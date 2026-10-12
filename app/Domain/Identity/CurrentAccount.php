<?php

namespace App\Domain\Identity;

use App\Models\AthleteProfile;
use App\Models\CoachProfile;
use App\Models\User;

final class CurrentAccount
{
    /**
     * @return array{
     *     user_id: int,
     *     phone: string,
     *     national_code: string,
     *     status: string,
     *     password_is_initial: bool,
     *     profiles: array{
     *         coach: array{id: int, display_name: string, status: string}|null,
     *         athlete: array{id: int, display_name: string, status: string}|null
     *     },
     *     roles: list<string>
     * }
     */
    public function describe(User $user): array
    {
        AccountStatus::assertActive($user);

        $user->load(['coachProfile', 'athleteProfile', 'platformRoles']);

        return [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'national_code' => $user->national_code,
            'status' => $user->status,
            'password_is_initial' => $user->password_is_initial,
            'profiles' => [
                'coach' => $this->profile($user->coachProfile),
                'athlete' => $this->profile($user->athleteProfile),
            ],
            'roles' => $user->platformRoles->pluck('role')->values()->all(),
        ];
    }

    /**
     * @return array{id: int, display_name: string, status: string}|null
     */
    private function profile(CoachProfile|AthleteProfile|null $profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        return [
            'id' => $profile->id,
            'display_name' => $profile->display_name,
            'status' => $profile->status,
        ];
    }
}
