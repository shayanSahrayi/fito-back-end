<?php

namespace App\Domain\Identity;

use App\Models\AthleteProfile;
use App\Models\CoachProfile;
use App\Models\SignupDraft;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class SignupCompleter
{
    /**
     * @return array{user_id: int, panel: string, profile_id: int}
     */
    public function complete(string $rawPhone, string $rawNationalCode, string $displayName, string $panel): array
    {
        $phone = PhoneNumber::normalize($rawPhone);
        $nationalCode = NationalCode::parse($rawNationalCode);
        $displayName = trim($displayName);

        if ($displayName === '' || mb_strlen($displayName) > 120) {
            throw new IdentityException('invalid_display_name', 'نام نمایشی معتبر نیست.');
        }

        if (! in_array($panel, ['coach', 'athlete'], true)) {
            throw new IdentityException('invalid_panel', 'پنل فقط مربی یا ورزشکار است.');
        }

        try {
            return DB::transaction(function () use ($phone, $nationalCode, $displayName, $panel): array {
                $draft = SignupDraft::query()->where('open_phone', $phone)->lockForUpdate()->first();

                if ($draft === null || $draft->phone_verified_at === null) {
                    throw new IdentityException('draft_not_verified', 'شماره هنوز تأیید نشده است.');
                }

                if ($draft->expires_at->lte(now())) {
                    throw new IdentityException('draft_expired', 'پیش‌نویس ثبت‌نام منقضی شده است.');
                }

                if (User::query()->where('phone', $phone)->exists()) {
                    throw new IdentityException('phone_taken', 'این شماره قبلاً ثبت شده است.');
                }

                if (User::query()->where('national_code', $nationalCode)->exists()) {
                    throw new IdentityException('national_code_taken', 'این کد ملی قبلاً ثبت شده است.');
                }

                $user = User::query()->create([
                    'phone' => $phone,
                    'national_code' => $nationalCode,
                    'password' => null,
                    'password_is_initial' => false,
                    'status' => 'active',
                ]);

                $profile = $panel === 'coach'
                    ? CoachProfile::query()->create([
                        'user_id' => $user->id,
                        'display_name' => $displayName,
                        'status' => 'active',
                    ])
                    : AthleteProfile::query()->create([
                        'user_id' => $user->id,
                        'display_name' => $displayName,
                        'status' => 'active',
                    ]);

                $draft->forceFill([
                    'national_code' => $nationalCode,
                    'display_name' => $displayName,
                    'panel' => $panel,
                    'open_phone' => null,
                    'consumed_at' => now(),
                    'created_user_id' => $user->id,
                ])->save();

                return [
                    'user_id' => $user->id,
                    'panel' => $panel,
                    'profile_id' => $profile->id,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            throw new IdentityException('phone_taken', 'این مشخصات قبلاً ثبت شده است.');
        }
    }
}
