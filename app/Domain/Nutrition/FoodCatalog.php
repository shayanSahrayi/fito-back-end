<?php

namespace App\Domain\Nutrition;

use App\Models\CoachProfile;
use App\Models\Food;
use App\Models\FoodNutrientRevision;
use Illuminate\Support\Facades\DB;

final class FoodCatalog
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPlatform(array $data): array
    {
        return $this->create(null, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPrivate(CoachProfile $coach, array $data): array
    {
        return $this->create($coach->id, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function renamePlatform(Food $food, array $data): array
    {
        $this->assertPlatform($food);

        return $this->rename($food, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function renamePrivate(CoachProfile $coach, Food $food, array $data): array
    {
        $this->assertOwn($coach, $food);

        return $this->rename($food, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function revisePlatform(Food $food, array $data): array
    {
        $this->assertPlatform($food);

        return $this->revise($food, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function revisePrivate(CoachProfile $coach, Food $food, array $data): array
    {
        $this->assertOwn($coach, $food);

        return $this->revise($food, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function archivePlatform(Food $food): array
    {
        $this->assertPlatform($food);

        return $this->archive($food);
    }

    /**
     * @return array<string, mixed>
     */
    public function archivePrivate(CoachProfile $coach, Food $food): array
    {
        $this->assertOwn($coach, $food);

        return $this->archive($food);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAdmin(): array
    {
        return Food::query()
            ->with('currentRevision')
            ->whereNull('coach_profile_id')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Food $food): array => $this->present($food))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCoach(CoachProfile $coach): array
    {
        return Food::query()
            ->with('currentRevision')
            ->where(function ($query) use ($coach): void {
                $query->where(function ($bank): void {
                    $bank->whereNull('coach_profile_id')->where('status', 'active');
                })->orWhere('coach_profile_id', $coach->id);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Food $food): array => $this->present($food))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showForAdmin(Food $food): array
    {
        $this->assertPlatform($food);

        return $this->present($food);
    }

    /**
     * @return array<string, mixed>
     */
    public function showForCoach(CoachProfile $coach, Food $food): array
    {
        $visible = $food->coach_profile_id === null
            ? $food->status === 'active'
            : (int) $food->coach_profile_id === $coach->id;

        if (! $visible) {
            throw new NutritionException('food_not_found', 'غذا پیدا نشد.', 404);
        }

        return $this->present($food);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Food $food): array
    {
        $food->loadMissing('currentRevision');

        return [
            'id' => $food->id,
            'coach_profile_id' => $food->coach_profile_id === null ? null : (int) $food->coach_profile_id,
            'name' => $food->name,
            'status' => $food->status,
            'current_revision_id' => $food->current_revision_id === null ? null : (int) $food->current_revision_id,
            'current_revision' => $food->currentRevision === null ? null : $this->presentRevision($food->currentRevision),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRevision(FoodNutrientRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'revision_number' => (int) $revision->revision_number,
            'basis' => $revision->basis,
            'unit_code' => $revision->unit_code,
            'unit_label' => $revision->unit_label,
            'grams_per_unit' => $revision->grams_per_unit === null ? null : (int) $revision->grams_per_unit,
            'energy_kcal' => (int) $revision->energy_kcal,
            'protein_mg' => (int) $revision->protein_mg,
            'carbohydrate_mg' => (int) $revision->carbohydrate_mg,
            'fat_mg' => (int) $revision->fat_mg,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function create(?int $coachProfileId, array $data): array
    {
        return DB::transaction(function () use ($coachProfileId, $data): array {
            $food = Food::query()->create([
                'coach_profile_id' => $coachProfileId,
                'name' => $this->name($data['name'] ?? null),
                'status' => 'active',
                'current_revision_id' => null,
            ]);
            $revision = $this->storeRevision($food, 1, $data);
            $food->current_revision_id = $revision->id;
            $food->save();

            return $this->present($food);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function rename(Food $food, array $data): array
    {
        $this->assertActive($food);
        $food->name = $this->name($data['name'] ?? null);
        $food->save();

        return $this->present($food);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function revise(Food $food, array $data): array
    {
        return DB::transaction(function () use ($food, $data): array {
            $locked = Food::query()->whereKey($food->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw new NutritionException('food_not_found', 'غذا پیدا نشد.', 404);
            }

            $this->assertActive($locked);
            $current = FoodNutrientRevision::query()->where('food_id', $locked->id)->max('revision_number');
            $revision = $this->storeRevision($locked, ($current === null ? 0 : (int) $current) + 1, $data);
            $locked->current_revision_id = $revision->id;
            $locked->save();

            return $this->present($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function archive(Food $food): array
    {
        $food->status = 'archived';
        $food->save();

        return $this->present($food);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeRevision(Food $food, int $number, array $data): FoodNutrientRevision
    {
        $basis = $this->basis($data['basis'] ?? null);
        $unitCode = $this->unitCode($basis, $data['unit_code'] ?? null);

        return $food->revisions()->create([
            'revision_number' => $number,
            'basis' => $basis,
            'unit_code' => $unitCode,
            'unit_label' => $this->label($data['unit_label'] ?? null),
            'grams_per_unit' => $this->gramsPerUnit($basis, $data),
            'energy_kcal' => $this->nutrient($data['energy_kcal'] ?? null),
            'protein_mg' => $this->nutrient($data['protein_mg'] ?? null),
            'carbohydrate_mg' => $this->nutrient($data['carbohydrate_mg'] ?? null),
            'fat_mg' => $this->nutrient($data['fat_mg'] ?? null),
        ]);
    }

    private function assertPlatform(Food $food): void
    {
        if ($food->coach_profile_id !== null) {
            throw new NutritionException('food_not_found', 'غذا پیدا نشد.', 404);
        }
    }

    private function assertOwn(CoachProfile $coach, Food $food): void
    {
        if ($food->coach_profile_id === null) {
            throw new NutritionException('food_locked', 'غذای بانک فقط توسط مدیر ویرایش می‌شود.', 403);
        }

        if ((int) $food->coach_profile_id !== $coach->id) {
            throw new NutritionException('food_not_found', 'غذا پیدا نشد.', 404);
        }
    }

    private function assertActive(Food $food): void
    {
        if ($food->status === 'archived') {
            throw new NutritionException('food_archived', 'غذا آرشیو شده است.');
        }
    }

    private function name(mixed $value): string
    {
        $name = trim((string) $value);

        if ($name === '' || mb_strlen($name) > 160) {
            throw new NutritionException('invalid_name', 'نام غذا معتبر نیست.');
        }

        return $name;
    }

    private function label(mixed $value): string
    {
        $label = trim((string) $value);

        if ($label === '' || mb_strlen($label) > 40) {
            throw new NutritionException('invalid_unit', 'برچسب واحد معتبر نیست.');
        }

        return $label;
    }

    private function basis(mixed $value): string
    {
        $basis = (string) $value;

        if (! in_array($basis, ['per_100g', 'per_unit'], true)) {
            throw new NutritionException('invalid_basis', 'مبنای غذا معتبر نیست.');
        }

        return $basis;
    }

    private function unitCode(string $basis, mixed $value): string
    {
        $code = trim((string) $value);
        $allowed = $basis === 'per_100g' ? ['g'] : ['ml', 'piece'];

        if (! in_array($code, $allowed, true)) {
            throw new NutritionException('invalid_unit', 'واحد غذا با مبنا جور نیست.');
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function gramsPerUnit(string $basis, array $data): ?int
    {
        $present = array_key_exists('grams_per_unit', $data) && $data['grams_per_unit'] !== null;

        if ($basis === 'per_100g') {
            if (array_key_exists('grams_per_unit', $data)) {
                throw new NutritionException('irrelevant_measure', 'گرم هر واحد برای مبنای صد گرم معنی ندارد.');
            }

            return null;
        }

        if (! $present) {
            return null;
        }

        return $this->nutrient($data['grams_per_unit'], 1);
    }

    private function nutrient(mixed $value, int $min = 0): int
    {
        $numeric = is_int($value) || (is_string($value) && ctype_digit($value));

        if (! $numeric) {
            throw new NutritionException('invalid_nutrient', 'عدد تغذیه معتبر نیست.');
        }

        $number = (int) $value;

        if ($number < $min || $number > 4294967295) {
            throw new NutritionException('invalid_nutrient', 'عدد تغذیه معتبر نیست.');
        }

        return $number;
    }
}
