<?php

namespace App\Domain\Workout;

use App\Domain\Feedback\Notifier;
use App\Models\Exercise;
use App\Models\PersonalRecordEvent;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionSet;

final class RecordBook
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function record(WorkoutSession $session, WorkoutSessionSet $set, Exercise $exercise): array
    {
        if (! $set->is_completed) {
            return [];
        }

        $events = [];
        $weight = $this->intOrNull($set->weight_g);
        $reps = $this->intOrNull($set->reps);
        $duration = $this->intOrNull($set->duration_seconds);
        $distance = $this->intOrNull($set->distance_m);

        if ($exercise->tracking_type === 'weight_reps' && $weight !== null) {
            $events[] = $this->beat($session, $set, $exercise, 'max_weight_g', $weight, null);
        }

        if ($exercise->tracking_type === 'reps' && $reps !== null) {
            $events[] = $this->beat($session, $set, $exercise, 'max_reps', $reps, null);
        }

        if ($exercise->tracking_type === 'duration' && $duration !== null) {
            $events[] = $this->beat($session, $set, $exercise, 'max_duration_s', $duration, null);
        }

        if ($exercise->tracking_type === 'distance' && $distance !== null) {
            $events[] = $this->beat($session, $set, $exercise, 'max_distance_m', $distance, null);
        }

        if ($exercise->tracking_type === 'weight_reps' && $weight !== null && $reps !== null && $reps >= 1 && $reps <= 10) {
            $events[] = $this->beat(
                $session,
                $set,
                $exercise,
                'estimated_1rm_g',
                Epley::estimated1rm($weight, $reps),
                'epley_v1',
            );
        }

        return array_values(array_filter($events));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function beat(
        WorkoutSession $session,
        WorkoutSessionSet $set,
        Exercise $exercise,
        string $metric,
        int $value,
        ?string $formula,
    ): ?array {
        $current = PersonalRecordEvent::query()
            ->where('athlete_profile_id', $session->athlete_profile_id)
            ->where('exercise_id', $exercise->id)
            ->where('metric', $metric)
            ->orderByDesc('value_int')
            ->lockForUpdate()
            ->first();

        if ($current !== null && $value <= (int) $current->value_int) {
            return null;
        }

        $event = PersonalRecordEvent::query()->create([
            'athlete_profile_id' => $session->athlete_profile_id,
            'exercise_id' => $exercise->id,
            'coaching_relationship_id' => $session->coaching_relationship_id,
            'metric' => $metric,
            'value_int' => $value,
            'workout_session_set_id' => $set->id,
            'formula_code' => $formula,
            'recorded_at' => now(),
        ]);
        $this->notifier->recordAchieved($session, $event);

        return $this->present($event);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PersonalRecordEvent $event): array
    {
        return [
            'id' => $event->id,
            'metric' => $event->metric,
            'value_int' => (int) $event->value_int,
            'formula_code' => $event->formula_code,
            'workout_session_set_id' => (int) $event->workout_session_set_id,
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
