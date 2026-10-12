<?php

namespace App\Domain\Identity;

use App\Domain\Coaching\InvitationClock;
use App\Domain\Commerce\CommerceException;
use App\Models\AthleteInvitation;
use App\Models\OtpChallenge;
use App\Models\SignupDraft;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class OtpIssuer
{
    public function __construct(
        private readonly OtpCodeGenerator $codes,
        private readonly OtpHasher $hasher,
        private readonly FixedOtpPolicy $fixedOtp,
        private readonly InvitationClock $clock,
    ) {}

    public function issue(string $rawPhone, string $purpose, ?int $invitationId = null): int
    {
        $this->fixedOtp->assertProductionIsSafe();
        $phone = PhoneNumber::normalize($rawPhone);

        if ($purpose === 'signup') {
            $this->issueSignup($phone);
        } elseif ($purpose === 'login') {
            $this->issueLogin($phone);
        } elseif ($purpose === 'invitation') {
            $this->issueInvitation($phone, $invitationId);
        } else {
            throw new IdentityException('invalid_purpose', 'هدف کد ورود معتبر نیست.');
        }

        return (int) config('fito.otp.ttl_seconds');
    }

    private function issueSignup(string $phone): void
    {
        if (User::query()->where('phone', $phone)->exists()) {
            throw new IdentityException('phone_taken', 'این شماره قبلاً ثبت شده است.');
        }

        $draft = SignupDraft::query()->where('open_phone', $phone)->first();

        if ($draft === null) {
            $draft = new SignupDraft([
                'phone' => $phone,
                'open_phone' => $phone,
            ]);
        }

        $draft->expires_at = now()->addSeconds((int) config('fito.otp.draft_ttl_seconds'));
        $draft->save();

        $this->storeChallenge($phone, 'signup', $draft->id);
    }

    private function issueLogin(string $phone): void
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            throw new IdentityException('account_not_found', 'حسابی با این شماره نیست.', 404);
        }

        if ($user->status !== 'active') {
            throw new IdentityException('account_suspended', 'این حساب تعلیق شده است.', 403);
        }

        $this->storeChallenge($phone, 'login', null, null);
    }

    private function issueInvitation(string $phone, ?int $invitationId): void
    {
        $invitation = AthleteInvitation::query()->whereKey($invitationId)->where('phone', $phone)->first();

        if ($invitation === null) {
            throw new CommerceException('invitation_not_found', 'دعوت پیدا نشد.', 404);
        }

        $this->clock->expireIfDue($invitation);

        if ($invitation->status !== 'pending') {
            throw new CommerceException('invitation_not_pending', 'این دعوت دیگر باز نیست.');
        }

        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            throw new IdentityException('account_not_found', 'حسابی با این شماره نیست.', 404);
        }

        AccountStatus::assertActive($user);

        $this->storeChallenge($phone, 'invitation', null, $invitation->id);
    }

    private function storeChallenge(string $phone, string $purpose, ?int $signupDraftId, ?int $invitationId = null): void
    {
        $code = $this->codes->generate();

        OtpChallenge::query()->create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => $this->hasher->hash($code),
            'expires_at' => now()->addSeconds((int) config('fito.otp.ttl_seconds')),
            'attempt_count' => 0,
            'signup_draft_id' => $signupDraftId,
            'athlete_invitation_id' => $invitationId,
        ]);

        if (app()->environment('local')) {
            Log::info('fito.otp.issued', [
                'phone' => $phone,
                'purpose' => $purpose,
                'code' => $code,
            ]);
        }
    }
}
