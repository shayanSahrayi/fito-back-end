<?php

namespace App\Domain\Coaching;

use App\Models\AthleteInvitation;
use App\Models\CoachingRelationship;

final class RelationshipView
{
    /**
     * @return array{id: int, status: string, athlete: array{display_name: string}}
     */
    public static function forCoach(CoachingRelationship $relationship): array
    {
        $relationship->loadMissing('athleteProfile');

        return [
            'id' => $relationship->id,
            'status' => $relationship->status,
            'athlete' => [
                'display_name' => $relationship->athleteProfile->display_name,
            ],
        ];
    }

    /**
     * @return array{id: int, status: string, coach: array{display_name: string}}
     */
    public static function forAthlete(CoachingRelationship $relationship): array
    {
        $relationship->loadMissing('coachProfile');

        return [
            'id' => $relationship->id,
            'status' => $relationship->status,
            'coach' => [
                'display_name' => $relationship->coachProfile->display_name,
            ],
        ];
    }

    /**
     * @return array{id: int, status: string, phone: string, expires_at: string}
     */
    public static function invitation(AthleteInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'status' => $invitation->status,
            'phone' => $invitation->phone,
            'expires_at' => $invitation->expires_at->toJSON(),
        ];
    }
}
