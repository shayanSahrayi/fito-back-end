<?php

namespace App\Domain\Feedback;

use App\Domain\Identity\AccountStatus;
use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\Notification;
use App\Models\PersonalRecordEvent;
use App\Models\User;
use App\Models\WorkoutSession;

final class Notifier
{
    /**
     * @var list<string>
     */
    public const TYPES = [
        'program_assigned',
        'record_achieved',
        'body_observation_recorded',
        'feedback_replied',
    ];

    public function programAssigned(int $athleteUserId, string $subjectType, int $subjectId, bool $meal): void
    {
        $this->send(
            $athleteUserId,
            'program_assigned',
            'برنامه جدید',
            $meal ? 'مربی یک برنامه غذا برایت گذاشت.' : 'مربی یک برنامه تمرین برایت گذاشت.',
            $subjectType,
            $subjectId,
        );
    }

    public function recordAchieved(WorkoutSession $session, PersonalRecordEvent $event): void
    {
        $athleteUserId = AthleteProfile::query()->whereKey($session->athlete_profile_id)->value('user_id');

        if ($athleteUserId !== null) {
            $this->send(
                (int) $athleteUserId,
                'record_achieved',
                'رکورد جدید',
                'یک رکورد تازه ثبت شد.',
                'personal_record_event',
                (int) $event->id,
            );
        }

        if ($session->coaching_relationship_id === null) {
            return;
        }

        $relationship = CoachingRelationship::query()->with('coachProfile')->find($session->coaching_relationship_id);
        $coachUserId = $relationship?->coachProfile?->user_id;

        if ($coachUserId === null || ($athleteUserId !== null && (int) $coachUserId === (int) $athleteUserId)) {
            return;
        }

        $this->send(
            (int) $coachUserId,
            'record_achieved',
            'رکورد جدید',
            'یک رکورد تازه ثبت شد.',
            'personal_record_event',
            (int) $event->id,
        );
    }

    public function bodyRecorded(int $recipientUserId, int $observationId): void
    {
        $this->send(
            $recipientUserId,
            'body_observation_recorded',
            'آنالیز بدن',
            'یک مشاهده بدن جدید ثبت شد.',
            'body_observation',
            $observationId,
        );
    }

    public function feedbackReplied(int $recipientUserId, int $messageId): void
    {
        $this->send(
            $recipientUserId,
            'feedback_replied',
            'پیام بازخورد',
            'یک پیام جدید در بازخورد ثبت شد.',
            'feedback_message',
            $messageId,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inbox(User $user): array
    {
        AccountStatus::assertActive($user);

        return Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Notification $notification): array => $this->present($notification))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function markRead(User $user, Notification $notification): array
    {
        AccountStatus::assertActive($user);

        if ((int) $notification->user_id !== $user->id) {
            throw new FeedbackException('notification_not_found', 'اعلان پیدا نشد.', 404);
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return $this->present($notification);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'subject_type' => $notification->subject_type,
            'subject_id' => $notification->subject_id === null ? null : (int) $notification->subject_id,
            'read_at' => $notification->read_at?->toJSON(),
            'created_at' => $notification->created_at?->toJSON(),
        ];
    }

    private function send(
        int $userId,
        string $type,
        string $title,
        string $body,
        ?string $subjectType,
        ?int $subjectId,
    ): void {
        if (! in_array($type, self::TYPES, true)) {
            throw new FeedbackException('invalid_notification', 'نوع اعلان معتبر نیست.');
        }

        Notification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'read_at' => null,
        ]);
    }
}
