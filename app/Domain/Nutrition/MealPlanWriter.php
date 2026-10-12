<?php

namespace App\Domain\Nutrition;

use App\Models\CoachProfile;
use App\Models\Meal;
use App\Models\MealDay;
use App\Models\MealPlan;
use App\Models\MealPlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MealPlanWriter
{
    public function __construct(
        private readonly NutritionAccess $access,
        private readonly MealTree $tree,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(User $user, CoachProfile $coach, array $data): array
    {
        return DB::transaction(function () use ($user, $coach, $data): array {
            $this->access->assert($coach);
            $plan = MealPlan::query()->create([
                'coach_profile_id' => $coach->id,
                'title' => $this->title($data['title'] ?? null),
                'status' => 'draft',
                'current_version_id' => null,
            ]);
            $version = $this->newVersion($plan, $user, 1);
            $this->tree->replace($version, $data['days'] ?? [], $coach);

            return $this->present($plan);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateDraft(CoachProfile $coach, MealPlan $plan, array $data): array
    {
        return DB::transaction(function () use ($coach, $plan, $data): array {
            $locked = $this->lockOwned($coach, $plan);
            $this->assertWritable($locked);
            $this->access->assert($coach);
            $draft = $this->requireDraft($locked);

            if (array_key_exists('title', $data)) {
                $locked->title = $this->title($data['title']);
                $locked->save();
            }

            if (array_key_exists('days', $data)) {
                $this->tree->replace($draft, $data['days'], $coach);
            }

            return $this->present($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(CoachProfile $coach, MealPlan $plan): array
    {
        return DB::transaction(function () use ($coach, $plan): array {
            $locked = $this->lockOwned($coach, $plan);
            $this->assertWritable($locked);
            $this->access->assert($coach);
            $draft = $this->requireDraft($locked);
            $draft->load('days.meals.items');
            $empty = $draft->days->isEmpty()
                || $draft->days->contains(fn (MealDay $day): bool => $day->meals->isEmpty()
                    || $day->meals->contains(fn (Meal $meal): bool => $meal->items->isEmpty()));

            if ($empty) {
                throw new NutritionException('days_required', 'برنامه حداقل یک روز، یک وعده و یک قلم لازم دارد.');
            }

            $draft->forceFill([
                'state' => 'published',
                'published_at' => now(),
            ])->save();
            $locked->forceFill([
                'status' => 'published',
                'current_version_id' => $draft->id,
            ])->save();

            return $this->present($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function openNextDraft(User $user, CoachProfile $coach, MealPlan $plan): array
    {
        return DB::transaction(function () use ($user, $coach, $plan): array {
            $locked = $this->lockOwned($coach, $plan);
            $this->assertWritable($locked);
            $this->access->assert($coach);

            if ($this->findDraft($locked) !== null) {
                throw new NutritionException('draft_open', 'یک پیش‌نویس باز وجود دارد.');
            }

            if ($locked->current_version_id === null) {
                throw new NutritionException('nothing_published', 'اول این برنامه را منتشر کن.');
            }

            $current = MealPlanVersion::query()->find($locked->current_version_id);

            if ($current === null || $current->state !== 'published') {
                throw new NutritionException('nothing_published', 'اول این برنامه را منتشر کن.');
            }

            $max = MealPlanVersion::query()->where('meal_plan_id', $locked->id)->max('version_number');
            $next = $this->newVersion($locked, $user, ($max === null ? 0 : (int) $max) + 1);
            $this->tree->copy($current, $next);

            return $this->present($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(CoachProfile $coach, MealPlan $plan): array
    {
        return DB::transaction(function () use ($coach, $plan): array {
            $locked = $this->lockOwned($coach, $plan);
            $this->access->assert($coach);
            $locked->status = 'archived';
            $locked->save();

            return $this->present($locked);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCoach(CoachProfile $coach): array
    {
        return MealPlan::query()
            ->where('coach_profile_id', $coach->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (MealPlan $plan): array => $this->summary($plan))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(CoachProfile $coach, MealPlan $plan): array
    {
        $this->assertOwned($coach, $plan);

        return $this->present($plan);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(MealPlan $plan): array
    {
        $plan->refresh();
        $draft = $this->findDraft($plan);
        $current = $plan->current_version_id === null
            ? null
            : MealPlanVersion::query()->find($plan->current_version_id);

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'current_version_id' => $plan->current_version_id === null ? null : (int) $plan->current_version_id,
            'current_version' => $current === null ? null : $this->tree->presentVersion($current),
            'draft' => $draft === null ? null : $this->tree->presentVersion($draft),
        ];
    }

    private function newVersion(MealPlan $plan, User $user, int $number): MealPlanVersion
    {
        return $plan->versions()->create([
            'version_number' => $number,
            'state' => 'draft',
            'published_at' => null,
            'created_by_user_id' => $user->id,
        ]);
    }

    private function lockOwned(CoachProfile $coach, MealPlan $plan): MealPlan
    {
        $locked = MealPlan::query()->whereKey($plan->id)->lockForUpdate()->first();

        if ($locked === null) {
            throw new NutritionException('plan_not_found', 'برنامه پیدا نشد.', 404);
        }

        $this->assertOwned($coach, $locked);

        return $locked;
    }

    private function assertOwned(CoachProfile $coach, MealPlan $plan): void
    {
        if ((int) $plan->coach_profile_id !== $coach->id) {
            throw new NutritionException('plan_not_found', 'برنامه پیدا نشد.', 404);
        }
    }

    private function assertWritable(MealPlan $plan): void
    {
        if ($plan->status === 'archived') {
            throw new NutritionException('plan_archived', 'برنامه آرشیو شده است.');
        }
    }

    private function requireDraft(MealPlan $plan): MealPlanVersion
    {
        $draft = $this->findDraft($plan);

        if ($draft === null) {
            throw new NutritionException('draft_required', 'برای ویرایش اول باید پیش‌نویس باز شود.');
        }

        return $draft;
    }

    private function findDraft(MealPlan $plan): ?MealPlanVersion
    {
        return MealPlanVersion::query()
            ->where('meal_plan_id', $plan->id)
            ->where('state', 'draft')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(MealPlan $plan): array
    {
        $draftId = MealPlanVersion::query()
            ->where('meal_plan_id', $plan->id)
            ->where('state', 'draft')
            ->orderByDesc('id')
            ->value('id');

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'current_version_id' => $plan->current_version_id === null ? null : (int) $plan->current_version_id,
            'draft_version_id' => $draftId === null ? null : (int) $draftId,
        ];
    }

    private function title(mixed $value): string
    {
        $title = trim((string) $value);

        if ($title === '' || mb_strlen($title) > 160) {
            throw new NutritionException('invalid_title', 'عنوان برنامه معتبر نیست.');
        }

        return $title;
    }
}
