<?php

namespace App\Domain\Workout;

use App\Domain\Catalog\CatalogRules;
use App\Domain\Commerce\CommerceException;
use App\Domain\Identity\AccountStatus;
use App\Models\AthleteProfile;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutAssignment;
use App\Models\WorkoutDay;
use App\Models\WorkoutDayExercise;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionSet;
use Illuminate\Support\Facades\DB;

final class SessionLogger
{
    /**
     * @var list<string>
     */
    private const COLUMNS = ['reps', 'weight_g', 'duration_seconds', 'distance_m'];

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'weight_reps' => ['reps', 'weight_g'],
        'reps' => ['reps'],
        'duration' => ['duration_seconds'],
        'distance' => ['distance_m'],
    ];

    public function __construct(private readonly RecordBook $records) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function start(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $athlete = $this->athlete($user);
            $source = (string) ($data['source'] ?? '');

            if (! in_array($source, ['prescribed', 'athlete_free'], true)) {
                throw new WorkoutException('invalid_source', 'نوع جلسه معتبر نیست.');
            }

            $link = $source === 'prescribed'
                ? $this->prescribedLink($athlete, $data)
                : $this->freeLink($athlete, $data);

            $session = WorkoutSession::query()->create([
                'athlete_profile_id' => $athlete->id,
                'coaching_relationship_id' => $link['coaching_relationship_id'],
                'workout_assignment_id' => $link['workout_assignment_id'],
                'workout_day_id' => $link['workout_day_id'],
                'performed_on' => $this->date($data['performed_on'] ?? null),
                'started_at' => now(),
                'completed_at' => null,
                'status' => 'in_progress',
                'source' => $source,
                'perceived_effort' => $this->effort($data['perceived_effort'] ?? null),
                'athlete_note' => CatalogRules::optionalText($data['athlete_note'] ?? null, 500),
            ]);

            return $this->present($session);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function addSet(User $user, WorkoutSession $session, array $data): array
    {
        return DB::transaction(function () use ($user, $session, $data): array {
            $athlete = $this->athlete($user);
            $locked = $this->own($athlete, $session, true);

            if ($locked->status !== 'in_progress') {
                throw new WorkoutException('session_completed', 'این جلسه بسته شده است.');
            }

            $this->assertWritable($locked);
            [$exercise, $dayExerciseId, $measures, $completed] = $this->performed($locked, $data);
            $current = WorkoutSessionSet::query()->where('workout_session_id', $locked->id)->max('sequence_no');
            $sequence = ($current === null ? 0 : (int) $current) + 1;

            if ($sequence > 65535) {
                throw new WorkoutException('invalid_measure', 'ظرفیت ست‌های این جلسه پر شده است.');
            }

            $set = $locked->sets()->create([
                'sequence_no' => $sequence,
                'workout_day_exercise_id' => $dayExerciseId,
                'exercise_id' => $exercise->id,
                'set_index' => $this->boundedInt($data['set_index'] ?? null, 0, 65535),
                'reps' => $measures['reps'],
                'weight_g' => $measures['weight_g'],
                'duration_seconds' => $measures['duration_seconds'],
                'distance_m' => $measures['distance_m'],
                'is_completed' => $completed,
            ]);

            return $this->presentSet($set, $this->records->record($locked, $set, $exercise));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(User $user, WorkoutSession $session): array
    {
        return DB::transaction(function () use ($user, $session): array {
            $athlete = $this->athlete($user);
            $locked = $this->own($athlete, $session, true);

            if ($locked->status === 'completed') {
                return $this->present($locked);
            }

            $this->assertWritable($locked);
            $locked->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            return $this->present($locked);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAthlete(User $user): array
    {
        AccountStatus::assertActive($user);
        $athlete = $user->athleteProfile;

        if ($athlete === null) {
            return [];
        }

        return WorkoutSession::query()
            ->where('athlete_profile_id', $athlete->id)
            ->with(['sets.exercise', 'sets.records'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (WorkoutSession $session): array => $this->present($session))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $user, WorkoutSession $session): array
    {
        $athlete = $this->athlete($user);

        return $this->present($this->own($athlete, $session, false));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCoach(CoachProfile $coach, CoachingRelationship $relationship): array
    {
        if ((int) $relationship->coach_profile_id !== $coach->id) {
            throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
        }

        return WorkoutSession::query()
            ->where('coaching_relationship_id', $relationship->id)
            ->with(['sets.exercise', 'sets.records'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (WorkoutSession $session): array => $this->present($session))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(WorkoutSession $session): array
    {
        $session->loadMissing(['sets.exercise', 'sets.records']);

        return [
            'id' => $session->id,
            'athlete_profile_id' => (int) $session->athlete_profile_id,
            'coaching_relationship_id' => $session->coaching_relationship_id === null ? null : (int) $session->coaching_relationship_id,
            'workout_assignment_id' => $session->workout_assignment_id === null ? null : (int) $session->workout_assignment_id,
            'workout_day_id' => $session->workout_day_id === null ? null : (int) $session->workout_day_id,
            'performed_on' => $session->performed_on->toDateString(),
            'started_at' => $session->started_at?->toJSON(),
            'completed_at' => $session->completed_at?->toJSON(),
            'status' => $session->status,
            'source' => $session->source,
            'perceived_effort' => $session->perceived_effort === null ? null : (int) $session->perceived_effort,
            'athlete_note' => $session->athlete_note,
            'sets' => $session->sets
                ->map(fn (WorkoutSessionSet $set): array => $this->presentSet($set))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $records
     * @return array<string, mixed>
     */
    private function presentSet(WorkoutSessionSet $set, ?array $records = null): array
    {
        $set->loadMissing(['exercise', 'records']);

        return [
            'id' => $set->id,
            'sequence_no' => (int) $set->sequence_no,
            'set_index' => (int) $set->set_index,
            'workout_day_exercise_id' => $set->workout_day_exercise_id === null ? null : (int) $set->workout_day_exercise_id,
            'exercise_id' => (int) $set->exercise_id,
            'reps' => $this->intOrNull($set->reps),
            'weight_g' => $this->intOrNull($set->weight_g),
            'duration_seconds' => $this->intOrNull($set->duration_seconds),
            'distance_m' => $this->intOrNull($set->distance_m),
            'is_completed' => (bool) $set->is_completed,
            'exercise' => $set->exercise === null ? null : [
                'id' => $set->exercise->id,
                'name' => $set->exercise->name,
                'tracking_type' => $set->exercise->tracking_type,
            ],
            'records' => $records ?? $set->records
                ->map(fn ($event): array => $this->records->present($event))
                ->values()
                ->all(),
        ];
    }

    private function athlete(User $user): AthleteProfile
    {
        AccountStatus::assertActive($user);
        $athlete = $user->athleteProfile;

        if ($athlete === null) {
            throw new WorkoutException('athlete_profile_required', 'این مسیر فقط برای ورزشکار است.', 403);
        }

        return $athlete;
    }

    private function own(AthleteProfile $athlete, WorkoutSession $session, bool $lock): WorkoutSession
    {
        $query = WorkoutSession::query()->whereKey($session->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $locked = $query->first();

        if ($locked === null || (int) $locked->athlete_profile_id !== $athlete->id) {
            throw new WorkoutException('session_not_found', 'جلسه پیدا نشد.', 404);
        }

        return $locked;
    }

    private function assertWritable(WorkoutSession $session): void
    {
        if ($session->coaching_relationship_id === null) {
            return;
        }

        $relationship = CoachingRelationship::query()->find($session->coaching_relationship_id);

        if ($relationship === null || $relationship->status !== 'active') {
            throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{coaching_relationship_id: int, workout_assignment_id: int, workout_day_id: int|null}
     */
    private function prescribedLink(AthleteProfile $athlete, array $data): array
    {
        $assignment = WorkoutAssignment::query()->find((int) ($data['workout_assignment_id'] ?? 0));

        if ($assignment === null || (int) $assignment->athlete_profile_id !== $athlete->id) {
            throw new WorkoutException('assignment_not_found', 'تخصیص پیدا نشد.', 404);
        }

        $relationship = CoachingRelationship::query()->whereKey($assignment->coaching_relationship_id)->lockForUpdate()->first();

        if ($relationship === null || $relationship->status !== 'active') {
            throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
        }

        $dayId = $data['workout_day_id'] ?? null;

        if ($dayId !== null) {
            $day = WorkoutDay::query()->find((int) $dayId);

            if ($day === null || (int) $day->workout_program_version_id !== (int) $assignment->workout_program_version_id) {
                throw new WorkoutException('day_not_in_program', 'این روز در نسخه تخصیص‌یافته نیست.');
            }
        }

        return [
            'coaching_relationship_id' => $relationship->id,
            'workout_assignment_id' => $assignment->id,
            'workout_day_id' => $dayId === null ? null : (int) $dayId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{coaching_relationship_id: int|null, workout_assignment_id: null, workout_day_id: null}
     */
    private function freeLink(AthleteProfile $athlete, array $data): array
    {
        $relationshipId = $data['coaching_relationship_id'] ?? null;

        if ($relationshipId === null) {
            return [
                'coaching_relationship_id' => null,
                'workout_assignment_id' => null,
                'workout_day_id' => null,
            ];
        }

        $relationship = CoachingRelationship::query()->whereKey((int) $relationshipId)->lockForUpdate()->first();

        if ($relationship === null || (int) $relationship->athlete_profile_id !== $athlete->id) {
            throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
        }

        if ($relationship->status !== 'active') {
            throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
        }

        return [
            'coaching_relationship_id' => $relationship->id,
            'workout_assignment_id' => null,
            'workout_day_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Exercise, 1: int|null, 2: array<string, int|null>, 3: bool}
     */
    private function performed(WorkoutSession $session, array $data): array
    {
        if ($session->source === 'prescribed') {
            $item = $this->prescribedItem($session, $data);
            $exercise = $item->exercise;

            if ($exercise === null) {
                throw new WorkoutException('exercise_not_available', 'این حرکت برای این جلسه در دسترس نیست.');
            }

            return [$exercise, $item->id, $this->measures($exercise->tracking_type, $data), $this->completed($data)];
        }

        if (array_key_exists('workout_day_exercise_id', $data) && $data['workout_day_exercise_id'] !== null) {
            throw new WorkoutException('prescribed_item_not_found', 'جلسه آزاد قلم تجویز ندارد.');
        }

        $exercise = Exercise::query()->find((int) ($data['exercise_id'] ?? 0));
        $this->assertFreeExercise($session, $exercise);

        return [$exercise, null, $this->measures($exercise->tracking_type, $data), $this->completed($data)];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function prescribedItem(WorkoutSession $session, array $data): WorkoutDayExercise
    {
        if (! array_key_exists('workout_day_exercise_id', $data) || $data['workout_day_exercise_id'] === null) {
            throw new WorkoutException('prescribed_item_required', 'ست تجویزی به قلم برنامه وصل است.');
        }

        $item = WorkoutDayExercise::query()->with(['day', 'exercise'])->find((int) $data['workout_day_exercise_id']);

        if ($item === null || $item->day === null) {
            throw new WorkoutException('prescribed_item_not_found', 'قلم تجویز پیدا نشد.', 404);
        }

        if ($session->workout_day_id !== null && (int) $item->workout_day_id !== (int) $session->workout_day_id) {
            throw new WorkoutException('prescribed_item_not_found', 'قلم تجویز پیدا نشد.', 404);
        }

        $session->loadMissing('assignment');
        $versionId = $session->assignment === null ? null : (int) $session->assignment->workout_program_version_id;

        if ($versionId === null || (int) $item->day->workout_program_version_id !== $versionId) {
            throw new WorkoutException('prescribed_item_not_found', 'قلم تجویز پیدا نشد.', 404);
        }

        return $item;
    }

    private function assertFreeExercise(WorkoutSession $session, ?Exercise $exercise): void
    {
        if ($exercise === null || $exercise->status !== 'active') {
            throw new WorkoutException('exercise_not_available', 'این حرکت برای این جلسه در دسترس نیست.');
        }

        if ($exercise->coach_profile_id === null) {
            return;
        }

        $session->loadMissing('assignment');
        $relationship = $session->coaching_relationship_id === null
            ? null
            : CoachingRelationship::query()->find($session->coaching_relationship_id);
        $allowed = $relationship !== null && (int) $exercise->coach_profile_id === (int) $relationship->coach_profile_id;

        if (! $allowed) {
            throw new WorkoutException('exercise_not_available', 'این حرکت برای این جلسه در دسترس نیست.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|null>
     */
    private function measures(string $type, array $data): array
    {
        CatalogRules::tracking($type);
        $allowed = self::ALLOWED[$type];
        $values = array_fill_keys(self::COLUMNS, null);

        foreach (self::COLUMNS as $column) {
            if (array_key_exists($column, $data) && ! in_array($column, $allowed, true)) {
                throw new WorkoutException('irrelevant_measure', 'این معیار با نوع ثبت حرکت جور نیست.');
            }
        }

        foreach ($allowed as $column) {
            if (! array_key_exists($column, $data) || $data[$column] === null) {
                continue;
            }

            $max = $column === 'reps' ? 65535 : 4294967295;
            $values[$column] = $this->boundedInt($data[$column], 1, $max);
        }

        $present = array_filter($values, fn (mixed $value): bool => $value !== null);

        if ($present === []) {
            throw new WorkoutException('measure_required', 'معیار این نوع حرکت لازم است.');
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function completed(array $data): bool
    {
        if (! array_key_exists('is_completed', $data) || $data['is_completed'] === null) {
            return true;
        }

        return (bool) $data['is_completed'];
    }

    private function effort(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->boundedInt($value, 1, 10);
    }

    private function date(mixed $value): string
    {
        $date = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new WorkoutException('invalid_period', 'تاریخ معتبر نیست.');
        }

        return $date;
    }

    private function boundedInt(mixed $value, int $min, int $max): int
    {
        $numeric = is_int($value) || (is_string($value) && ctype_digit($value));

        if (! $numeric) {
            throw new WorkoutException('invalid_measure', 'عدد معیار معتبر نیست.');
        }

        $number = (int) $value;

        if ($number < $min || $number > $max) {
            throw new WorkoutException('invalid_measure', 'عدد معیار معتبر نیست.');
        }

        return $number;
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
