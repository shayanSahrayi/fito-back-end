<?php

namespace Tests\Feature\Auth;

use App\Models\AthleteProfile;
use App\Models\CoachProfile;
use App\Models\SignupDraft;
use App\Models\User;
use RuntimeException;

class SignupTest extends AuthTestCase
{
    public function test_signup_creates_the_user_and_only_the_chosen_profile(): void
    {
        $created = $this->completeSignup();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('coach_profiles', 1);
        $this->assertDatabaseCount('athlete_profiles', 0);
        $this->assertDatabaseHas('users', [
            'id' => $created['user_id'],
            'phone' => self::PHONE,
            'password' => null,
            'status' => 'active',
        ]);
        $this->assertNull(SignupDraft::query()->where('created_user_id', $created['user_id'])->firstOrFail()->open_phone);
    }

    public function test_athlete_signup_does_not_create_a_coach_profile(): void
    {
        $created = $this->completeSignup(panel: 'athlete', nationalCode: $this->nationalCode('223456789'));

        $this->assertDatabaseCount('athlete_profiles', 1);
        $this->assertDatabaseCount('coach_profiles', 0);
        $this->assertSame($created['profile_id'], AthleteProfile::query()->firstOrFail()->id);
    }

    public function test_fixed_otp_is_rejected_outside_local(): void
    {
        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'signup',
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'purpose' => 'signup',
            'code' => '12345',
        ])->assertStatus(422)->assertJson(['code' => 'otp_invalid']);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'signup',
        ])->assertOk();

        $this->travel(121)->seconds();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'purpose' => 'signup',
            'code' => self::OTP,
        ])->assertStatus(422)->assertJson(['code' => 'otp_expired']);

        $this->travelBack();
    }

    public function test_five_wrong_attempts_lock_the_challenge(): void
    {
        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'signup',
        ])->assertOk();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'phone' => self::PHONE,
                'purpose' => 'signup',
                'code' => '00000',
            ])->assertStatus(422)->assertJson(['code' => 'otp_invalid']);
        }

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'purpose' => 'signup',
            'code' => '00000',
        ])->assertStatus(422)->assertJson(['code' => 'otp_exhausted']);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'purpose' => 'signup',
            'code' => self::OTP,
        ])->assertStatus(422)->assertJson(['code' => 'otp_exhausted']);
    }

    public function test_duplicate_phone_does_not_create_a_second_account(): void
    {
        $this->completeSignup();

        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'signup',
        ])->assertStatus(422)->assertJson(['code' => 'phone_taken']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_duplicate_national_code_does_not_leave_a_partial_account(): void
    {
        $nationalCode = $this->nationalCode();
        User::factory()->create([
            'phone' => '09121111111',
            'national_code' => $nationalCode,
        ]);

        $this->verifySignupPhone();

        $this->postJson('/api/v1/auth/signup', [
            'phone' => self::PHONE,
            'national_code' => $nationalCode,
            'display_name' => 'شایان',
            'panel' => 'coach',
        ])->assertStatus(422)->assertJson(['code' => 'national_code_taken']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('coach_profiles', 0);
        $this->assertNotNull(SignupDraft::query()->where('open_phone', self::PHONE)->first());
    }

    public function test_profile_failure_rolls_back_the_user(): void
    {
        CoachProfile::creating(function () {
            throw new RuntimeException('stop');
        });

        $this->verifySignupPhone();

        $this->postJson('/api/v1/auth/signup', [
            'phone' => self::PHONE,
            'national_code' => $this->nationalCode(),
            'display_name' => 'شایان',
            'panel' => 'coach',
        ])->assertStatus(500);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('coach_profiles', 0);
        $this->assertNull(SignupDraft::query()->where('phone', self::PHONE)->firstOrFail()->consumed_at);
    }

    public function test_panel_must_be_coach_or_athlete(): void
    {
        $this->verifySignupPhone();

        $this->postJson('/api/v1/auth/signup', [
            'phone' => self::PHONE,
            'national_code' => $this->nationalCode(),
            'display_name' => 'شایان',
            'panel' => 'both',
        ])->assertStatus(422)->assertJsonValidationErrors('panel');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_signup_normalizes_the_phone(): void
    {
        $this->postJson('/api/v1/auth/otp', [
            'phone' => '+989120000000',
            'purpose' => 'signup',
        ])->assertOk();

        $this->assertDatabaseHas('signup_drafts', [
            'phone' => self::PHONE,
            'open_phone' => self::PHONE,
        ]);
    }
}
