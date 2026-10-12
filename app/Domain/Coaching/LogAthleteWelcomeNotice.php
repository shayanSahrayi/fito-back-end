<?php

namespace App\Domain\Coaching;

use Illuminate\Support\Facades\Log;

final class LogAthleteWelcomeNotice implements AthleteWelcomeNotice
{
    public function send(string $phone, string $initialPassword): void
    {
        Log::info('fito.invitation.sms', [
            'phone' => $phone,
        ]);
    }
}
