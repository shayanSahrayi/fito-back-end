<?php

namespace Tests\Unit\Identity;

use App\Domain\Identity\IdentityException;
use App\Domain\Identity\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_it_normalizes_common_mobile_shapes(): void
    {
        $this->assertSame('09123456789', PhoneNumber::normalize('09123456789'));
        $this->assertSame('09123456789', PhoneNumber::normalize('+989123456789'));
        $this->assertSame('09123456789', PhoneNumber::normalize('00989123456789'));
        $this->assertSame('09123456789', PhoneNumber::normalize('9123456789'));
        $this->assertSame('09123456789', PhoneNumber::normalize('۰۹۱۲۳۴۵۶۷۸۹'));
    }

    public function test_it_rejects_a_number_that_is_not_a_mobile(): void
    {
        $this->expectException(IdentityException::class);

        PhoneNumber::normalize('02112345678');
    }
}
