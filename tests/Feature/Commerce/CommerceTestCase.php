<?php

namespace Tests\Feature\Commerce;

use App\Models\AthleteProfile;
use App\Models\CoachProfile;
use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class CommerceTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, profile: CoachProfile, token: string}
     */
    protected function coach(string $name = 'مربی'): array
    {
        $user = User::factory()->create();
        $profile = CoachProfile::query()->create([
            'user_id' => $user->id,
            'display_name' => $name,
            'status' => 'active',
        ]);

        return [
            'user' => $user,
            'profile' => $profile,
            'token' => $user->createToken('mobile')->plainTextToken,
        ];
    }

    /**
     * @return array{user: User, token: string}
     */
    protected function admin(): array
    {
        $user = User::factory()->create();
        PlatformRole::query()->create([
            'user_id' => $user->id,
            'role' => 'platform_admin',
        ]);

        return [
            'user' => $user,
            'token' => $user->createToken('mobile')->plainTextToken,
        ];
    }

    protected function athleteToken(): string
    {
        $user = User::factory()->create();
        AthleteProfile::query()->create([
            'user_id' => $user->id,
            'display_name' => 'ورزشکار',
            'status' => 'active',
        ]);

        return $user->createToken('mobile')->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function planPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'starter',
            'name' => 'شروع',
            'description' => null,
            'price_irr' => 1000,
            'billing_interval_unit' => 'day',
            'billing_interval_count' => 1,
            'athlete_capacity' => 5,
            'ai_request_quota' => null,
            'is_sellable' => true,
            'features' => ['workout_programs'],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPlan(string $adminToken, array $overrides = []): TestResponse
    {
        return $this->bearer($adminToken)->postJson('/api/v1/admin/plans', $this->planPayload($overrides));
    }

    protected function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    protected function confirm(int $attemptId, int $amount, string $reference): TestResponse
    {
        return $this->postJson('/api/v1/payments/test/callback', [
            'attempt_id' => $attemptId,
            'amount_irr' => $amount,
            'gateway_reference' => $reference,
        ]);
    }
}
