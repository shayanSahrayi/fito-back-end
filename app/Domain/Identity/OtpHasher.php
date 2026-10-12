<?php

namespace App\Domain\Identity;

final class OtpHasher
{
    public function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
