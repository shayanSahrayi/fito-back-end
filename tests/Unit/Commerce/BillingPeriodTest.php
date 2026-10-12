<?php

namespace Tests\Unit\Commerce;

use App\Domain\Commerce\BillingPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BillingPeriodTest extends TestCase
{
    public function test_a_tehran_month_does_not_overflow_past_the_month_end(): void
    {
        $end = BillingPeriod::end(CarbonImmutable::parse('2026-01-30 20:30:00', 'UTC'), 'month', 1);

        $this->assertSame('2026-02-27 20:30:00', $end->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_tehran_day_keeps_the_civil_clock_time(): void
    {
        $end = BillingPeriod::end(CarbonImmutable::parse('2026-03-20 20:30:00', 'UTC'), 'day', 1);

        $this->assertSame('2026-03-21 20:30:00', $end->utc()->format('Y-m-d H:i:s'));
    }
}
