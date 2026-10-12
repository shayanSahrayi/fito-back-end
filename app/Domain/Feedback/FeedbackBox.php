<?php

namespace App\Domain\Feedback;

use App\Domain\Commerce\CommerceException;
use App\Domain\Identity\AccountStatus;
use App\Models\AthleteProfile;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\Exercise;
use App\Models\FeedbackMessage;
use App\Models\FeedbackThread;
use App\Models\User;
use App\Models\WorkoutAssignment;
use App\Models\WorkoutDayExercise;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;

final class FeedbackBox
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function openAsCoach(User $user, CoachProfile $coach, array $data): array
    {
        AccountStatus::assertActive($user);

        return DB::transaction(function () use ($user, $coach, $data): array {
            $relationship = $this->relationshipForCoach($coach, (int) $data['coaching_relationship_id']);
            $this->assertActive($relationship);

            return $this->open($user, $relationship, $data, true);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function openAsAthlete(User $user, array $data): array
    {
        $athlete = $this->athlete($user);

        return DB::transaction(function () use ($user, $athlete, $data): array {
            $relationship = $this->relationshipForAthlete($athlete, (int) $data['coaching_relationship_id']);
            $this->assertActive($relationship);

            return $this->open($user, $relationship, $data, false);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function replyAsCoach(User $user, CoachProfile $coach, FeedbackThread $thread, array $data): array
    {
        AccountStatus::assertActive($user);

        return $this->reply($user, $this->ownedByCoach($coach, $thread), $thread, $data, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function replyAsAthlete(User $user, FeedbackThread $thread, array $data): array
    {
        $athlete = $this->athlete($user);

        return $this->reply($user, $this->ownedByAthlete($athlete, $thread), $thread, $data, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveAsCoach(User $user, CoachProfile $coach, FeedbackThread $thread): array
    {
        AccountStatus::assertActive($user);

        return $this->resolve($this->ownedByCoach($coach, $thread), $thread);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveAsAthlete(User $user, FeedbackThread $thread): array
    {
        $athlete = $this->athlete($user);

        return $this->resolve($this->ownedByAthlete($athlete, $thread), $thread);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCoach(CoachProfile $coach, ?int $relationshipId): array
    {
        if ($relationshipId !== null) {
            $this->relationshipForCoach($coach, $relationshipId);
        }

        return FeedbackThread::query()
            ->whereHas('relationship', fn ($query) => $query->where('coach_profile_id', $coach->id))
            ->when($relationshipId !== null, fn ($query) => $query->where('coaching_relationship_id', $relationshipId))
            ->orderByDesc('id')
            ->get()
            ->map(fn (FeedbackThread $thread): array => $this->summary($thread))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAthlete(User $user, ?int $relationshipId): array
    {
        $athlete = $this->athlete($user);

        if ($relationshipId !== null) {
            $this->relationshipForAthlete($athlete, $relationshipId);
        }

        return FeedbackThread::query()
            ->whereHas('relationship', fn ($query) => $query->where('athlete_profile_id', $athlete->id))
            ->whereHas('messages', fn ($query) => $query->where('visibility', 'shared'))
            ->when($relationshipId !== null, fn ($query) => $query->where('coaching_relationship_id', $relationshipId))
            ->orderByDesc('id')
            ->get()
            ->map(fn (FeedbackThread $thread): array => $this->summary($thread))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showForCoach(CoachProfile $coach, FeedbackThread $thread): array
    {
        $this->ownedByCoach($coach, $thread);

        return $this->present($thread, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function showForAthlete(User $user, FeedbackThread $thread): array
    {
        $athlete = $this->athlete($user);
        $this->ownedByAthlete($athlete, $thread);
        $shared = $thread->messages()->where('visibility', 'shared')->exists();

        if (! $shared) {
            throw new FeedbackException('thread_not_found', 'موضوع پیدا نشد.', 404);
        }

        return $this->present($thread, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function open(User $user, CoachingRelationship $relationship, array $data, bool $asCoach): array
    {
        $subjects = $this->subjects($relationship, $data);
        $thread = FeedbackThread::query()->create([
            'coaching_relationship_id' => $relationship->id,
            'opened_by_user_id' => $user->id,
            'workout_session_id' => $subjects['workout_session_id'],
            'workout_day_exercise_id' => $subjects['workout_day_exercise_id'],
            'exercise_id' => $subjects['exercise_id'],
            'status' => 'open',
        ]);
        $this->storeMessage($user, $relationship, $thread, $data, $asCoach);

        return $this->present($thread, ! $asCoach);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function reply(
        User $user,
        CoachingRelationship $relationship,
        FeedbackThread $thread,
        array $data,
        bool $asCoach,
    ): array {
        return DB::transaction(function () use ($user, $relationship, $thread, $data, $asCoach): array {
            $locked = FeedbackThread::query()->whereKey($thread->id)->lockForUpdate()->first();

            if ($locked === null || (int) $locked->coaching_relationship_id !== (int) $relationship->id) {
                throw new FeedbackException('thread_not_found', 'موضوع پیدا نشد.', 404);
            }

            $this->assertActive($relationship);

            if ($locked->status !== 'open') {
                throw new FeedbackException('thread_resolved', 'این موضوع بسته شده است.');
            }

            $message = $this->storeMessage($user, $relationship, $locked, $data, $asCoach);

            return $this->presentMessage($message);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve(CoachingRelationship $relationship, FeedbackThread $thread): array
    {
        return DB::transaction(function () use ($relationship, $thread): array {
            $locked = FeedbackThread::query()->whereKey($thread->id)->lockForUpdate()->first();

            if ($locked === null || (int) $locked->coaching_relationship_id !== (int) $relationship->id) {
                throw new FeedbackException('thread_not_found', 'موضوع پیدا نشد.', 404);
            }

            $this->assertActive($relationship);

            if ($locked->status !== 'resolved') {
                $locked->status = 'resolved';
                $locked->save();
            }

            return $this->summary($locked);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeMessage(
        User $user,
        CoachingRelationship $relationship,
        FeedbackThread $thread,
        array $data,
        bool $asCoach,
    ): FeedbackMessage {
        $visibility = $this->visibility($asCoach, $data);
        $message = $thread->messages()->create([
            'author_user_id' => $user->id,
            'visibility' => $visibility,
            'body' => $this->body($data['body'] ?? null),
            'pain_level' => $this->pain($data),
        ]);

        if ($visibility === 'shared') {
            $relationship->loadMissing('athleteProfile', 'coachProfile');
            $recipient = $asCoach
                ? $relationship->athleteProfile?->user_id
                : $relationship->coachProfile?->user_id;

            if ($recipient !== null && (int) $recipient !== $user->id) {
                $this->notifier->feedbackReplied((int) $recipient, (int) $message->id);
            }
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{workout_session_id: int|null, workout_day_exercise_id: int|null, exercise_id: int|null}
     */
    private function subjects(CoachingRelationship $relationship, array $data): array
    {
        $sessionId = $this->optionalId($data, 'workout_session_id');
        $dayExerciseId = $this->optionalId($data, 'workout_day_exercise_id');
        $exerciseId = $this->optionalId($data, 'exercise_id');
        $chosen = array_values(array_filter(
            [$sessionId, $dayExerciseId, $exerciseId],
            fn (?int $id): bool => $id !== null,
        ));

        if (count($chosen) > 1) {
            throw new FeedbackException('subject_conflict', 'فقط یک موضوع برای هر رشته مجاز است.');
        }

        if ($sessionId !== null) {
            $session = WorkoutSession::query()->find($sessionId);

            if ($session === null || (int) $session->coaching_relationship_id !== (int) $relationship->id) {
                throw new FeedbackException('subject_not_in_relationship', 'این موضوع به این رابطه تعلق ندارد.');
            }
        }

        if ($dayExerciseId !== null) {
            $line = WorkoutDayExercise::query()->with('day')->find($dayExerciseId);
            $versionId = $line?->day?->workout_program_version_id;
            $assigned = $versionId !== null && WorkoutAssignment::query()
                ->where('coaching_relationship_id', $relationship->id)
                ->where('workout_program_version_id', $versionId)
                ->exists();

            if (! $assigned) {
                throw new FeedbackException('subject_not_in_relationship', 'این موضوع به این رابطه تعلق ندارد.');
            }
        }

        if ($exerciseId !== null && ! $this->exerciseVisible($relationship, $exerciseId)) {
            throw new FeedbackException('subject_not_in_relationship', 'این موضوع به این رابطه تعلق ندارد.');
        }

        return [
            'workout_session_id' => $sessionId,
            'workout_day_exercise_id' => $dayExerciseId,
            'exercise_id' => $exerciseId,
        ];
    }

    private function exerciseVisible(CoachingRelationship $relationship, int $exerciseId): bool
    {
        $exercise = Exercise::query()->find($exerciseId);

        if ($exercise === null) {
            return false;
        }

        if ($exercise->coach_profile_id === null) {
            return $exercise->status === 'active';
        }

        return (int) $exercise->coach_profile_id === (int) $relationship->coach_profile_id;
    }

    private function relationshipForCoach(CoachProfile $coach, int $id): CoachingRelationship
    {
        $relationship = CoachingRelationship::query()->whereKey($id)->first();

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

    private function ownedByCoach(CoachProfile $coach, FeedbackThread $thread): CoachingRelationship
    {
        return $this->relationshipForCoach($coach, (int) $thread->coaching_relationship_id);
    }

    private function ownedByAthlete(AthleteProfile $athlete, FeedbackThread $thread): CoachingRelationship
    {
        return $this->relationshipForAthlete($athlete, (int) $thread->coaching_relationship_id);
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
            throw new FeedbackException('athlete_profile_required', 'این مسیر فقط برای ورزشکار است.', 403);
        }

        return $athlete;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function visibility(bool $asCoach, array $data): string
    {
        $value = $data['visibility'] ?? null;

        if ($value === null || $value === '') {
            if ($asCoach) {
                throw new FeedbackException('visibility_required', 'نوع دیده شدن پیام لازم است.');
            }

            return 'shared';
        }

        if (! in_array($value, ['shared', 'coach_only'], true)) {
            throw new FeedbackException('invalid_visibility', 'نوع دیده شدن پیام معتبر نیست.');
        }

        if (! $asCoach && $value === 'coach_only') {
            throw new FeedbackException('note_forbidden', 'یادداشت داخلی فقط برای مربی است.');
        }

        return $value;
    }

    private function body(mixed $value): string
    {
        $body = trim((string) $value);

        if ($body === '' || mb_strlen($body) > 2000) {
            throw new FeedbackException('invalid_body', 'متن پیام معتبر نیست.');
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pain(array $data): ?int
    {
        if (! array_key_exists('pain_level', $data) || $data['pain_level'] === null || $data['pain_level'] === '') {
            return null;
        }

        $value = $data['pain_level'];
        $numeric = is_int($value) || (is_string($value) && ctype_digit($value));

        if (! $numeric || (int) $value > 10) {
            throw new FeedbackException('invalid_pain', 'شدت درد معتبر نیست.');
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalId(array $data, string $key): ?int
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        $value = $data[$key];
        $numeric = is_int($value) || (is_string($value) && ctype_digit($value));

        if (! $numeric || (int) $value < 1) {
            throw new FeedbackException('subject_not_in_relationship', 'این موضوع به این رابطه تعلق ندارد.');
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FeedbackThread $thread, bool $sharedOnly): array
    {
        $query = $thread->messages();

        if ($sharedOnly) {
            $query->where('visibility', 'shared');
        }

        $summary = $this->summary($thread);
        $summary['messages'] = $query->get()
            ->map(fn (FeedbackMessage $message): array => $this->presentMessage($message))
            ->all();

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(FeedbackThread $thread): array
    {
        return [
            'id' => $thread->id,
            'coaching_relationship_id' => (int) $thread->coaching_relationship_id,
            'opened_by_user_id' => (int) $thread->opened_by_user_id,
            'workout_session_id' => $this->nullableInt($thread->workout_session_id),
            'workout_day_exercise_id' => $this->nullableInt($thread->workout_day_exercise_id),
            'exercise_id' => $this->nullableInt($thread->exercise_id),
            'status' => $thread->status,
            'created_at' => $thread->created_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMessage(FeedbackMessage $message): array
    {
        return [
            'id' => $message->id,
            'author_user_id' => (int) $message->author_user_id,
            'visibility' => $message->visibility,
            'body' => $message->body,
            'pain_level' => $message->pain_level === null ? null : (int) $message->pain_level,
            'created_at' => $message->created_at?->toJSON(),
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
