<?php

namespace App\Domain\Coaching;

use App\Domain\Commerce\CommerceException;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\CoachingRelationshipEvent;
use App\Models\User;

final class RelationshipEnder
{
    /**
     * @return array{id: int, status: string}
     */
    public function end(User $coachUser, CoachProfile $coach, CoachingRelationship $relationship, ?string $reason): array
    {
        if ($relationship->coach_profile_id !== $coach->id) {
            throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
        }

        if ($relationship->status !== 'active') {
            throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
        }

        $reason = $reason === null ? null : trim($reason);

        if ($reason === '') {
            $reason = null;
        }

        if ($reason !== null && mb_strlen($reason) > 160) {
            throw new CommerceException('invalid_end_reason', 'دلیل پایان رابطه معتبر نیست.');
        }

        $relationship->forceFill([
            'status' => 'ended',
            'ended_at' => now(),
            'ended_by_user_id' => $coachUser->id,
            'end_reason' => $reason,
        ])->save();

        CoachingRelationshipEvent::query()->create([
            'coaching_relationship_id' => $relationship->id,
            'event_type' => 'ended',
            'actor_user_id' => $coachUser->id,
            'happened_at' => now(),
        ]);

        return [
            'id' => $relationship->id,
            'status' => $relationship->status,
        ];
    }
}
