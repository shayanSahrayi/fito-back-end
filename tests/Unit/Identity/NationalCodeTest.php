<?php

namespace Tests\Unit\Identity;

use App\Domain\Identity\IdentityException;
use App\Domain\Identity\NationalCode;
use PHPUnit\Framework\TestCase;

class NationalCodeTest extends TestCase
{
    public function test_it_accepts_a_code_with_a_valid_check_digit(): void
    {
        $code = NationalCode::withCheckDigit('123456789');

        $this->assertSame($code, NationalCode::parse($code));
    }

    public function test_it_rejects_a_bad_check_digit(): void
    {
        $code = NationalCode::withCheckDigit('123456789');
        $invalid = substr($code, 0, 9).((int) $code[9] === 0 ? '1' : '0');

        $this->expectException(IdentityException::class);
        $this->expectExceptionMessage('کد ملی معتبر نیست.');

        NationalCode::parse($invalid);
    }

    public function test_it_rejects_repeated_digits(): void
    {
        $this->expectException(IdentityException::class);

        NationalCode::parse('0000000000');
    }
}
