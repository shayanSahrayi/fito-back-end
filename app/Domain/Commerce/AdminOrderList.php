<?php

namespace App\Domain\Commerce;

use App\Models\Order;
use App\Models\Payment;

final class AdminOrderList
{
    /**
     * @return array{orders: list<array<string, mixed>>}
     */
    public function all(): array
    {
        $orders = Order::query()->with(['payment.attempt'])->orderByDesc('id')->get();

        return [
            'orders' => $orders->map(fn (Order $order): array => $this->present($order))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Order $order): array
    {
        return [
            'id' => $order->id,
            'coach_profile_id' => $order->coach_profile_id,
            'plan_id' => $order->plan_id,
            'status' => $order->status,
            'price_irr' => (int) $order->price_irr,
            'expires_at' => $order->expires_at->toJSON(),
            'paid_at' => $order->paid_at?->toJSON(),
            'payment' => $this->payment($order->payment),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payment(?Payment $payment): ?array
    {
        if ($payment === null) {
            return null;
        }

        $confirmedBy = $payment->attempt?->gateway_payload['confirmed_by_user_id'] ?? null;

        return [
            'id' => $payment->id,
            'amount_irr' => (int) $payment->amount_irr,
            'gateway' => $payment->gateway,
            'gateway_reference' => $payment->gateway_reference,
            'confirmed_at' => $payment->confirmed_at->toJSON(),
            'confirmed_by_user_id' => $confirmedBy === null ? null : (int) $confirmedBy,
        ];
    }
}
