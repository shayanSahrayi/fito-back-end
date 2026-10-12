<?php

namespace Tests\Feature\Auth;

use App\Models\User;

class LoginTest extends AuthTestCase
{
    public function test_web_login_starts_a_session_and_mobile_login_returns_a_token(): void
    {
        $created = $this->completeSignup();

        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'login',
        ])->assertOk();

        $web = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'purpose' => 'login',
            'code' => self::OTP,
            'client' => 'web',
        ]);

        $web->assertOk()->assertJson(['user_id' => $created['user_id']]);
        $web->assertJsonMissingPath('token');
        $this->assertAuthenticatedAs(User::query()->findOrFail($created['user_id']));

        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();

        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'login',
        ])->assertOk();

        $mobile = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'purpose' => 'login',
            'code' => self::OTP,
            'client' => 'mobile',
        ]);

        $mobile->assertOk();
        $token = $mobile->json('token');
        $this->assertIsString($token);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull(User::query()->findOrFail($created['user_id'])->last_login_at);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function test_a_suspended_user_cannot_request_a_login_code(): void
    {
        User::factory()->create([
            'phone' => self::PHONE,
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'login',
        ])->assertStatus(403)->assertJson(['code' => 'account_suspended']);

        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_login_requires_an_existing_account(): void
    {
        $this->postJson('/api/v1/auth/otp', [
            'phone' => self::PHONE,
            'purpose' => 'login',
        ])->assertStatus(404)->assertJson(['code' => 'account_not_found']);
    }

    public function test_logout_without_a_session_or_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }
}
