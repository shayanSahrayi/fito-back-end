<?php

namespace App\Domain\Commerce;

use App\Models\CoachProfile;
use Illuminate\Support\Facades\DB;

final class AccessReader
{
    public function __construct(private readonly AccessSchedule $schedule) {}

    /**
     * @return array{access: array<string, mixed>|null, features: list<string>}
     */
    public function current(CoachProfile $coach): array
    {
        $active = DB::transaction(fn () => $this->schedule->promote($coach, now()));

        if ($active === null) {
            return [
                'access' => null,
                'features' => [],
            ];
        }

        $active->load('order.features');

        return [
            'access' => [
                'id' => $active->id,
                'status' => $active->status,
                'starts_at' => $active->starts_at->toJSON(),
                'ends_at' => $active->ends_at->toJSON(),
                'athlete_capacity' => (int) $active->athlete_capacity,
                'ai_request_quota' => $active->ai_request_quota === null ? null : (int) $active->ai_request_quota,
            ],
            'features' => $active->order->features->pluck('feature_key')->values()->all(),
        ];
    }
}
