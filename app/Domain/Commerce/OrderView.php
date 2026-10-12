<?php

namespace App\Domain\Commerce;

use App\Models\Order;

final class OrderView
{
    /**
     * @return array<string, mixed>
     */
    public static function order(Order $order): array
    {
        $order->loadMissing('features');

        return [
            'id' => $order->id,
            'coach_profile_id' => $order->coach_profile_id,
            'plan_id' => $order->plan_id,
            'status' => $order->status,
            'price_irr' => (int) $order->price_irr,
            'billing_interval_unit' => $order->billing_interval_unit,
            'billing_interval_count' => (int) $order->billing_interval_count,
            'athlete_capacity' => (int) $order->athlete_capacity,
            'ai_request_quota' => $order->ai_request_quota === null ? null : (int) $order->ai_request_quota,
            'expires_at' => $order->expires_at->toJSON(),
            'paid_at' => $order->paid_at?->toJSON(),
            'features' => $order->features->pluck('feature_key')->values()->all(),
        ];
    }
}
