<?php

namespace App\Domain\Identity;

interface OtpCodeGenerator
{
    public function generate(): string;
}
