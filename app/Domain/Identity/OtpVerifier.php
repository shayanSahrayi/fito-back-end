<?php

namespace App\Domain\Identity;

use App\Models\OtpChallenge;
use App\Models\SignupDraft;
use App\Models\User;

final class OtpVerifier
{
    public function __construct(
        private readonly OtpHasher $hasher,
        private readonly FixedOtpPolicy $fixedOtp,
    ) {}

    public function verifySignup(string $rawPhone, string $code): void
    {
        $challenge = $this->matchingOpen($rawPhone, 'signup', $code);

        $draft = $challenge->signupDraft;

        if (! $draft instanceof SignupDraft || $draft->open_phone === null) {
            throw new IdentityException('draft_not_verified', 'شماره هنوز تأیید نشده است.');
        }

        if ($draft->expires_at !== null && $draft->expires_at->lte(now())) {
            throw new IdentityException('draft_expired', 'پیش‌نویس ثبت‌نام منقضی شده است.');
        }

        $draft->phone_verified_at = now();
        $draft->expires_at = now()->addSeconds((int) config('fito.otp.draft_ttl_seconds'));
        $draft->save();

        $this->consume($challenge);
    }

    public function verifyLogin(string $rawPhone, string $code): User
    {
        $phone = PhoneNumber::normalize($rawPhone);
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            throw new IdentityException('account_not_found', 'حسابی با این شماره نیست.', 404);
        }

        if ($user->status !== 'active') {
            throw new IdentityException('account_suspended', 'این حساب تعلیق شده است.', 403);
        }

        $challenge = $this->matchingOpen($phone, 'login', $code);
        $this->consume($challenge);

        $user->last_login_at = now();
        $user->save();

        return $user;
    }

    public function matchingOpen(string $rawPhone, string $purpose, string $code, ?int $invitationId = null): OtpChallenge
    {
        $phone = PhoneNumber::normalize($rawPhone);
        $challenge = $this->latestOpen($phone, $purpose, $invitationId);
        $this->assertUsable($challenge, $code);

        return $challenge;
    }

    public function consume(OtpChallenge $challenge): void
    {
        $challenge->consumed_at = now();
        $challenge->save();
    }

    private function latestOpen(string $phone, string $purpose, ?int $invitationId = null): OtpChallenge
    {
        $challenge = OtpChallenge::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->when($invitationId !== null, fn ($query) => $query->where('athlete_invitation_id', $invitationId))
            ->orderByDesc('id')
            ->first();

        if ($challenge === null) {
            throw new IdentityException('otp_invalid', 'کد ورود نادرست است.');
        }

        return $challenge;
    }

    private function assertUsable(OtpChallenge $challenge, string $code): void
    {
        if ($challenge->expires_at->lte(now())) {
            throw new IdentityException('otp_expired', 'کد ورود منقضی شده است.');
        }

        $maxAttempts = (int) config('fito.otp.max_attempts');

        if ($challenge->attempt_count >= $maxAttempts) {
            throw new IdentityException('otp_exhausted', 'تعداد تلاش بیش از حد مجاز است.');
        }

        if ($this->matches($challenge, $code)) {
            return;
        }

        $challenge->increment('attempt_count');

        if ($challenge->attempt_count >= $maxAttempts) {
            throw new IdentityException('otp_exhausted', 'تعداد تلاش بیش از حد مجاز است.');
        }

        throw new IdentityException('otp_invalid', 'کد ورود نادرست است.');
    }

    private function matches(OtpChallenge $challenge, string $code): bool
    {
        if ($this->fixedOtp->accepts($code)) {
            return true;
        }

        return hash_equals($challenge->code_hash, $this->hasher->hash($code));
    }
}
