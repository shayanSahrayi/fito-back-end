<?php

namespace Tests\Unit\Identity;

use App\Domain\Identity\FixedOtpPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FixedOtpPolicyTest extends TestCase
{
    public function test_local_accepts_only_the_fixed_code(): void
    {
        $policy = new FixedOtpPolicy('12345', 'local');

        $this->assertTrue($policy->accepts('12345'));
        $this->assertFalse($policy->accepts('54321'));
    }

    public function test_testing_and_production_do_not_accept_the_fixed_code(): void
    {
        $this->assertFalse((new FixedOtpPolicy('12345', 'testing'))->accepts('12345'));
        $this->assertFalse((new FixedOtpPolicy(null, 'production'))->accepts('12345'));
    }

    public function test_production_refuses_to_start_when_a_fixed_code_is_configured(): void
    {
        $this->expectException(RuntimeException::class);

        (new FixedOtpPolicy('12345', 'production'))->assertProductionIsSafe();
    }
}
