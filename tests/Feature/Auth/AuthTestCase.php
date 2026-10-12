<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\NationalCode;
use App\Domain\Identity\OtpCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AuthTestCase extends TestCase
{
    use RefreshDatabase;

    protected const PHONE = '09120000000';

    protected const OTP = '54321';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(OtpCodeGenerator::class, fn () => new class implements OtpCodeGenerator
        {
            public function generate(): string
            {
                return '54321';
            }
        });
    }

    protected function nationalCode(string $firstNine = '123456789'): string
    {
        return NationalCode::withCheckDigit($firstNine);
    }

    protected function verifySignupPhone(string $phone = self::PHONE): void
    {
        $this->postJson('/api/v1/auth/otp', [
            'phone' => $phone,
            'purpose' => 'signup',
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'purpose' => 'signup',
            'code' => self::OTP,
        ])->assertOk()->assertJson(['phone_verified' => true]);
    }

    /**
     * @return array{user_id: int, panel: string, profile_id: int}
     */
    protected function completeSignup(string $phone = self::PHONE, string $panel = 'coach', ?string $nationalCode = null): array
    {
        $this->verifySignupPhone($phone);

        $response = $this->postJson('/api/v1/auth/signup', [
            'phone' => $phone,
            'national_code' => $nationalCode ?? $this->nationalCode(),
            'display_name' => 'شایان',
            'panel' => $panel,
        ]);

        $response->assertCreated();

        return $response->json();
    }
}
