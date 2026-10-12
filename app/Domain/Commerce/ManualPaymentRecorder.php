<?php

namespace App\Domain\Commerce;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;

final class ManualPaymentRecorder
{
    public function __construct(private readonly PaymentConfirmer $confirmer) {}

    /**
     * @return array{order_id: int, payment_id: int, access_period_id: int, access_status: string}
     */
    public function record(User $admin, Order $order, int $amountIrr, string $reference): array
    {
        $attempt = PaymentAttempt::query()->create([
            'order_id' => $order->id,
            'gateway' => 'manual',
            'amount_irr' => $amountIrr,
            'status' => 'initiated',
            'idempotency_key' => bin2hex(random_bytes(32)),
            'gateway_payload' => ['confirmed_by_user_id' => $admin->id],
        ]);

        return $this->confirmer->confirm(
            $attempt->id,
            $amountIrr,
            $reference,
            'manual',
            ['confirmed_by_user_id' => $admin->id],
        );
    }
}
