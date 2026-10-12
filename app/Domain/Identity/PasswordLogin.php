<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class PasswordLogin
{
    public function login(string $rawPhone, string $password): User
    {
        $phone = PhoneNumber::normalize($rawPhone);
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            throw new IdentityException('password_invalid', 'شماره یا رمز نادرست است.');
        }

        AccountStatus::assertActive($user);

        $user->last_login_at = now();
        $user->save();

        return $user;
    }
}
