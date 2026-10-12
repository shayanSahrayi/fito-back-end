<?php

namespace App\Domain\Identity;

final class FixedOtpPolicy
{
    public function __construct(
        private readonly ?string $fixedCode,
        private readonly string $environment,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('fito.otp.fixed_code'),
            (string) app()->environment(),
        );
    }

    public function assertProductionIsSafe(): void
    {
        if ($this->environment === 'production' && $this->fixedCode !== null && $this->fixedCode !== '') {
            throw new \RuntimeException('Fixed OTP must not be configured when APP_ENV is production.');
        }
    }

    public function accepts(string $code): bool
    {
        if ($this->environment !== 'local' || $this->fixedCode === null || $this->fixedCode === '') {
            return false;
        }

        return hash_equals($this->fixedCode, $code);
    }
}
