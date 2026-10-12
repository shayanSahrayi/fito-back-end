<?php

namespace App\Domain\Commerce;

use App\Models\AccessPeriod;
use App\Models\CoachProfile;
use App\Models\Order;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

final class AccessSchedule
{
    public function open(CoachProfile $coach, Order $order, DateTimeInterface $confirmedAt): AccessPeriod
    {
        $confirmedAt = CarbonImmutable::instance(\Carbon\Carbon::parse($confirmedAt))->utc();
        $active = $this->promote($coach, $confirmedAt);
        $startsAt = $active === null ? $confirmedAt : CarbonImmutable::instance($active->ends_at)->utc();
        $endsAt = BillingPeriod::end($startsAt, $order->billing_interval_unit, (int) $order->billing_interval_count);

        return AccessPeriod::query()->create([
            'coach_profile_id' => $coach->id,
            'order_id' => $order->id,
            'status' => $active === null ? 'active' : 'scheduled',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'athlete_capacity' => $order->athlete_capacity,
            'ai_request_quota' => $order->ai_request_quota,
            'ai_requests_used' => 0,
            'active_coach_id' => $active === null ? $coach->id : null,
        ]);
    }

    public function promote(CoachProfile $coach, DateTimeInterface $now): ?AccessPeriod
    {
        $now = CarbonImmutable::instance(\Carbon\Carbon::parse($now))->utc();

        /** @var Collection<int, AccessPeriod> $periods */
        $periods = AccessPeriod::query()
            ->where('coach_profile_id', $coach->id)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        while (true) {
            $active = $periods->first(fn (AccessPeriod $period): bool => $period->status === 'active');

            if ($active !== null && $active->ends_at->lte($now)) {
                $active->forceFill([
                    'status' => 'expired',
                    'active_coach_id' => null,
                ])->save();
                $active = null;
            }

            if ($active !== null) {
                return $active;
            }

            $next = $periods->first(fn (AccessPeriod $period): bool => $period->status === 'scheduled' && $period->starts_at->lte($now));

            if ($next === null) {
                return null;
            }

            if ($next->ends_at->lte($now)) {
                $next->forceFill([
                    'status' => 'expired',
                    'active_coach_id' => null,
                ])->save();

                continue;
            }

            $next->forceFill([
                'status' => 'active',
                'active_coach_id' => $coach->id,
            ])->save();

            return $next;
        }
    }
}
