<?php

namespace App\Domain\Coaching;

use App\Models\AthleteInvitation;

final class InvitationClock
{
    public function expireIfDue(AthleteInvitation $invitation): void
    {
        if ($invitation->status === 'pending' && $invitation->expires_at->lte(now())) {
            $invitation->forceFill([
                'status' => 'expired',
                'pending_key' => null,
            ])->save();
        }
    }
}
