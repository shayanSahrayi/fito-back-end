<?php

namespace Tests\Unit\Commerce;

use App\Domain\Commerce\TestGateway;
use PHPUnit\Framework\TestCase;

class TestGatewayTest extends TestCase
{
    public function test_the_test_gateway_is_only_open_outside_production(): void
    {
        $this->assertTrue(TestGateway::enabled('local'));
        $this->assertTrue(TestGateway::enabled('testing'));
        $this->assertFalse(TestGateway::enabled('production'));
    }
}
