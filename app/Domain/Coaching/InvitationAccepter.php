<?php

namespace App\Domain\Coaching;

use App\Domain\Commerce\CommerceException;
use App\Domain\Identity\AccountStatus;
use App\Domain\Identity\OtpVerifier;
use App\Domain\Identity\PhoneNumber;
use App\Models\AccessPeriod;
use App\Models\AthleteInvitation;
use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\CoachingRelationshipEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class InvitationAccepter
{
    public function __construct(
        private readonly OtpVerifier $verifier,
        private readonly CapacityGuard $capacity,
        private readonly InvitationClock $clock,
    ) {}

    /**
     * @return array{invitation: array<string, mixed>, relationship: array<string, mixed>}
     */
    public function accept(string $rawPhone, string $code, int $invitationId): array
    {
        $phone = PhoneNumber::normalize($rawPhone);
        $challenge = $this->verifier->matchingOpen($phone, 'invitation', $code, $invitationId);

        $result = DB::transaction(function () use ($phone, $invitationId): array {
            $invitation = AthleteInvitation::query()->whereKey($invitationId)->lockForUpdate()->first();

            if ($invitation === null || $invitation->phone !== $phone) {
                throw new CommerceException('invitation_not_found', 'دعوت پیدا نشد.', 404);
            }

            $this->clock->expireIfDue($invitation);

            $athlete = User::query()->where('phone', $phone)->first();

            if ($athlete === null) {
                throw new CommerceException('invitation_not_found', 'دعوت پیدا نشد.', 404);
            }

            AccountStatus::assertActive($athlete);

            if ($invitation->status === 'accepted' && $invitation->relationship !== null) {
                return [
                    'invitation' => RelationshipView::invitation($invitation),
                    'relationship' => RelationshipView::forAthlete($invitation->relationship),
                ];
            }

            if ($invitation->status !== 'pending') {
                throw new CommerceException('invitation_not_pending', 'این دعوت دیگر باز نیست.');
            }

            $profile = $athlete->athleteProfile;

            if (! $profile instanceof AthleteProfile) {
                throw new CommerceException('athlete_profile_required', 'پروفایل ورزشکار هنوز ساخته نشده است.', 422);
            }

            $period = AccessPeriod::query()
                ->where('active_coach_id', $invitation->coach_profile_id)
                ->lockForUpdate()
                ->first();

            if ($period === null || $period->status !== 'active' || $period->ends_at->lte(now())) {
                throw new CommerceException('no_access', 'دوره دسترسی فعالی نیست.', 403);
            }

            $relationship = CoachingRelationship::query()
                ->where('coach_profile_id', $invitation->coach_profile_id)
                ->where('athlete_profile_id', $profile->id)
                ->lockForUpdate()
                ->first();

            if ($relationship?->status !== 'active') {
                $this->capacity->assertOpen($period, $invitation->coachProfile);
            }

            if ($relationship === null) {
                $relationship = CoachingRelationship::query()->create([
                    'coach_profile_id' => $invitation->coach_profile_id,
                    'athlete_profile_id' => $profile->id,
                    'status' => 'active',
                    'started_at' => now(),
                ]);
                $this->event($relationship, 'started', $athlete);
            } elseif ($relationship->status === 'ended') {
                $relationship->forceFill([
                    'status' => 'active',
                    'ended_at' => null,
                    'ended_by_user_id' => null,
                    'end_reason' => null,
                ])->save();
                $this->event($relationship, 'reactivated', $athlete);
            }

            $invitation->forceFill([
                'status' => 'accepted',
                'pending_key' => null,
                'accepted_at' => now(),
                'accepted_by_user_id' => $athlete->id,
                'coaching_relationship_id' => $relationship->id,
            ])->save();

            return [
                'invitation' => RelationshipView::invitation($invitation),
                'relationship' => RelationshipView::forAthlete($relationship),
            ];
        });

        $this->verifier->consume($challenge);

        return $result;
    }

    private function event(CoachingRelationship $relationship, string $type, User $actor): void
    {
        CoachingRelationshipEvent::query()->create([
            'coaching_relationship_id' => $relationship->id,
            'event_type' => $type,
            'actor_user_id' => $actor->id,
            'happened_at' => now(),
        ]);
    }
}
