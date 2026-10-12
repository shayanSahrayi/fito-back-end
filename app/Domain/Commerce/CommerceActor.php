<?php

namespace App\Domain\Commerce;

use App\Domain\Identity\AccountStatus;
use App\Models\CoachProfile;
use App\Models\User;

final class CommerceActor
{
    public function admin(User $user): User
    {
        AccountStatus::assertActive($user);

        $isAdmin = $user->platformRoles()->where('role', 'platform_admin')->exists();

        if (! $isAdmin) {
            throw new CommerceException('forbidden', 'این کار فقط برای مدیر پلتفرم است.', 403);
        }

        return $user;
    }

    public function coach(User $user): CoachProfile
    {
        AccountStatus::assertActive($user);

        $profile = $user->coachProfile;

        if ($profile === null) {
            throw new CommerceException('coach_profile_required', 'این مسیر فقط برای مربی است.', 403);
        }

        return $profile;
    }
}
