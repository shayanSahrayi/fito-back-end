<?php

namespace App\Domain\Coaching;

use App\Domain\Identity\AccountStatus;
use App\Models\AthleteInvitation;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\User;

final class RelationshipDirectory
{
    public function __construct(private readonly InvitationClock $clock) {}

    /**
     * @return array{relationships: list<array<string, mixed>>}
     */
    public function forCoach(CoachProfile $coach): array
    {
        $rows = CoachingRelationship::query()
            ->where('coach_profile_id', $coach->id)
            ->orderByDesc('id')
            ->get();

        return [
            'relationships' => $rows->map(fn (CoachingRelationship $row): array => RelationshipView::forCoach($row))->all(),
        ];
    }

    /**
     * @return array{relationships: list<array<string, mixed>>}
     */
    public function forAthlete(User $user): array
    {
        AccountStatus::assertActive($user);
        $profile = $user->athleteProfile;

        if ($profile === null) {
            return ['relationships' => []];
        }

        $rows = CoachingRelationship::query()
            ->where('athlete_profile_id', $profile->id)
            ->orderByDesc('id')
            ->get();

        return [
            'relationships' => $rows->map(fn (CoachingRelationship $row): array => RelationshipView::forAthlete($row))->all(),
        ];
    }

    /**
     * @return array{invitations: list<array<string, mixed>>}
     */
    public function invitations(User $user): array
    {
        AccountStatus::assertActive($user);

        $rows = AthleteInvitation::query()
            ->with('coachProfile')
            ->where('phone', $user->phone)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();

        $open = [];

        foreach ($rows as $invitation) {
            $this->clock->expireIfDue($invitation);

            if ($invitation->status !== 'pending') {
                continue;
            }

            $open[] = [
                'id' => $invitation->id,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at->toJSON(),
                'coach' => [
                    'display_name' => $invitation->coachProfile->display_name,
                ],
            ];
        }

        return ['invitations' => $open];
    }
}
