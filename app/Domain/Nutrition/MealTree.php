<?php

namespace App\Domain\Nutrition;

use App\Models\CoachProfile;
use App\Models\Food;
use App\Models\FoodNutrientRevision;
use App\Models\Meal;
use App\Models\MealDay;
use App\Models\MealItem;
use App\Models\MealPlanVersion;

final class MealTree
{
    /**
     * @var list<string>
     */
    private const MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack', 'other'];

    public function __construct(private readonly FoodCatalog $foods) {}

    /**
     * @param  list<array<string, mixed>>  $days
     */
    public function replace(MealPlanVersion $version, array $days, CoachProfile $coach): void
    {
        if ($version->state !== 'draft') {
            throw new NutritionException('version_locked', 'نسخه منتشرشده تغییر نمی‌کند.');
        }

        $prepared = $this->prepare($days, $coach);
        $this->clear($version);

        foreach ($prepared as $day) {
            $storedDay = $version->days()->create([
                'position' => $day['position'],
                'title' => $day['title'],
            ]);

            foreach ($day['meals'] as $meal) {
                $storedMeal = $storedDay->meals()->create([
                    'position' => $meal['position'],
                    'meal_type' => $meal['meal_type'],
                    'title' => $meal['title'],
                ]);

                foreach ($meal['items'] as $item) {
                    $storedMeal->items()->create($item);
                }
            }
        }
    }

