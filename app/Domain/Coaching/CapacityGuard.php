<?php

namespace App\Domain\Coaching;

use App\Domain\Commerce\CommerceException;
use App\Models\AccessPeriod;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;

final class CapacityGuard
{
    public function assertOpen(AccessPeriod $period, CoachProfile $coach): void
    {
        $used = CoachingRelationship::query()
            ->where('coach_profile_id', $coach->id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->count();

        if ($used >= (int) $period->athlete_capacity) {
            throw new CommerceException('capacity_full', 'ظرفیت ورزشکار این دوره پر است.');
        }
    }
}
