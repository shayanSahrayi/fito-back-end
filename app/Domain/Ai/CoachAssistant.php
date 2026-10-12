<?php

namespace App\Domain\Ai;

use App\Domain\Commerce\CommerceException;
use App\Domain\Identity\AccountStatus;
use App\Domain\Nutrition\MealTree;
use App\Models\AiConversation;
use App\Models\AiGeneration;
use App\Models\AiMessage;
use App\Models\AthleteProfile;
use App\Models\CoachAiSettingVersion;
use App\Models\CoachAiWorkspace;
use App\Models\CoachKnowledgeChunk;
use App\Models\CoachKnowledgeDocument;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\Exercise;
use App\Models\Food;
use App\Models\MealPlan;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutDayExercise;
use App\Models\WorkoutProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CoachAssistant
{
    public function __construct(
        private readonly AiQuota $quota,
        private readonly AiTools $tools,
        private readonly LanguageModel $model,
        private readonly MealTree $meals,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveSettings(User $user, CoachProfile $coach, array $data): array
    {
        AccountStatus::assertActive($user);

        return DB::transaction(function () use ($user, $coach, $data): array {
            $locked = CoachProfile::query()->whereKey($coach->id)->lockForUpdate()->first();
            CoachAiWorkspace::query()->firstOrCreate(['coach_profile_id' => $locked->id]);
            $max = CoachAiSettingVersion::query()->where('coach_profile_id', $locked->id)->max('version_number');
            $version = CoachAiSettingVersion::query()->create([
                'coach_profile_id' => $locked->id,
                'version_number' => ($max === null ? 0 : (int) $max) + 1,
                'methodology' => $this->text($data['methodology'] ?? null, 'روش کار معتبر نیست.'),
                'preferences' => $this->text($data['preferences'] ?? null, 'ترجیح‌ها معتبر نیستند.'),
                'constraints' => $this->text($data['constraints'] ?? null, 'محدودیت‌ها معتبر نیستند.'),
            ]);
            $locked->current_ai_setting_version_id = $version->id;

            if ($locked->ai_onboarding_completed_at === null) {
                $locked->ai_onboarding_completed_at = now();
            }

            $locked->save();

            return [
                'id' => $version->id,
                'version_number' => (int) $version->version_number,
                'methodology' => $version->methodology,
                'preferences' => $version->preferences,
                'constraints' => $version->constraints,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function storeKnowledge(User $user, CoachProfile $coach, string $title, string $contents): array
    {
        AccountStatus::assertActive($user);
        $title = trim($title);
        $contents = trim($contents);

        if ($title === '' || mb_strlen($title) > 160 || $contents === '') {
            throw new AiException('invalid_document', 'سند دانش معتبر نیست.');
        }

        $hash = hash('sha256', $contents);

        return DB::transaction(function () use ($user, $coach, $title, $contents, $hash): array {
            $exists = CoachKnowledgeDocument::query()
                ->where('coach_profile_id', $coach->id)
                ->where('sha256', $hash)
                ->exists();

            if ($exists) {
                throw new AiException('knowledge_duplicate', 'این سند قبلاً ذخیره شده است.');
            }

            $path = 'coach-knowledge/'.$coach->id.'/'.Str::uuid()->toString().'.txt';
            Storage::disk('local')->put($path, $contents);
            $document = CoachKnowledgeDocument::query()->create([
                'coach_profile_id' => $coach->id,
                'title' => $title,
                'storage_path' => $path,
                'sha256' => $hash,
                'status' => 'ready',
                'created_by_user_id' => $user->id,
            ]);
            $parts = mb_str_split($contents, 400);
            if ($parts === []) {
                $parts = [$contents];
            }

            foreach (array_values($parts) as $index => $part) {
                CoachKnowledgeChunk::query()->create([
                    'coach_knowledge_document_id' => $document->id,
                    'coach_profile_id' => $coach->id,
                    'chunk_index' => $index,
                    'body' => $part,
                ]);
            }

            return [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function coachTurn(User $user, CoachProfile $coach, array $data, ?AiConversation $conversation): array
    {
        AccountStatus::assertActive($user);

        return DB::transaction(function () use ($user, $coach, $data, $conversation): array {
            $locked = $this->readyCoach($coach);
            $relationship = $this->optionalRelationship($locked, $data['coaching_relationship_id'] ?? null);
            $this->quota->consume(
                $locked,
                $user,
                'ai_coach_assistant',
                'conversation',
                $relationship?->athlete_profile_id === null ? null : (int) $relationship->athlete_profile_id,
                $relationship?->id,
                null,
            );
            $thread = $conversation === null
                ? $this->newConversation($locked, $user, 'coach', $relationship)
                : $this->ownConversation($locked, $conversation);

            return $this->turn($locked, $thread, (string) ($data['body'] ?? ''), $relationship);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function athleteTurn(User $user, array $data): array
    {
        $athlete = $this->athlete($user);

        return DB::transaction(function () use ($user, $athlete, $data): array {
            $relationship = CoachingRelationship::query()->whereKey((int) ($data['coaching_relationship_id'] ?? 0))->first();

            if ($relationship === null || (int) $relationship->athlete_profile_id !== $athlete->id) {
                throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
            }

            if ($relationship->status !== 'active') {
                throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
            }

            $coach = CoachProfile::query()->find($relationship->coach_profile_id);
            $locked = $this->readyCoach($coach);
            $this->quota->consume($locked, $user, 'ai_athlete_access', 'conversation', $athlete->id, $relationship->id, null);
            $thread = $this->newConversation($locked, $user, 'athlete', $relationship);

            return $this->turn($locked, $thread, (string) ($data['body'] ?? ''), $relationship);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function draft(User $user, CoachProfile $coach, array $data): array
    {
        AccountStatus::assertActive($user);

        return DB::transaction(function () use ($user, $coach, $data): array {
            $locked = $this->readyCoach($coach);
            $kind = (string) ($data['kind'] ?? '');

            if (! in_array($kind, ['workout_program', 'meal_plan', 'progress_review'], true)) {
                throw new AiException('invalid_kind', 'نوع پیشنهاد معتبر نیست.');
            }

            $relationship = $this->requiredRelationship($locked, (int) ($data['coaching_relationship_id'] ?? 0));
            $context = ['prompt' => (string) ($data['body'] ?? '')];

            if ($kind === 'workout_program') {
                $context['exercise_id'] = $this->exerciseId($locked);
            }

            if ($kind === 'meal_plan') {
                $context['food_id'] = $this->foodId($locked);
            }

            $this->quota->consume($locked, $user, 'ai_coach_assistant', 'generation', (int) $relationship->athlete_profile_id, $relationship->id, null);
            $proposal = json_decode($this->model->reply($kind, $context), true);

            if (! is_array($proposal)) {
                throw new AiException('invalid_proposal', 'خروجی مدل JSON معتبر نیست.');
            }

            $generation = AiGeneration::query()->create([
                'coach_profile_id' => $locked->id,
                'coaching_relationship_id' => $relationship->id,
                'athlete_profile_id' => $relationship->athlete_profile_id,
                'ai_conversation_id' => null,
                'coach_ai_setting_version_id' => $locked->current_ai_setting_version_id,
                'kind' => $kind,
                'status' => 'draft',
                'proposal' => $proposal,
                'model_name' => $this->model->name(),
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'workout_program_version_id' => null,
                'meal_plan_version_id' => null,
            ]);

            return $this->presentGeneration($generation);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function approve(User $user, CoachProfile $coach, AiGeneration $generation): array
    {
        AccountStatus::assertActive($user);

        return DB::transaction(function () use ($user, $coach, $generation): array {
            $locked = $this->ownedGeneration($coach, $generation);

            if ($locked->status !== 'draft') {
                throw new AiException('generation_locked', 'این پیشنهاد دیگر پیش‌نویس نیست.');
            }

            if ($locked->kind === 'workout_program') {
                $locked->workout_program_version_id = $this->materializeWorkout($coach, $locked->proposal);
            }

            if ($locked->kind === 'meal_plan') {
                $locked->meal_plan_version_id = $this->materializeMeal($user, $coach, $locked->proposal);
            }

            $locked->status = 'materialized';
            $locked->reviewed_by_user_id = $user->id;
            $locked->reviewed_at = now();
            $locked->save();

            return $this->presentGeneration($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(User $user, CoachProfile $coach, AiGeneration $generation): array
    {
        AccountStatus::assertActive($user);

        return DB::transaction(function () use ($user, $coach, $generation): array {
            $locked = $this->ownedGeneration($coach, $generation);

            if ($locked->status === 'materialized') {
                throw new AiException('generation_locked', 'پیشنهاد تأییدشده رد نمی‌شود.');
            }

            if ($locked->status !== 'rejected') {
                $locked->status = 'rejected';
                $locked->reviewed_by_user_id = $user->id;
                $locked->reviewed_at = now();
                $locked->save();
            }

            return $this->presentGeneration($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function show(CoachProfile $coach, AiConversation $conversation): array
    {
        return $this->presentConversation($this->ownConversation($coach, $conversation));
    }

    /**
     * @return array<string, mixed>
     */
    private function turn(CoachProfile $coach, AiConversation $conversation, string $body, ?CoachingRelationship $relationship): array
    {
        $text = trim($body);

        if ($text === '' || mb_strlen($text) > 4000) {
            throw new AiException('invalid_message', 'متن پیام معتبر نیست.');
        }

        $this->append($conversation, 'user', $text, null);

        foreach ($this->tools->gather($coach, $relationship, $text) as $tool) {
            $this->append($conversation, 'tool', $tool['body'], $tool['name']);
        }

        $reply = $this->model->reply('chat', ['prompt' => $text]);
        $this->append($conversation, 'assistant', $reply, null);

        return $this->presentConversation($conversation);
    }

    private function append(AiConversation $conversation, string $role, string $body, ?string $tool): void
    {
        $max = AiMessage::query()->where('ai_conversation_id', $conversation->id)->max('sequence_no');
        $conversation->messages()->create([
            'sequence_no' => ($max === null ? 0 : (int) $max) + 1,
            'role' => $role,
            'body' => $body,
            'tool_name' => $tool,
        ]);
    }

    private function readyCoach(?CoachProfile $coach): CoachProfile
    {
        if ($coach === null) {
            throw new AiException('coach_not_found', 'مربی پیدا نشد.', 404);
        }

        $locked = CoachProfile::query()->whereKey($coach->id)->lockForUpdate()->first();

        if ($locked === null || $locked->current_ai_setting_version_id === null || $locked->ai_onboarding_completed_at === null) {
            throw new AiException('onboarding_required', 'اول روش کار دستیار را ثبت کن.');
        }

        return $locked;
    }

    private function newConversation(CoachProfile $coach, User $actor, string $audience, ?CoachingRelationship $relationship): AiConversation
    {
        return AiConversation::query()->create([
            'coach_profile_id' => $coach->id,
            'coaching_relationship_id' => $relationship?->id,
            'athlete_profile_id' => $relationship?->athlete_profile_id,
            'actor_user_id' => $actor->id,
            'audience' => $audience,
            'coach_ai_setting_version_id' => $coach->current_ai_setting_version_id,
        ]);
    }

    private function ownConversation(CoachProfile $coach, AiConversation $conversation): AiConversation
    {
        if ((int) $conversation->coach_profile_id !== $coach->id) {
            throw new AiException('conversation_not_found', 'گفتگو پیدا نشد.', 404);
        }

        return $conversation;
    }

    private function optionalRelationship(CoachProfile $coach, mixed $id): ?CoachingRelationship
    {
        if ($id === null || $id === '') {
            return null;
        }

        return $this->requiredRelationship($coach, (int) $id);
    }

    private function requiredRelationship(CoachProfile $coach, int $id): CoachingRelationship
    {
        $relationship = CoachingRelationship::query()->whereKey($id)->first();

        if ($relationship === null || (int) $relationship->coach_profile_id !== $coach->id) {
            throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
        }

        if ($relationship->status !== 'active') {
            throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
        }

        return $relationship;
    }

    private function exerciseId(CoachProfile $coach): int
    {
        $exercise = Exercise::query()
            ->where(function ($query) use ($coach): void {
                $query->where(fn ($inner) => $inner->whereNull('coach_profile_id')->where('status', 'active'))
                    ->orWhere('coach_profile_id', $coach->id);
            })
            ->where('tracking_type', 'weight_reps')
            ->orderBy('id')
            ->first();

        if ($exercise === null) {
            throw new AiException('exercise_not_available', 'حرکت قابل استفاده برای پیشنهاد پیدا نشد.');
        }

        return $exercise->id;
    }

    private function foodId(CoachProfile $coach): int
    {
        $food = Food::query()
            ->where('status', 'active')
            ->whereNotNull('current_revision_id')
            ->where(function ($query) use ($coach): void {
                $query->whereNull('coach_profile_id')->orWhere('coach_profile_id', $coach->id);
            })
            ->whereHas('currentRevision', fn ($query) => $query->where('basis', 'per_100g'))
            ->orderBy('id')
            ->first();

        if ($food === null) {
            throw new AiException('food_not_available', 'غذای قابل استفاده برای پیشنهاد پیدا نشد.');
        }

        return $food->id;
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    private function materializeWorkout(CoachProfile $coach, array $proposal): int
    {
        $program = WorkoutProgram::query()->create([
            'coach_profile_id' => $coach->id,
            'title' => $this->short($proposal['title'] ?? null, 'عنوان برنامه معتبر نیست.'),
            'status' => 'published',
            'current_version_id' => null,
        ]);
        $version = $program->versions()->create([
            'version_number' => 1,
            'state' => 'published',
            'note' => null,
            'published_at' => now(),
            'created_by_user_id' => $coach->user_id,
        ]);

        foreach ($proposal['days'] ?? [] as $day) {
            $stored = WorkoutDay::query()->create([
                'workout_program_version_id' => $version->id,
                'position' => (int) ($day['position'] ?? 0),
                'title' => $this->short($day['title'] ?? null, 'عنوان روز معتبر نیست.', 120),
                'weekday' => $day['weekday'] ?? null,
            ]);

            foreach ($day['exercises'] ?? [] as $item) {
                $exercise = Exercise::query()->find((int) ($item['exercise_id'] ?? 0));
                $visible = $exercise !== null && (
                    ($exercise->coach_profile_id === null && $exercise->status === 'active')
                    || (int) $exercise->coach_profile_id === $coach->id
                );

                if (! $visible) {
                    throw new AiException('exercise_not_available', 'این حرکت برای پیشنهاد در دسترس نیست.');
                }

                WorkoutDayExercise::query()->create([
                    'workout_day_id' => $stored->id,
                    'exercise_id' => $exercise->id,
                    'position' => (int) ($item['position'] ?? 0),
                    'tracking_type' => $exercise->tracking_type,
                    'prescribed_sets' => $item['prescribed_sets'] ?? null,
                    'reps_min' => $item['reps_min'] ?? null,
                    'reps_max' => $item['reps_max'] ?? null,
                ]);
            }
        }

        $program->current_version_id = $version->id;
        $program->save();

        return $version->id;
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    private function materializeMeal(User $user, CoachProfile $coach, array $proposal): int
    {
        $plan = MealPlan::query()->create([
            'coach_profile_id' => $coach->id,
            'title' => $this->short($proposal['title'] ?? null, 'عنوان برنامه معتبر نیست.'),
            'status' => 'draft',
            'current_version_id' => null,
        ]);
        $version = $plan->versions()->create([
            'version_number' => 1,
            'state' => 'draft',
            'published_at' => null,
            'created_by_user_id' => $user->id,
        ]);
        $this->meals->replace($version, $proposal['days'] ?? [], $coach);
        $version->forceFill([
            'state' => 'published',
            'published_at' => now(),
        ])->save();
        $plan->forceFill([
            'status' => 'published',
            'current_version_id' => $version->id,
        ])->save();

        return $version->id;
    }

    private function ownedGeneration(CoachProfile $coach, AiGeneration $generation): AiGeneration
    {
        $locked = AiGeneration::query()->whereKey($generation->id)->lockForUpdate()->first();

        if ($locked === null || (int) $locked->coach_profile_id !== $coach->id) {
            throw new AiException('generation_not_found', 'پیشنهاد پیدا نشد.', 404);
        }

        return $locked;
    }

    private function athlete(User $user): AthleteProfile
    {
        AccountStatus::assertActive($user);
        $athlete = $user->athleteProfile;

        if ($athlete === null) {
            throw new AiException('athlete_profile_required', 'این مسیر فقط برای ورزشکار است.', 403);
        }

        return $athlete;
    }

    private function text(mixed $value, string $message): string
    {
        $text = trim((string) $value);

        if ($text === '' || mb_strlen($text) > 4000) {
            throw new AiException('invalid_settings', $message);
        }

        return $text;
    }

    private function short(mixed $value, string $message, int $max = 160): string
    {
        $text = trim((string) $value);

        if ($text === '' || mb_strlen($text) > $max) {
            throw new AiException('invalid_proposal', $message);
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentConversation(AiConversation $conversation): array
    {
        $conversation->load('messages');

        return [
            'id' => $conversation->id,
            'audience' => $conversation->audience,
            'coaching_relationship_id' => $conversation->coaching_relationship_id === null ? null : (int) $conversation->coaching_relationship_id,
            'messages' => $conversation->messages->map(fn (AiMessage $message): array => [
                'id' => $message->id,
                'sequence_no' => (int) $message->sequence_no,
                'role' => $message->role,
                'body' => $message->body,
                'tool_name' => $message->tool_name,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentGeneration(AiGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'kind' => $generation->kind,
            'status' => $generation->status,
            'proposal' => $generation->proposal,
            'model_name' => $generation->model_name,
            'workout_program_version_id' => $generation->workout_program_version_id === null ? null : (int) $generation->workout_program_version_id,
            'meal_plan_version_id' => $generation->meal_plan_version_id === null ? null : (int) $generation->meal_plan_version_id,
        ];
    }
}
