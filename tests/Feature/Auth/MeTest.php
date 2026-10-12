<?php

namespace Tests\Feature\Auth;

use App\Models\CoachProfile;
use App\Models\User;

class MeTest extends AuthTestCase
{
    public function test_guest_cannot_read_the_account(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_coach_without_a_plan_can_read_the_account_from_the_web_session(): void
    {
        $created = $this->completeSignup();
        $this->loginWeb();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJson([
                'user_id' => $created['user_id'],
                'phone' => self::PHONE,
                'status' => 'active',
                'password_is_initial' => false,
                'profiles' => [
                    'coach' => [
                        'id' => $created['profile_id'],
                        'display_name' => 'شایان',
                        'status' => 'active',
                    ],
                    'athlete' => null,
                ],
                'roles' => [],
            ])
            ->assertJsonMissingPath('plan')
            ->assertJsonMissingPath('password');
    }

    public function test_mobile_token_can_read_the_account(): void
    {
        $created = $this->completeSignup(panel: 'athlete', nationalCode: $this->nationalCode('223456789'));
        $token = $this->loginMobile();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('profiles.athlete.id', $created['profile_id'])
            ->assertJsonPath('profiles.coach', null);
    }

    public function test_a_suspended_token_cannot_read_the_account(): void
    {
        $user = User::factory()->create([
            'phone' => self::PHONE,
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);
        CoachProfile::query()->create([
            'user_id' => $user->id,
            'display_name' => 'شایان',
            'status' => 'active',
        ]);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertStatus(403)
            ->assertJson(['code' => 'account_suspended']);
    }

    public function test_the_missing_profile_is_added_once_and_creates_no_relationship(): void
    {
        $this->completeSignup();
        $this->loginWeb();

        $this->postJson('/api/v1/me/profiles', [
            'panel' => 'athlete',
            'display_name' => 'شایان ورزشکار',
        ])->assertCreated()->assertJson([
            'panel' => 'athlete',
        ]);

        $this->assertDatabaseCount('athlete_profiles', 1);
        $this->assertDatabaseCount('coach_profiles', 1);
        $this->assertDatabaseCount('coaching_relationships', 0);

        $this->postJson('/api/v1/me/profiles', [
            'panel' => 'athlete',
            'display_name' => 'نام دیگر',
        ])->assertStatus(422)->assertJson(['code' => 'profile_exists']);

        $this->assertDatabaseCount('athlete_profiles', 1);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('profiles.athlete.display_name', 'شایان ورزشکار')
            ->assertJsonPath('profiles.coach.display_name', 'شایان');
    }

    public function test_panel_must_be_coach_or_athlete(): void
    {
        $this->completeSignup();
        $this->loginWeb();

        $this->postJson('/api/v1/me/profiles', [
            'panel' => 'both',
            'display_name' => 'شایان',
        ])->assertStatus(422)->assertJsonValidationErrors('panel');
    }

    public function test_platform_admin_is_granted_once_and_appears_on_the_account(): void
    {
        $created = $this->completeSignup();

        $this->artisan('identity:grant-platform-admin', ['phone' => self::PHONE])
            ->expectsOutput('نقش مدیر داده شد.')
            ->assertSuccessful();

        $this->assertDatabaseHas('platform_roles', [
            'user_id' => $created['user_id'],
            'role' => 'platform_admin',
            'granted_by_user_id' => null,
        ]);

        $this->artisan('identity:grant-platform-admin', ['phone' => '+98'.substr(self::PHONE, 1)])
            ->expectsOutput('این کاربر از قبل مدیر است.')
            ->assertSuccessful();

        $this->assertDatabaseCount('platform_roles', 1);

        $this->loginWeb();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('roles', ['platform_admin']);
    }

    public function test_platform_admin_is_not_granted_to_a_missing_or_suspended_phone(): void
    {
        $this->artisan('identity:grant-platform-admin', ['phone' => self::PHONE])
            ->expectsOutput('حسابی با این شماره نیست.')
            ->assertFailed();

        User::factory()->create([
            'phone' => self::PHONE,
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        $this->artisan('identity:grant-platform-admin', ['phone' => self::PHONE])
            ->expectsOutput('این حساب تعلیق شده است.')
            ->assertFailed();

        $this->assertDatabaseCount('platform_roles', 0);
    }

    private function loginWeb(): void
    {
        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'login',
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'purpose' => 'login',
            'code' => self::OTP,
            'client' => 'web',
        ])->assertOk();
    }

    private function loginMobile(): string
    {
        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'login',
        ])->assertOk();

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'purpose' => 'login',
            'code' => self::OTP,
            'client' => 'mobile',
        ])->assertOk();

        return $response->json('token');
    }
}
