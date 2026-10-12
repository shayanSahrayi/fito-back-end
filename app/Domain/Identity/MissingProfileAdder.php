<?php

namespace App\Domain\Identity;

use App\Models\AthleteProfile;
use App\Models\CoachProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class MissingProfileAdder
{
    /**
     * @return array{panel: string, profile_id: int}
     */
    public function add(User $user, string $panel, string $displayName): array
    {
        AccountStatus::assertActive($user);
        $displayName = $this->displayName($displayName);
        $panel = $this->panel($panel);

        try {
            return DB::transaction(function () use ($user, $panel, $displayName): array {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                AccountStatus::assertActive($locked);

                if ($this->existing($locked, $panel) !== null) {
                    throw new IdentityException('profile_exists', 'این پنل قبلاً ساخته شده است.');
                }

                $profile = $this->create($locked, $panel, $displayName);

                return [
                    'panel' => $panel,
                    'profile_id' => $profile->id,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            throw new IdentityException('profile_exists', 'این پنل قبلاً ساخته شده است.');
        }
    }

    private function displayName(string $displayName): string
    {
        $displayName = trim($displayName);

        if ($displayName === '' || mb_strlen($displayName) > 120) {
            throw new IdentityException('invalid_display_name', 'نام نمایشی معتبر نیست.');
        }

        return $displayName;
    }

    private function panel(string $panel): string
    {
        if (! in_array($panel, ['coach', 'athlete'], true)) {
            throw new IdentityException('invalid_panel', 'پنل فقط مربی یا ورزشکار است.');
        }

        return $panel;
    }

    private function existing(User $user, string $panel): CoachProfile|AthleteProfile|null
    {
        return $panel === 'coach' ? $user->coachProfile : $user->athleteProfile;
    }

    private function create(User $user, string $panel, string $displayName): Model
    {
        $attributes = [
            'user_id' => $user->id,
            'display_name' => $displayName,
            'status' => 'active',
        ];

        return $panel === 'coach'
            ? CoachProfile::query()->create($attributes)
            : AthleteProfile::query()->create($attributes);
    }
}
