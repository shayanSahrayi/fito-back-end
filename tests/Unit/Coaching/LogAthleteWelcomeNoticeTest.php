<?php

namespace Tests\Unit\Coaching;

use App\Domain\Coaching\LogAthleteWelcomeNotice;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LogAthleteWelcomeNoticeTest extends TestCase
{
    public function test_the_welcome_log_does_not_include_the_initial_password(): void
    {
        Event::fake([MessageLogged::class]);

        (new LogAthleteWelcomeNotice())->send('09120000000', '1234567891');

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            $encoded = json_encode($event->context);

            return $event->message === 'fito.invitation.sms'
                && $encoded !== false
                && ! str_contains($encoded, '1234567891');
        });
    }
}
