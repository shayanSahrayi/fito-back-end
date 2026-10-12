<?php

namespace App\Domain\Ai;

use App\Domain\Commerce\AccessSchedule;
use App\Domain\Commerce\CommerceException;
use App\Models\AccessPeriod;
use App\Models\AiUsageEntry;
use App\Models\CoachProfile;
use App\Models\User;

final class AiQuota
{
    public function __construct(private readonly AccessSchedule $schedule) {}

    public function consume(
        CoachProfile $coach,
        User $actor,
        string $feature,
        string $kind,
        ?int $athleteProfileId,
        ?int $relationshipId,
        ?int $generationId,
    ): void {
        $active = $this->schedule->promote($coach, now());

        if ($active === null) {
            throw new CommerceException('no_access', 'دوره دسترسی فعالی نیست.', 403);
        }

        $locked = AccessPeriod::query()->whereKey($active->id)->lockForUpdate()->first();

        if ($locked === null || $locked->status !== 'active') {
            throw new CommerceException('no_access', 'دوره دسترسی فعالی نیست.', 403);
        }

        $locked->load('order.features');
        $keys = $locked->order === null ? [] : $locked->order->features->pluck('feature_key')->all();

        if (! in_array($feature, $keys, true)) {
            throw new CommerceException('feature_unavailable', 'این دوره قابلیت دستیار ندارد.', 403);
        }

        if ($locked->ai_request_quota === null) {
            throw new AiException('quota_missing', 'سهمیه دستیار برای این دوره تنظیم نشده است.');
        }

        if ((int) $locked->ai_requests_used >= (int) $locked->ai_request_quota) {
            throw new AiException('quota_exhausted', 'سهمیه دستیار این دوره تمام شده است.');
        }

        $locked->ai_requests_used = (int) $locked->ai_requests_used + 1;
        $locked->save();

        AiUsageEntry::query()->create([
            'access_period_id' => $locked->id,
            'coach_profile_id' => $coach->id,
            'actor_user_id' => $actor->id,
            'athlete_profile_id' => $athleteProfileId,
            'coaching_relationship_id' => $relationshipId,
            'ai_generation_id' => $generationId,
            'kind' => $kind,
            'units' => 1,
            'provider_prompt_tokens' => null,
            'provider_completion_tokens' => null,
        ]);
    }
}
