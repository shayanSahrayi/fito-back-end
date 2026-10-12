<?php

namespace App\Domain\Commerce;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class BillingPeriod
{
    /**
     * Month and day lengths follow the Asia/Tehran civil calendar, then return to UTC.
     */
    public static function end(DateTimeInterface $startsAtUtc, string $unit, int $count): CarbonImmutable
    {
        if (! in_array($unit, ['day', 'month'], true) || $count < 1) {
            throw new CommerceException('invalid_interval', 'دوره پلن معتبر نیست.');
        }

        $start = CarbonImmutable::instance(\Carbon\Carbon::parse($startsAtUtc))->utc()->timezone('Asia/Tehran');
        $end = $unit === 'month' ? $start->addMonthsNoOverflow($count) : $start->addDays($count);

        return $end->utc();
    }
}
