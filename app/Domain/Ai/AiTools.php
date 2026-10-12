<?php

namespace App\Domain\Ai;

use App\Models\BodyObservation;
use App\Models\BodyPhoto;
use App\Models\CoachAiSettingVersion;
use App\Models\CoachKnowledgeChunk;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\FeedbackMessage;
use App\Models\PersonalRecordEvent;
use App\Models\WorkoutSession;

final class AiTools
{
    /**
     * @return list<array{name: string, body: string}>
     */
    public function gather(CoachProfile $coach, ?CoachingRelationship $relationship, string $search): array
    {
        $tools = [
            ['name' => 'coach_method', 'body' => $this->encode($this->method($coach))],
            ['name' => 'knowledge_search', 'body' => $this->encode($this->knowledge($coach->id, $search))],
        ];

        if ($relationship === null || (int) $relationship->coach_profile_id !== $coach->id) {
            return $tools;
        }

        $tools[] = ['name' => 'relationship_summary', 'body' => $this->encode($this->summary($relationship))];
        $tools[] = ['name' => 'workout_history', 'body' => $this->encode($this->sessions($relationship))];
        $tools[] = ['name' => 'personal_records', 'body' => $this->encode($this->records($relationship))];
        $tools[] = ['name' => 'body_series', 'body' => $this->encode($this->body($relationship))];
        $tools[] = ['name' => 'shared_feedback', 'body' => $this->encode($this->feedback($relationship))];

        return $tools;
    }

    /**
     * @return array<string, mixed>
     */
    private function method(CoachProfile $coach): array
    {
        if ($coach->current_ai_setting_version_id === null) {
            return [];
        }

        $version = CoachAiSettingVersion::query()->find($coach->current_ai_setting_version_id);

        if ($version === null || (int) $version->coach_profile_id !== $coach->id) {
            return [];
        }

        return [
            'methodology' => $version->methodology,
            'preferences' => $version->preferences,
            'constraints' => $version->constraints,
        ];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    private function knowledge(int $coachId, string $search): array
    {
        $term = trim($search);

        if ($term === '') {
            return [];
        }

        $like = '%'.addcslashes(mb_substr($term, 0, 40), '%_\\').'%';

        return CoachKnowledgeChunk::query()
            ->where('coach_profile_id', $coachId)
            ->where('body', 'like', $like)
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(fn (CoachKnowledgeChunk $chunk): array => [
                'body' => $chunk->body,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(CoachingRelationship $relationship): array
    {
        $relationship->loadMissing('athleteProfile');

        return [
            'id' => $relationship->id,
            'status' => $relationship->status,
            'athlete' => $relationship->athleteProfile?->display_name,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sessions(CoachingRelationship $relationship): array
    {
        return WorkoutSession::query()
            ->where('coaching_relationship_id', $relationship->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'performed_on', 'status', 'source'])
            ->map(fn (WorkoutSession $session): array => [
                'id' => $session->id,
                'performed_on' => $session->performed_on->toDateString(),
                'status' => $session->status,
                'source' => $session->source,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(CoachingRelationship $relationship): array
    {
        return PersonalRecordEvent::query()
            ->where('coaching_relationship_id', $relationship->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'exercise_id', 'metric', 'value_int'])
            ->map(fn (PersonalRecordEvent $event): array => [
                'id' => $event->id,
                'exercise_id' => (int) $event->exercise_id,
                'metric' => $event->metric,
                'value_int' => (int) $event->value_int,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function body(CoachingRelationship $relationship): array
    {
        $observations = BodyObservation::query()
            ->where('coaching_relationship_id', $relationship->id)
            ->where('visibility', 'relationship')
            ->where('measured_at', '>=', $relationship->started_at)
            ->when($relationship->ended_at !== null, fn ($query) => $query->where('measured_at', '<=', $relationship->ended_at))
            ->with('values')
            ->orderByDesc('measured_at')
            ->limit(3)
            ->get()
            ->map(fn (BodyObservation $observation): array => [
                'id' => $observation->id,
                'measured_at' => $observation->measured_at->toJSON(),
                'metrics' => $observation->values->map(fn ($value): array => [
                    'metric_code' => $value->metric_code,
                    'value_int' => (int) $value->value_int,
                    'origin' => $value->origin,
                ])->all(),
            ])
            ->all();

        $photos = [];

        if ($relationship->status === 'active') {
            $photos = BodyPhoto::query()
                ->where('coaching_relationship_id', $relationship->id)
                ->orderByDesc('id')
                ->limit(5)
                ->pluck('id')
                ->all();
        }

        return [
            'observations' => $observations,
            'photo_ids' => $photos,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function feedback(CoachingRelationship $relationship): array
    {
        return FeedbackMessage::query()
            ->where('visibility', 'shared')
            ->whereHas('thread', fn ($query) => $query->where('coaching_relationship_id', $relationship->id))
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'body', 'pain_level'])
            ->map(fn (FeedbackMessage $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'pain_level' => $message->pain_level === null ? null : (int) $message->pain_level,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
