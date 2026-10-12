<?php

namespace App\Domain\Body;

use App\Domain\Commerce\CommerceException;
use App\Domain\Feedback\Notifier;
use App\Domain\Identity\AccountStatus;
use App\Models\AthleteProfile;
use App\Models\BodyObservation;
use App\Models\BodyObservationValue;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BodyLog
{
    public function __construct(
        private readonly BodyAccess $access,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function recordAsAthlete(User $user, array $data): array
    {
        $athlete = $this->athlete($user);

        return DB::transaction(function () use ($user, $athlete, $data): array {
            $visibility = (string) ($data['visibility'] ?? 'athlete_private');
            $relationship = null;

            if ($visibility === 'relationship') {
                $relationship = $this->relationshipForAthlete($athlete, (int) ($data['coaching_relationship_id'] ?? 0));
                $this->assertActive($relationship);
            } elseif ($visibility !== 'athlete_private' || $this->presentId($data, 'coaching_relationship_id') !== null) {
                throw new BodyException('invalid_visibility', 'دیده شدن این مشاهده معتبر نیست.');
            }

            $source = (string) ($data['source'] ?? 'manual');

            if (! in_array($source, ['manual', 'device'], true)) {
                throw new BodyException('invalid_source', 'منبع مشاهده معتبر نیست.');
            }

            return $this->store($user, $athlete, $relationship, $source, $visibility, $data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function recordAsCoach(User $user, CoachProfile $coach, CoachingRelationship $relationship, array $data): array
    {
        AccountStatus::assertActive($user);

        return DB::transaction(function () use ($user, $coach, $relationship, $data): array {
            $locked = $this->relationshipForCoach($coach, (int) $relationship->id);
            $this->assertActive($locked);
            $this->access->assert($coach);
            $athlete = AthleteProfile::query()->find($locked->athlete_profile_id);

            if ($athlete === null) {
                throw new BodyException('athlete_profile_required', 'ورزشکار این رابطه پیدا نشد.', 404);
            }

            return $this->store($user, $athlete, $locked, 'coach', 'relationship', $data);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAthlete(User $user): array
    {
        $athlete = $this->athlete($user);

        return BodyObservation::query()
            ->where('athlete_profile_id', $athlete->id)
            ->with('values')
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BodyObservation $observation): array => $this->present($observation))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCoach(CoachProfile $coach, CoachingRelationship $relationship): array
    {
        $locked = $this->relationshipForCoach($coach, (int) $relationship->id);

        return $this->visibleToCoach($coach, $locked)
            ->with('values')
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BodyObservation $observation): array => $this->present($observation))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showForAthlete(User $user, BodyObservation $observation): array
    {
        $athlete = $this->athlete($user);

        if ((int) $observation->athlete_profile_id !== $athlete->id) {
            throw new BodyException('observation_not_found', 'مشاهده پیدا نشد.', 404);
        }

        return $this->present($observation);
    }

    /**
     * @return array<string, mixed>
     */
    public function showForCoach(CoachProfile $coach, BodyObservation $observation): array
    {
        if (! $this->coachSees($coach, $observation)) {
            throw new BodyException('observation_not_found', 'مشاهده پیدا نشد.', 404);
        }

        return $this->present($observation);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function seriesForAthlete(User $user, string $metric): array
    {
        $athlete = $this->athlete($user);
        $code = $this->seriesCode($metric);

        return BodyObservation::query()
            ->where('athlete_profile_id', $athlete->id)
            ->whereHas('values', fn ($query) => $query->where('metric_code', $code)->where('origin', 'measured'))
            ->with('values')
            ->orderBy('measured_at')
            ->orderBy('id')
            ->get()
            ->map(fn (BodyObservation $observation): array => $this->point($observation, $code))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function seriesForCoach(CoachProfile $coach, CoachingRelationship $relationship, string $metric): array
    {
        $locked = $this->relationshipForCoach($coach, (int) $relationship->id);
        $code = $this->seriesCode($metric);

        return $this->visibleToCoach($coach, $locked)
            ->whereHas('values', fn ($query) => $query->where('metric_code', $code)->where('origin', 'measured'))
            ->with('values')
            ->orderBy('measured_at')
            ->orderBy('id')
            ->get()
            ->map(fn (BodyObservation $observation): array => $this->point($observation, $code))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function compareForAthlete(User $user, int $beforeId, int $afterId): array
    {
        $athlete = $this->athlete($user);

        return $this->compare(
            $this->ownedByAthlete($athlete, $beforeId),
            $this->ownedByAthlete($athlete, $afterId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function compareForCoach(CoachProfile $coach, int $beforeId, int $afterId): array
    {
        return $this->compare(
            $this->ownedByCoach($coach, $beforeId),
            $this->ownedByCoach($coach, $afterId),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function store(
        User $user,
        AthleteProfile $athlete,
        ?CoachingRelationship $relationship,
        string $source,
        string $visibility,
        array $data,
    ): array {
        $measuredAt = $this->moment($data['measured_at'] ?? null);

        if ($relationship !== null && $measuredAt->lt($relationship->started_at)) {
            throw new BodyException('measured_before_contract', 'زمان اندازه‌گیری قبل از شروع قرارداد است.');
        }

        $observation = BodyObservation::query()->create([
            'athlete_profile_id' => $athlete->id,
            'coaching_relationship_id' => $relationship?->id,
            'recorded_by_user_id' => $user->id,
            'source' => $source,
            'visibility' => $visibility,
            'measured_at' => $measuredAt,
            'note' => $this->note($data['note'] ?? null),
        ]);
        $stored = $this->metrics($data['metrics'] ?? []);

        foreach ($stored as $code => $value) {
            if ($code === 'bmi_centi') {
                continue;
            }

            $observation->values()->create([
                'metric_code' => $code,
                'value_int' => $value,
                'origin' => 'measured',
                'formula_code' => null,
            ]);
        }

        if (isset($stored['bmi_centi'])) {
            $observation->values()->create([
                'metric_code' => 'bmi_centi',
                'value_int' => $stored['bmi_centi'],
                'origin' => 'measured',
                'formula_code' => null,
            ]);
        }

        $weight = $stored['weight_g'] ?? null;
        $height = $stored['height_mm'] ?? $this->earlierHeight($athlete->id, $measuredAt, (int) $observation->id);

        if ($weight !== null && $height !== null) {
            $observation->values()->create([
                'metric_code' => 'bmi_centi',
                'value_int' => Bmi::centi($weight, $height),
                'origin' => 'calculated',
                'formula_code' => 'bmi_v1',
            ]);
        }

        if ($relationship !== null) {
            $relationship->loadMissing('athleteProfile', 'coachProfile');
            $recipient = $source === 'coach'
                ? $relationship->athleteProfile?->user_id
                : $relationship->coachProfile?->user_id;

            if ($recipient !== null && (int) $recipient !== $user->id) {
                $this->notifier->bodyRecorded((int) $recipient, (int) $observation->id);
            }
        }

        return $this->present($observation);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, int>
     */
    private function metrics(mixed $metrics): array
    {
        if ($metrics === null) {
            return [];
        }

        if (! is_array($metrics)) {
            throw new BodyException('invalid_metric', 'فهرست معیار معتبر نیست.');
        }

        $stored = [];

        foreach ($metrics as $name => $value) {
            if ($value === null) {
                continue;
            }

            $field = BodyUnits::FIELDS[$name] ?? null;

            if ($field === null) {
                throw new BodyException('invalid_metric', 'این معیار شناخته نشده است.');
            }

            $stored[$field['code']] = BodyUnits::toStorage($value, $field['scale'], $field['min']);
        }

        return $stored;
    }

    private function earlierHeight(int $athleteId, Carbon $measuredAt, int $currentId): ?int
    {
        $observationId = BodyObservation::query()
            ->where('athlete_profile_id', $athleteId)
            ->where('id', '!=', $currentId)
            ->where('measured_at', '<', $measuredAt)
            ->whereHas('values', fn ($query) => $query->where('metric_code', 'height_mm')->where('origin', 'measured'))
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->value('id');

        if ($observationId === null) {
            return null;
        }

        $value = BodyObservationValue::query()
            ->where('body_observation_id', $observationId)
            ->where('metric_code', 'height_mm')
            ->where('origin', 'measured')
            ->value('value_int');

        return $value === null ? null : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function compare(BodyObservation $before, BodyObservation $after): array
    {
        if ($before->id === $after->id) {
            throw new BodyException('invalid_compare', 'دو مشاهده باید متفاوت باشند.');
        }

        $before->loadMissing('values');
        $after->loadMissing('values');
        $codes = $before->values->pluck('metric_code')
            ->merge($after->values->pluck('metric_code'))
            ->unique()
            ->values();
        $rows = [];

        foreach ($codes as $code) {
            $field = BodyUnits::fieldFor((string) $code);

            if ($field === null || $code === 'bmi_centi') {
                continue;
            }

            $left = $this->measured($before, (string) $code);
            $right = $this->measured($after, (string) $code);
            $scale = BodyUnits::scaleOf((string) $code);
            $rows[] = [
                'metric' => $field,
                'before' => $left === null ? null : BodyUnits::fromStorage($left, $scale),
                'after' => $right === null ? null : BodyUnits::fromStorage($right, $scale),
                'delta' => $left === null || $right === null ? null : BodyUnits::fromStorage($right - $left, $scale),
            ];
        }

        $rows[] = [
            'metric' => 'bmi',
            'before' => $this->originBmi($before, 'measured'),
            'after' => $this->originBmi($after, 'measured'),
            'delta' => $this->originBmiDelta($before, $after, 'measured'),
        ];
        $rows[] = [
            'metric' => 'bmi_calculated',
            'before' => $this->bmiValue($before),
            'after' => $this->bmiValue($after),
            'delta' => $this->bmiDelta($before, $after),
        ];

        return [
            'before_id' => $before->id,
            'after_id' => $after->id,
            'metrics' => array_values(array_filter(
                $rows,
                fn (array $row): bool => $row['before'] !== null || $row['after'] !== null,
            )),
        ];
    }

    private function originBmi(BodyObservation $observation, string $origin): int|string|null
    {
        $value = $observation->values->first(
            fn (BodyObservationValue $row): bool => $row->metric_code === 'bmi_centi' && $row->origin === $origin,
        );

        return $value === null ? null : BodyUnits::fromStorage((int) $value->value_int, 2);
    }

    private function originBmiDelta(BodyObservation $before, BodyObservation $after, string $origin): int|string|null
    {
        $left = $this->originBmi($before, $origin);
        $right = $this->originBmi($after, $origin);

        if ($left === null || $right === null) {
            return null;
        }

        $leftStored = (int) $before->values->first(
            fn (BodyObservationValue $row): bool => $row->metric_code === 'bmi_centi' && $row->origin === $origin,
        )->value_int;
        $rightStored = (int) $after->values->first(
            fn (BodyObservationValue $row): bool => $row->metric_code === 'bmi_centi' && $row->origin === $origin,
        )->value_int;

        return BodyUnits::fromStorage($rightStored - $leftStored, 2);
    }

    private function bmiValue(BodyObservation $observation): int|string|null
    {
        $value = $observation->values->first(
            fn (BodyObservationValue $row): bool => $row->metric_code === 'bmi_centi' && $row->origin === 'calculated',
        );

        return $value === null ? null : BodyUnits::fromStorage((int) $value->value_int, 2);
    }

    private function bmiDelta(BodyObservation $before, BodyObservation $after): int|string|null
    {
        $left = $before->values->first(
            fn (BodyObservationValue $row): bool => $row->metric_code === 'bmi_centi' && $row->origin === 'calculated',
        );
        $right = $after->values->first(
            fn (BodyObservationValue $row): bool => $row->metric_code === 'bmi_centi' && $row->origin === 'calculated',
        );

        if ($left === null || $right === null) {
            return null;
        }

        return BodyUnits::fromStorage((int) $right->value_int - (int) $left->value_int, 2);
    }

    private function measured(BodyObservation $observation, string $code): ?int
    {
        $row = $observation->values->first(
            fn (BodyObservationValue $value): bool => $value->metric_code === $code && $value->origin === 'measured',
        );

        return $row === null ? null : (int) $row->value_int;
    }

    /**
     * @return array<string, mixed>
     */
    private function point(BodyObservation $observation, string $code): array
    {
        return [
            'observation_id' => $observation->id,
            'measured_at' => $observation->measured_at->toJSON(),
            'value' => BodyUnits::fromStorage((int) $this->measured($observation, $code), BodyUnits::scaleOf($code)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BodyObservation $observation): array
    {
        $observation->load('values');
        $metrics = [];

        foreach ($observation->values as $value) {
            if ($value->origin !== 'measured' || $value->metric_code === 'bmi_centi') {
                continue;
            }

            $field = BodyUnits::fieldFor($value->metric_code);

            if ($field !== null) {
                $metrics[$field] = BodyUnits::fromStorage((int) $value->value_int, BodyUnits::scaleOf($value->metric_code));
            }
        }

        $measuredBmi = $observation->values->first(
            fn (BodyObservationValue $value): bool => $value->metric_code === 'bmi_centi' && $value->origin === 'measured',
        );
        $calculatedBmi = $observation->values->first(
            fn (BodyObservationValue $value): bool => $value->metric_code === 'bmi_centi' && $value->origin === 'calculated',
        );

        if ($measuredBmi !== null) {
            $metrics['bmi'] = BodyUnits::fromStorage((int) $measuredBmi->value_int, 2);
        }

        if ($calculatedBmi !== null) {
            $metrics['bmi_calculated'] = BodyUnits::fromStorage((int) $calculatedBmi->value_int, 2);
        }

        return [
            'id' => $observation->id,
            'athlete_profile_id' => (int) $observation->athlete_profile_id,
            'coaching_relationship_id' => $observation->coaching_relationship_id === null ? null : (int) $observation->coaching_relationship_id,
            'source' => $observation->source,
            'visibility' => $observation->visibility,
            'measured_at' => $observation->measured_at->toJSON(),
            'note' => $observation->note,
            'metrics' => $metrics,
        ];
    }

    private function visibleToCoach(CoachProfile $coach, CoachingRelationship $relationship): \Illuminate\Database\Eloquent\Builder
    {
        $query = BodyObservation::query()
            ->where('coaching_relationship_id', $relationship->id)
            ->where('visibility', 'relationship')
            ->where('measured_at', '>=', $relationship->started_at);

        if ($relationship->ended_at !== null) {
            $query->where('measured_at', '<=', $relationship->ended_at);
        }

        return $query;
    }

    private function coachSees(CoachProfile $coach, BodyObservation $observation): bool
    {
        if ($observation->visibility !== 'relationship' || $observation->coaching_relationship_id === null) {
            return false;
        }

        $relationship = CoachingRelationship::query()->find($observation->coaching_relationship_id);

        if ($relationship === null || (int) $relationship->coach_profile_id !== $coach->id) {
            return false;
        }

        if ($observation->measured_at->lt($relationship->started_at)) {
            return false;
        }

        return $relationship->ended_at === null || $observation->measured_at->lte($relationship->ended_at);
    }

    private function ownedByAthlete(AthleteProfile $athlete, int $id): BodyObservation
    {
        $observation = BodyObservation::query()->with('values')->find($id);

        if ($observation === null || (int) $observation->athlete_profile_id !== $athlete->id) {
            throw new BodyException('observation_not_found', 'مشاهده پیدا نشد.', 404);
        }

        return $observation;
    }

    private function ownedByCoach(CoachProfile $coach, int $id): BodyObservation
    {
        $observation = BodyObservation::query()->with('values')->find($id);

        if ($observation === null || ! $this->coachSees($coach, $observation)) {
            throw new BodyException('observation_not_found', 'مشاهده پیدا نشد.', 404);
        }

        return $observation;
    }

    private function relationshipForCoach(CoachProfile $coach, int $id): CoachingRelationship
    {
        $relationship = CoachingRelationship::query()->whereKey($id)->lockForUpdate()->first()
            ?? CoachingRelationship::query()->find($id);

        if ($relationship === null || (int) $relationship->coach_profile_id !== $coach->id) {
            throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
        }

        return $relationship;
    }

    private function relationshipForAthlete(AthleteProfile $athlete, int $id): CoachingRelationship
    {
        $relationship = CoachingRelationship::query()->whereKey($id)->first();

        if ($relationship === null || (int) $relationship->athlete_profile_id !== $athlete->id) {
            throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
        }

        return $relationship;
    }

    private function assertActive(CoachingRelationship $relationship): void
    {
        if ($relationship->status !== 'active') {
            throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
        }
    }

    private function athlete(User $user): AthleteProfile
    {
        AccountStatus::assertActive($user);
        $athlete = $user->athleteProfile;

        if ($athlete === null) {
            throw new BodyException('athlete_profile_required', 'این مسیر فقط برای ورزشکار است.', 403);
        }

        return $athlete;
    }

    private function seriesCode(string $metric): string
    {
        $field = BodyUnits::FIELDS[$metric] ?? null;

        if ($field === null || $metric === 'bmi') {
            throw new BodyException('invalid_metric', 'این معیار برای سری زمانی شناخته نشده است.');
        }

        return $field['code'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function presentId(array $data, string $key): ?int
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        return (int) $data[$key];
    }

    private function moment(mixed $value): Carbon
    {
        $text = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})?$/', $text) !== 1) {
            throw new BodyException('invalid_time', 'زمان اندازه‌گیری معتبر نیست.');
        }

        return Carbon::parse($text)->utc();
    }

    private function note(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $note = trim((string) $value);

        if ($note === '') {
            return null;
        }

        if (mb_strlen($note) > 500) {
            throw new BodyException('invalid_note', 'یادداشت مشاهده معتبر نیست.');
        }

        return $note;
    }
}
