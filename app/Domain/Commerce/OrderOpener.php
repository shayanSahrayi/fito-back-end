<?php

namespace App\Domain\Commerce;

use App\Models\CoachProfile;
use App\Models\Order;
use App\Models\OrderFeature;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class OrderOpener
{
    /**
     * @return array{created: bool, order: array<string, mixed>}
     */
    public function open(User $coachUser, CoachProfile $coach, int $planId): array
    {
        $plan = Plan::query()->with('features')->find($planId);

        if ($plan === null) {
            throw new CommerceException('plan_not_found', 'پلن پیدا نشد.', 404);
        }

        try {
            return DB::transaction(function () use ($coachUser, $coach, $plan): array {
                $existing = Order::query()->where('open_checkout_coach_id', $coach->id)->lockForUpdate()->first();

                if ($existing !== null) {
                    if ($existing->expires_at->gt(now())) {
                        return [
                            'created' => false,
                            'order' => OrderView::order($existing),
                        ];
                    }

                    $existing->forceFill([
                        'status' => 'expired',
                        'open_checkout_coach_id' => null,
                    ])->save();
                }

                if (! $plan->is_sellable) {
                    throw new CommerceException('plan_not_sellable', 'این پلن قابل فروش نیست.');
                }

                $order = Order::query()->create([
                    'coach_profile_id' => $coach->id,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                    'price_irr' => $plan->price_irr,
                    'billing_interval_unit' => $plan->billing_interval_unit,
                    'billing_interval_count' => $plan->billing_interval_count,
                    'athlete_capacity' => $plan->athlete_capacity,
                    'ai_request_quota' => $plan->ai_request_quota,
                    'open_checkout_coach_id' => $coach->id,
                    'expires_at' => now()->addSeconds((int) config('fito.checkout.ttl_seconds')),
                    'created_by_user_id' => $coachUser->id,
                ]);

                foreach ($plan->features as $feature) {
                    OrderFeature::query()->create([
                        'order_id' => $order->id,
                        'feature_key' => $feature->feature_key,
                    ]);
                }

                return [
                    'created' => true,
                    'order' => OrderView::order($order),
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = Order::query()->where('open_checkout_coach_id', $coach->id)->first();

            if ($existing === null) {
                throw new CommerceException('order_open', 'سفارش باز ساخته نشد.', 409);
            }

            return [
                'created' => false,
                'order' => OrderView::order($existing),
            ];
        }
    }
}
