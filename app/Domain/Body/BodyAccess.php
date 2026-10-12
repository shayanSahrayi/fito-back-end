<?php

namespace App\Domain\Body;

use App\Domain\Commerce\AccessSchedule;
use App\Domain\Commerce\CommerceException;
use App\Models\CoachProfile;

final class BodyAccess
{
    public function __construct(private readonly AccessSchedule $schedule) {}

    public function assert(CoachProfile $coach): void
    {
        $active = $this->schedule->promote($coach, now());

        if ($active === null) {
            throw new CommerceException('no_access', 'دوره دسترسی فعالی نیست.', 403);
        }

        $active->load('order.features');
        $keys = $active->order === null
            ? []
            : $active->order->features->pluck('feature_key')->all();

        if (! in_array('body_analysis', $keys, true)) {
            throw new CommerceException('feature_unavailable', 'این دوره قابلیت آنالیز بدن ندارد.', 403);
        }
    }
}
