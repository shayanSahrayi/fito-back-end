<?php

namespace App\Domain\Workout;

use App\Domain\Commerce\CommerceException;
use App\Domain\Feedback\Notifier;
use App\Domain\Identity\AccountStatus;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\User;
use App\Models\WorkoutAssignment;
use App\Models\WorkoutProgramVersion;
use Illuminate\Support\Facades\DB;

final class WorkoutAssigner
{
    public function __construct(
        private readonly WorkoutAccess $access,
        private readonly Prescription $prescription,
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

            $version = $this->publishedVersion($coach, (int) $data['workout_program_version_id']);
            [$startsOn, $endsOn] = $this->period($data['starts_on'] ?? null, $data['ends_on'] ?? null);

            $assignment = WorkoutAssignment::query()->create([
                'coaching_relationship_id' => $relationship->id,
                'athlete_profile_id' => $relationship->athlete_profile_id,
                'workout_program_version_id' => $version->id,
                'assigned_by_user_id' => $coachUser->id,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'active',
            ]);
            $relationship->loadMissing('athleteProfile');
            $this->notifier->programAssigned(
                (int) $relationship->athleteProfile->user_id,
                'workout_assignment',
                (int) $assignment->id,
                false,
            );

            return $this->present($assignment);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function end(CoachProfile $coach, WorkoutAssignment $assignment): array
    {
        return DB::transaction(function () use ($coach, $assignment): array {
            $locked = WorkoutAssignment::query()->whereKey($assignment->id)->lockForUpdate()->first();

            if ($locked === null || ! $this->owned($coach, $locked)) {
                throw new WorkoutException('assignment_not_found', 'تخصیص پیدا نشد.', 404);
            }

            $this->access->assert($coach);

            if ($locked->status !== 'active') {
                throw new WorkoutException('assignment_not_active', 'این تخصیص فعال نیست.');
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
        return WorkoutAssignment::query()
            ->whereHas('version.program', fn ($query) => $query->where('coach_profile_id', $coach->id))
            ->with(['version.program', 'version.days.exercises.exercise'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (WorkoutAssignment $assignment): array => $this->present($assignment))
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

        return WorkoutAssignment::query()
            ->where('athlete_profile_id', $profile->id)
            ->with(['version.program', 'version.days.exercises.exercise'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (WorkoutAssignment $assignment): array => $this->present($assignment))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showForAthlete(User $user, WorkoutAssignment $assignment): array
    {
        AccountStatus::assertActive($user);
        $profile = $user->athleteProfile;

        if ($profile === null || (int) $assignment->athlete_profile_id !== $profile->id) {
            throw new WorkoutException('assignment_not_found', 'تخصیص پیدا نشد.', 404);
        }

        return $this->present($assignment);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(WorkoutAssignment $assignment): array
    {
        $assignment->loadMissing('version.program', 'version.days.exercises.exercise');

        return [
            'id' => $assignment->id,
            'coaching_relationship_id' => (int) $assignment->coaching_relationship_id,
            'athlete_profile_id' => (int) $assignment->athlete_profile_id,
            'workout_program_version_id' => (int) $assignment->workout_program_version_id,
            'starts_on' => $assignment->starts_on->toDateString(),
            'ends_on' => $assignment->ends_on?->toDateString(),
            'status' => $assignment->status,
            'program' => [
                'id' => $assignment->version->program->id,
                'title' => $assignment->version->program->title,
            ],
            'version' => $this->prescription->presentVersion($assignment->version),
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

    private function publishedVersion(CoachProfile $coach, int $id): WorkoutProgramVersion
    {
        $version = WorkoutProgramVersion::query()->with('program')->find($id);

        if ($version === null || $version->program === null || (int) $version->program->coach_profile_id !== $coach->id) {
            throw new WorkoutException('program_not_found', 'برنامه پیدا نشد.', 404);
        }

        if ($version->program->status === 'archived') {
            throw new WorkoutException('program_archived', 'برنامه آرشیو شده است.');
        }

        if ($version->state !== 'published') {
            throw new WorkoutException('version_not_published', 'فقط نسخه منتشرشده تخصیص داده می‌شود.');
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
            throw new WorkoutException('invalid_period', 'تاریخ پایان قبل از شروع است.');
        }

        return [$start, $end];
    }

    private function date(mixed $value): string
    {
        $date = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new WorkoutException('invalid_period', 'تاریخ معتبر نیست.');
        }

        return $date;
    }

    private function owned(CoachProfile $coach, WorkoutAssignment $assignment): bool
    {
        $assignment->loadMissing('version.program');

        return $assignment->version !== null
            && $assignment->version->program !== null
            && (int) $assignment->version->program->coach_profile_id === $coach->id;
    }
}
