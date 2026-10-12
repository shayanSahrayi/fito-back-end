<?php

namespace App\Domain\Nutrition;

use App\Domain\Commerce\CommerceException;
use App\Domain\Feedback\Notifier;
use App\Domain\Identity\AccountStatus;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\MealAssignment;
use App\Models\MealPlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MealAssigner
{
    public function __construct(
        private readonly NutritionAccess $access,
        private readonly MealTree $tree,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function assign(User $coachUser, CoachProfile $coach, array $data): array
    {
        return DB::transaction(function () use ($coachUser, $coach, $data): array {
            $relationship = $this->relationship($coach, (int) $data['coaching_relationship_id']);
            $this->access->assert($coach);

            if ($relationship->status !== 'active') {
                throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
            }

            $version = $this->publishedVersion($coach, (int) $data['meal_plan_version_id']);
            [$startsOn, $endsOn] = $this->period($data['starts_on'] ?? null, $data['ends_on'] ?? null);
            $assignment = MealAssignment::query()->create([
                'coaching_relationship_id' => $relationship->id,
                'athlete_profile_id' => $relationship->athlete_profile_id,
                'meal_plan_version_id' => $version->id,
                'assigned_by_user_id' => $coachUser->id,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'active',
            ]);
            $relationship->loadMissing('athleteProfile');
            $this->notifier->programAssigned(
                (int) $relationship->athleteProfile->user_id,
                'meal_assignment',
                (int) $assignment->id,
                true,
            );

            return $this->present($assignment);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function end(CoachProfile $coach, MealAssignment $assignment): array
    {
        return DB::transaction(function () use ($coach, $assignment): array {
            $locked = MealAssignment::query()->whereKey($assignment->id)->lockForUpdate()->first();

            if ($locked === null || ! $this->owned($coach, $locked)) {
                throw new NutritionException('assignment_not_found', 'تخصیص پیدا نشد.', 404);
            }

            $this->access->assert($coach);

            if ($locked->status !== 'active') {
                throw new NutritionException('assignment_not_active', 'این تخصیص فعال نیست.');
            }

            $relationship = CoachingRelationship::query()->find($locked->coaching_relationship_id);

            if ($relationship === null || $relationship->status !== 'active') {
                throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
            }

            $locked->status = 'ended';
            $locked->save();

            return $this->present($locked);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCoach(CoachProfile $coach): array
    {
        return MealAssignment::query()
            ->whereHas('version.plan', fn ($query) => $query->where('coach_profile_id', $coach->id))
            ->with(['version.plan', 'version.days.meals.items.food', 'version.days.meals.items.revision'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (MealAssignment $assignment): array => $this->present($assignment))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAthlete(User $user): array
    {
        AccountStatus::assertActive($user);
        $profile = $user->athleteProfile;

        if ($profile === null) {
            return [];
        }

        return MealAssignment::query()
            ->where('athlete_profile_id', $profile->id)
            ->with(['version.plan', 'version.days.meals.items.food', 'version.days.meals.items.revision'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (MealAssignment $assignment): array => $this->present($assignment))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showForAthlete(User $user, MealAssignment $assignment): array
    {
        AccountStatus::assertActive($user);
        $profile = $user->athleteProfile;

        if ($profile === null || (int) $assignment->athlete_profile_id !== $profile->id) {
            throw new NutritionException('assignment_not_found', 'تخصیص پیدا نشد.', 404);
        }

        return $this->present($assignment);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(MealAssignment $assignment): array
    {
        $assignment->loadMissing('version.plan');

        return [
            'id' => $assignment->id,
            'coaching_relationship_id' => (int) $assignment->coaching_relationship_id,
            'athlete_profile_id' => (int) $assignment->athlete_profile_id,
            'meal_plan_version_id' => (int) $assignment->meal_plan_version_id,
            'starts_on' => $assignment->starts_on->toDateString(),
            'ends_on' => $assignment->ends_on?->toDateString(),
            'status' => $assignment->status,
            'plan' => [
                'id' => $assignment->version->plan->id,
                'title' => $assignment->version->plan->title,
            ],
            'version' => $this->tree->presentVersion($assignment->version),
        ];
    }

    private function relationship(CoachProfile $coach, int $id): CoachingRelationship
    {
        $relationship = CoachingRelationship::query()->whereKey($id)->lockForUpdate()->first();

        if ($relationship === null || (int) $relationship->coach_profile_id !== $coach->id) {
            throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
        }

        return $relationship;
    }

    private function publishedVersion(CoachProfile $coach, int $id): MealPlanVersion
    {
        $version = MealPlanVersion::query()->with('plan')->find($id);

        if ($version === null || $version->plan === null || (int) $version->plan->coach_profile_id !== $coach->id) {
            throw new NutritionException('plan_not_found', 'برنامه پیدا نشد.', 404);
        }

        if ($version->plan->status === 'archived') {
            throw new NutritionException('plan_archived', 'برنامه آرشیو شده است.');
        }

        if ($version->state !== 'published') {
            throw new NutritionException('version_not_published', 'فقط نسخه منتشرشده تخصیص داده می‌شود.');
        }

        return $version;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function period(mixed $startsOn, mixed $endsOn): array
    {
        $start = $this->date($startsOn);
        $end = $endsOn === null || $endsOn === '' ? null : $this->date($endsOn);

        if ($end !== null && $end < $start) {
            throw new NutritionException('invalid_period', 'تاریخ پایان قبل از شروع است.');
        }

        return [$start, $end];
    }

    private function date(mixed $value): string
    {
        $date = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new NutritionException('invalid_period', 'تاریخ معتبر نیست.');
        }

        return $date;
    }

    private function owned(CoachProfile $coach, MealAssignment $assignment): bool
    {
        $assignment->loadMissing('version.plan');

        return $assignment->version !== null
            && $assignment->version->plan !== null
            && (int) $assignment->version->plan->coach_profile_id === $coach->id;
    }
}
