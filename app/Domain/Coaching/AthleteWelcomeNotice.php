<?php

namespace App\Domain\Coaching;

interface AthleteWelcomeNotice
{
    /**
     * The initial password is for the message body only. Implementations must not log it.
     */
    public function send(string $phone, string $initialPassword): void;
}
