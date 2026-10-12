<?php

namespace App\Domain\Workout;

use App\Domain\Catalog\CatalogRules;
use App\Models\CoachProfile;
use App\Models\Exercise;
use App\Models\WorkoutDay;
use App\Models\WorkoutDayExercise;
use App\Models\WorkoutProgramVersion;

final class Prescription
{
    /**
     * @var list<string>
     */
    private const COLUMNS = [
        'prescribed_sets',
        'reps_min',
        'reps_max',
        'target_weight_g',
        'rest_seconds',
        'duration_seconds',
        'distance_m',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'weight_reps' => ['prescribed_sets', 'reps_min', 'reps_max', 'target_weight_g', 'rest_seconds'],
        'reps' => ['prescribed_sets', 'reps_min', 'reps_max', 'rest_seconds'],
        'duration' => ['duration_seconds'],
        'distance' => ['distance_m'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const REQUIRED = [
        'weight_reps' => ['reps_min'],
        'reps' => ['reps_min'],
        'duration' => ['duration_seconds'],
        'distance' => ['distance_m'],
    ];

    /**
     * @param  list<array<string, mixed>>  $days
     */
    public function replace(WorkoutProgramVersion $version, array $days, CoachProfile $coach): void
    {
        if ($version->state !== 'draft') {
            throw new WorkoutException('version_locked', 'نسخه منتشرشده تغییر نمی‌کند.');
        }

        $prepared = $this->prepare($days, $coach);
        $this->clear($version);

        foreach ($prepared as $day) {
            $stored = $version->days()->create([
                'position' => $day['position'],
                'title' => $day['title'],
                'weekday' => $day['weekday'],
            ]);

            foreach ($day['exercises'] as $exercise) {
                $stored->exercises()->create($exercise);
            }
        }
    }

    public function copy(WorkoutProgramVersion $source, WorkoutProgramVersion $target): void
    {
        $source->loadMissing('days.exercises');

        foreach ($source->days as $day) {
            $copy = $target->days()->create([
                'position' => $day->position,
                'title' => $day->title,
                'weekday' => $day->weekday,
            ]);

            foreach ($day->exercises as $exercise) {
                $copy->exercises()->create([
                    'exercise_id' => $exercise->exercise_id,
                    'position' => $exercise->position,
                    'tracking_type' => $exercise->tracking_type,
                    'prescribed_sets' => $exercise->prescribed_sets,
                    'reps_min' => $exercise->reps_min,
                    'reps_max' => $exercise->reps_max,
                    'target_weight_g' => $exercise->target_weight_g,
                    'rest_seconds' => $exercise->rest_seconds,
                    'duration_seconds' => $exercise->duration_seconds,
                    'distance_m' => $exercise->distance_m,
                    'coach_note' => $exercise->coach_note,
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function presentVersion(WorkoutProgramVersion $version): array
    {
        $version->loadMissing('days.exercises.exercise');

        return [
            'id' => $version->id,
            'version_number' => (int) $version->version_number,
            'state' => $version->state,
            'note' => $version->note,
            'published_at' => $version->published_at?->toJSON(),
            'days' => $version->days
                ->map(fn (WorkoutDay $day): array => [
                    'id' => $day->id,
                    'position' => (int) $day->position,
                    'title' => $day->title,
                    'weekday' => $day->weekday === null ? null : (int) $day->weekday,
                    'exercises' => $day->exercises
                        ->map(fn (WorkoutDayExercise $item): array => $this->presentItem($item))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(WorkoutDayExercise $item): array
    {
        $exercise = $item->exercise;

        return [
            'id' => $item->id,
            'position' => (int) $item->position,
            'exercise_id' => (int) $item->exercise_id,
            'tracking_type' => $item->tracking_type,
            'prescribed_sets' => $this->intOrNull($item->prescribed_sets),
            'reps_min' => $this->intOrNull($item->reps_min),
            'reps_max' => $this->intOrNull($item->reps_max),
            'target_weight_g' => $this->intOrNull($item->target_weight_g),
            'rest_seconds' => $this->intOrNull($item->rest_seconds),
            'duration_seconds' => $this->intOrNull($item->duration_seconds),
            'distance_m' => $this->intOrNull($item->distance_m),
            'coach_note' => $item->coach_note,
            'exercise' => $exercise === null ? null : [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'instructions' => $exercise->instructions,
                'goal' => $exercise->goal,
                'tracking_type' => $exercise->tracking_type,
            ],
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * @param  list<array<string, mixed>>  $days
     * @return list<array<string, mixed>>
     */
    private function prepare(array $days, CoachProfile $coach): array
    {
        if ($days === []) {
            throw new WorkoutException('days_required', 'برنامه حداقل یک روز لازم دارد.');
        }

        $exerciseIds = [];

        foreach ($days as $day) {
            foreach ($day['exercises'] ?? [] as $exercise) {
                $exerciseIds[] = (int) ($exercise['exercise_id'] ?? 0);
            }
        }

        $found = Exercise::query()
            ->whereIn('id', array_values(array_unique($exerciseIds)))
            ->get()
            ->keyBy('id');

        $prepared = [];
        $dayPositions = [];

        foreach ($days as $day) {
            $position = $this->position($day['position'] ?? null);

            if (isset($dayPositions[$position])) {
                throw new WorkoutException('position_taken', 'ترتیب روزها تکراری است.');
            }

            $dayPositions[$position] = true;
            $weekday = $this->weekday($day['weekday'] ?? null);
            $items = [];
            $itemPositions = [];

            foreach ($day['exercises'] ?? [] as $exercise) {
                $itemPosition = $this->position($exercise['position'] ?? null);

                if (isset($itemPositions[$itemPosition])) {
                    throw new WorkoutException('position_taken', 'ترتیب حرکت‌های یک روز تکراری است.');
                }

                $itemPositions[$itemPosition] = true;
                $exerciseId = (int) ($exercise['exercise_id'] ?? 0);
                $this->assertExercise($found->get($exerciseId), $coach);
                $type = CatalogRules::tracking($exercise['tracking_type'] ?? null);

                $items[] = array_merge([
                    'exercise_id' => $exerciseId,
                    'position' => $itemPosition,
                    'tracking_type' => $type,
                    'coach_note' => CatalogRules::optionalText($exercise['coach_note'] ?? null, 500),
                ], $this->measures($type, $exercise));
            }

            if ($items === []) {
                throw new WorkoutException('days_required', 'هر روز حداقل یک حرکت لازم دارد.');
            }

            $prepared[] = [
                'position' => $position,
                'title' => CatalogRules::boundedText($day['title'] ?? null, 120, 'invalid_title', 'عنوان روز معتبر نیست.'),
                'weekday' => $weekday,
                'exercises' => $items,
            ];
        }

        return $prepared;
    }

    private function assertExercise(?Exercise $exercise, CoachProfile $coach): void
    {
        $owned = $exercise !== null
            && $exercise->status === 'active'
            && ($exercise->coach_profile_id === null || (int) $exercise->coach_profile_id === $coach->id);

        if (! $owned) {
            throw new WorkoutException('exercise_not_available', 'این حرکت برای این برنامه در دسترس نیست.');
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, int|null>
     */
    private function measures(string $type, array $item): array
    {
        $allowed = self::ALLOWED[$type];
        $values = array_fill_keys(self::COLUMNS, null);

        foreach (self::COLUMNS as $column) {
            if (array_key_exists($column, $item) && ! in_array($column, $allowed, true)) {
                throw new WorkoutException('irrelevant_measure', 'این معیار با نوع ثبت حرکت جور نیست.');
            }
        }

        foreach ($allowed as $column) {
            $required = in_array($column, self::REQUIRED[$type], true);
            $missing = ! array_key_exists($column, $item) || $item[$column] === null;

            if ($missing) {
                if ($required) {
                    throw new WorkoutException('measure_required', 'معیار این نوع حرکت لازم است.');
                }

                continue;
            }

            $max = in_array($column, ['target_weight_g', 'duration_seconds', 'distance_m'], true)
                ? 4294967295
                : 65535;
            $min = $column === 'rest_seconds' ? 0 : 1;
            $values[$column] = $this->boundedInt($item[$column], $min, $max);
        }

        if ($values['reps_max'] !== null && $values['reps_min'] !== null && $values['reps_max'] < $values['reps_min']) {
            throw new WorkoutException('invalid_reps', 'حداکثر تکرار از حداقل کمتر است.');
        }

        return $values;
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

    private function position(mixed $value): int
    {
        return $this->boundedInt($value, 0, 65535);
    }

    private function weekday(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->boundedInt($value, 0, 6);
    }

    private function clear(WorkoutProgramVersion $version): void
    {
        $dayIds = $version->days()->pluck('id');

        if ($dayIds->isNotEmpty()) {
            WorkoutDayExercise::query()->whereIn('workout_day_id', $dayIds)->delete();
            WorkoutDay::query()->whereIn('id', $dayIds)->delete();
        }

        $version->unsetRelation('days');
    }
}
