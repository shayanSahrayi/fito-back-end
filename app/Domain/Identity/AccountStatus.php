<?php

namespace App\Domain\Identity;

use App\Models\User;

final class AccountStatus
{
    public static function assertActive(User $user): void
    {
        if ($user->status !== 'active') {
            throw new IdentityException('account_suspended', 'این حساب تعلیق شده است.', 403);
        }
    }
}
