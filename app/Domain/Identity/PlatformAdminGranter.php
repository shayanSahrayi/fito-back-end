<?php

namespace App\Domain\Identity;

use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

final class PlatformAdminGranter
{
    /**
     * @return array{user_id: int, created: bool}
     */
    public function grant(string $rawPhone): array
    {
        $phone = PhoneNumber::normalize($rawPhone);
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            throw new IdentityException('account_not_found', 'حسابی با این شماره نیست.', 404);
        }

        AccountStatus::assertActive($user);

        $exists = PlatformRole::query()
            ->where('user_id', $user->id)
            ->where('role', 'platform_admin')
            ->exists();

        if ($exists) {
            return [
                'user_id' => $user->id,
                'created' => false,
            ];
        }

        try {
            PlatformRole::query()->create([
                'user_id' => $user->id,
                'role' => 'platform_admin',
                'granted_by_user_id' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return [
                'user_id' => $user->id,
                'created' => false,
            ];
        }

        return [
            'user_id' => $user->id,
            'created' => true,
        ];
    }
}
