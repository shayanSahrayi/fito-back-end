<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class PasswordChanger
{
    public function change(User $user, string $currentPassword, string $password): void
    {
        AccountStatus::assertActive($user);

        if ($user->password === null || ! Hash::check($currentPassword, $user->password)) {
            throw new IdentityException('password_invalid', 'رمز فعلی نادرست است.');
        }

        if (strlen($password) < 8 || strlen($password) > 255) {
            throw new IdentityException('invalid_password', 'رمز جدید معتبر نیست.');
        }

        $user->password = $password;
        $user->password_is_initial = false;
        $user->save();
    }
}
