<?php

namespace App\Domain\Coaching;

use App\Domain\Commerce\CommerceException;
use App\Domain\Identity\AccountStatus;
use App\Models\AthleteInvitation;
use App\Models\CoachProfile;
use App\Models\User;

final class InvitationActions
{
    public function __construct(private readonly InvitationClock $clock) {}

    /**
     * @return array{id: int, status: string}
     */
    public function revoke(CoachProfile $coach, AthleteInvitation $invitation): array
    {
        if ($invitation->coach_profile_id !== $coach->id) {
            throw new CommerceException('invitation_not_found', 'دعوت پیدا نشد.', 404);
        }

        return $this->close($invitation, 'revoked');
    }

    /**
     * @return array{id: int, status: string}
     */
    public function decline(User $athlete, AthleteInvitation $invitation): array
    {
        AccountStatus::assertActive($athlete);

        if ($invitation->phone !== $athlete->phone) {
            throw new CommerceException('invitation_not_found', 'دعوت پیدا نشد.', 404);
        }

        return $this->close($invitation, 'declined');
    }

    /**
     * @return array{id: int, status: string}
     */
    private function close(AthleteInvitation $invitation, string $status): array
    {
        $this->clock->expireIfDue($invitation);

        if ($invitation->status !== 'pending') {
            throw new CommerceException('invitation_not_pending', 'این دعوت دیگر باز نیست.');
        }

        $invitation->forceFill([
            'status' => $status,
            'pending_key' => null,
            'revoked_at' => $status === 'revoked' ? now() : null,
        ])->save();

        return [
            'id' => $invitation->id,
            'status' => $invitation->status,
        ];
    }
}
