<?php

namespace App\Domain\Identity;

final class RandomOtpCodeGenerator implements OtpCodeGenerator
{
    public function generate(): string
    {
        return str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }
}