    public function copy(MealPlanVersion $source, MealPlanVersion $target): void
    {
        $source->loadMissing('days.meals.items');

        foreach ($source->days as $day) {
            $copyDay = $target->days()->create([
                'position' => $day->position,
                'title' => $day->title,
            ]);

            foreach ($day->meals as $meal) {
                $copyMeal = $copyDay->meals()->create([
                    'position' => $meal->position,
                    'meal_type' => $meal->meal_type,
                    'title' => $meal->title,
                ]);

                foreach ($meal->items as $item) {
                    $copyMeal->items()->create([
                        'position' => $item->position,
                        'food_id' => $item->food_id,
                        'food_nutrient_revision_id' => $item->food_nutrient_revision_id,
                        'quantity_g' => $item->quantity_g,
                        'quantity_unit_milli' => $item->quantity_unit_milli,
                    ]);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function presentVersion(MealPlanVersion $version): array
    {
        $version->loadMissing('days.meals.items.food', 'days.meals.items.revision');
        $totals = NutrientMath::zero();
        $days = [];

        foreach ($version->days as $day) {
            $presented = $this->presentDay($day);
            $totals = NutrientMath::add($totals, $presented['totals']);
            $days[] = $presented;
        }

        return [
            'id' => $version->id,
            'version_number' => (int) $version->version_number,
            'state' => $version->state,
            'published_at' => $version->published_at?->toJSON(),
            'totals' => $totals,
            'days' => $days,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $days
     * @return list<array<string, mixed>>
     */
    private function prepare(array $days, CoachProfile $coach): array
    {
        if ($days === []) {
            throw new NutritionException('days_required', 'برنامه حداقل یک روز لازم دارد.');
        }

        $foodIds = [];

        foreach ($days as $day) {
            foreach ($day['meals'] ?? [] as $meal) {
                foreach ($meal['items'] ?? [] as $item) {
                    $foodIds[] = (int) ($item['food_id'] ?? 0);
                }
            }
        }

        $found = Food::query()
            ->with('currentRevision')
            ->whereIn('id', array_values(array_unique($foodIds)))
            ->get()
            ->keyBy('id');

        $prepared = [];
        $dayPositions = [];

        foreach ($days as $day) {
            $position = $this->position($day['position'] ?? null);

            if (isset($dayPositions[$position])) {
                throw new NutritionException('position_taken', 'ترتیب روزها تکراری است.');
            }

            $dayPositions[$position] = true;
            $meals = $this->meals($day['meals'] ?? [], $found, $coach);

            if ($meals === []) {
                throw new NutritionException('days_required', 'هر روز حداقل یک وعده لازم دارد.');
            }

            $prepared[] = [
                'position' => $position,
                'title' => $this->title($day['title'] ?? null, 120, 'عنوان روز معتبر نیست.'),
                'meals' => $meals,
            ];
        }

        return $prepared;
    }

    /**
     * @param  list<array<string, mixed>>  $meals
     * @param  \Illuminate\Support\Collection<int, Food>  $found
     * @return list<array<string, mixed>>
     */
    private function meals(array $meals, $found, CoachProfile $coach): array
    {
        $prepared = [];
        $positions = [];

        foreach ($meals as $meal) {
            $position = $this->position($meal['position'] ?? null);

            if (isset($positions[$position])) {
                throw new NutritionException('position_taken', 'ترتیب وعده‌های یک روز تکراری است.');
            }

            $positions[$position] = true;
            $type = (string) ($meal['meal_type'] ?? '');

            if (! in_array($type, self::MEAL_TYPES, true)) {
                throw new NutritionException('invalid_meal_type', 'نوع وعده معتبر نیست.');
            }

            $items = $this->items($meal['items'] ?? [], $found, $coach);

            if ($items === []) {
                throw new NutritionException('days_required', 'هر وعده حداقل یک قلم لازم دارد.');
            }

            $title = $meal['title'] ?? null;
            $prepared[] = [
                'position' => $position,
                'meal_type' => $type,
                'title' => $title === null || trim((string) $title) === '' ? null : $this->title($title, 120, 'عنوان وعده معتبر نیست.'),
                'items' => $items,
            ];
        }

        return $prepared;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  \Illuminate\Support\Collection<int, Food>  $found
     * @return list<array<string, mixed>>
     */
    private function items(array $items, $found, CoachProfile $coach): array
    {
        $prepared = [];
        $positions = [];

        foreach ($items as $item) {
            $position = $this->position($item['position'] ?? null);

            if (isset($positions[$position])) {
                throw new NutritionException('position_taken', 'ترتیب قلم‌های یک وعده تکراری است.');
            }

            $positions[$position] = true;
            $food = $found->get((int) ($item['food_id'] ?? 0));
            $revision = $this->usableRevision($food, $coach);
            $quantity = $this->quantity($revision, $item);

            $prepared[] = [
                'position' => $position,
                'food_id' => $food->id,
                'food_nutrient_revision_id' => $revision->id,
                'quantity_g' => $quantity['quantity_g'],
                'quantity_unit_milli' => $quantity['quantity_unit_milli'],
            ];
        }

        return $prepared;
    }

    private function usableRevision(?Food $food, CoachProfile $coach): FoodNutrientRevision
    {
        $owned = $food !== null
            && $food->status === 'active'
            && $food->currentRevision !== null
            && ($food->coach_profile_id === null || (int) $food->coach_profile_id === $coach->id);

        if (! $owned || $food === null || $food->currentRevision === null) {
            throw new NutritionException('food_not_available', 'این غذا برای این برنامه در دسترس نیست.');
        }

        return $food->currentRevision;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{quantity_g: int|null, quantity_unit_milli: int|null}
     */
    private function quantity(FoodNutrientRevision $revision, array $item): array
    {
        $grams = array_key_exists('quantity_g', $item);
        $units = array_key_exists('quantity_unit_milli', $item);

        if ($revision->basis === 'per_100g') {
            if ($units) {
                throw new NutritionException('irrelevant_measure', 'مقدار واحدی برای مبنای صد گرم معنی ندارد.');
            }

            if (! $grams || $item['quantity_g'] === null) {
                throw new NutritionException('quantity_required', 'مقدار گرمی لازم است.');
            }

            return [
                'quantity_g' => $this->amount($item['quantity_g']),
                'quantity_unit_milli' => null,
            ];
        }

        if ($grams) {
            throw new NutritionException('irrelevant_measure', 'مقدار گرمی برای مبنای واحدی معنی ندارد.');
        }

        if (! $units || $item['quantity_unit_milli'] === null) {
            throw new NutritionException('quantity_required', 'مقدار واحدی لازم است.');
        }

        return [
            'quantity_g' => null,
            'quantity_unit_milli' => $this->amount($item['quantity_unit_milli']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDay(MealDay $day): array
    {
        $totals = NutrientMath::zero();
        $meals = [];

        foreach ($day->meals as $meal) {
            $presented = $this->presentMeal($meal);
            $totals = NutrientMath::add($totals, $presented['totals']);
            $meals[] = $presented;
        }

        return [
            'id' => $day->id,
            'position' => (int) $day->position,
            'title' => $day->title,
            'totals' => $totals,
            'meals' => $meals,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMeal(Meal $meal): array
    {
        $totals = NutrientMath::zero();
        $items = [];

        foreach ($meal->items as $item) {
            $presented = $this->presentItem($item);
            $totals = NutrientMath::add($totals, $presented['totals']);
            $items[] = $presented;
        }

        return [
            'id' => $meal->id,
            'position' => (int) $meal->position,
            'meal_type' => $meal->meal_type,
            'title' => $meal->title,
            'totals' => $totals,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(MealItem $item): array
    {
        $revision = $item->revision;
        $quantity = 0;
        $totals = NutrientMath::zero();

        if ($revision !== null) {
            $quantity = $revision->basis === 'per_100g'
                ? (int) ($item->quantity_g ?? 0)
                : (int) ($item->quantity_unit_milli ?? 0);
            $totals = NutrientMath::line(
                $revision->basis,
                $quantity,
                (int) $revision->energy_kcal,
                (int) $revision->protein_mg,
                (int) $revision->carbohydrate_mg,
                (int) $revision->fat_mg,
            );
        }

        return [
            'id' => $item->id,
            'position' => (int) $item->position,
            'food_id' => (int) $item->food_id,
            'food_nutrient_revision_id' => (int) $item->food_nutrient_revision_id,
            'quantity_g' => $item->quantity_g === null ? null : (int) $item->quantity_g,
            'quantity_unit_milli' => $item->quantity_unit_milli === null ? null : (int) $item->quantity_unit_milli,
            'food' => $item->food === null ? null : [
                'id' => $item->food->id,
                'name' => $item->food->name,
            ],
            'revision' => $revision === null ? null : $this->foods->presentRevision($revision),
            'totals' => $totals,
        ];
    }

    private function clear(MealPlanVersion $version): void
    {
        $dayIds = $version->days()->pluck('id');

        if ($dayIds->isEmpty()) {
            return;
        }

        $mealIds = Meal::query()->whereIn('meal_day_id', $dayIds)->pluck('id');

        if ($mealIds->isNotEmpty()) {
            MealItem::query()->whereIn('meal_id', $mealIds)->delete();
            Meal::query()->whereIn('id', $mealIds)->delete();
        }

        MealDay::query()->whereIn('id', $dayIds)->delete();
        $version->unsetRelation('days');
    }

    private function position(mixed $value): int
    {
        $number = $this->amount($value, 0);

        if ($number > 65535) {
            throw new NutritionException('invalid_quantity', 'ترتیب معتبر نیست.');
        }

        return $number;
    }

    private function amount(mixed $value, int $min = 1): int
    {
        $numeric = is_int($value) || (is_string($value) && ctype_digit($value));

        if (! $numeric) {
            throw new NutritionException('invalid_quantity', 'مقدار معتبر نیست.');
        }

        $number = (int) $value;

        if ($number < $min || $number > 4294967295) {
            throw new NutritionException('invalid_quantity', 'مقدار معتبر نیست.');
        }

        return $number;
    }

    private function title(mixed $value, int $max, string $message): string
    {
        $title = trim((string) $value);

        if ($title === '' || mb_strlen($title) > $max) {
            throw new NutritionException('invalid_title', $message);
        }

        return $title;
    }
}
