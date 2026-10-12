<?php

namespace App\Domain\Commerce;

use App\Models\AccessPeriod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class PaymentConfirmer
{
    public function __construct(private readonly AccessSchedule $schedule) {}

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{order_id: int, payment_id: int, access_period_id: int, access_status: string}
     */
    public function confirm(int $attemptId, int $amountIrr, string $gatewayReference, string $expectedGateway, ?array $payload = null): array
    {
        $gatewayReference = trim($gatewayReference);

        if ($gatewayReference === '') {
            throw new CommerceException('invalid_reference', 'مرجع پرداخت معتبر نیست.');
        }

        try {
            $outcome = DB::transaction(function () use ($attemptId, $amountIrr, $gatewayReference, $expectedGateway, $payload): array {
                $attempt = PaymentAttempt::query()->lockForUpdate()->find($attemptId);

                if ($attempt === null) {
                    return ['ok' => false, 'code' => 'attempt_not_found', 'message' => 'تلاش پرداخت پیدا نشد.', 'status' => 404];
                }

                if ($attempt->gateway !== $expectedGateway) {
                    return ['ok' => false, 'code' => 'gateway_mismatch', 'message' => 'این تلاش برای این درگاه نیست.', 'status' => 422];
                }

                $existingPayment = Payment::query()->where('order_id', $attempt->order_id)->first();

                if ($attempt->status === 'succeeded' && $existingPayment !== null && $existingPayment->payment_attempt_id === $attempt->id) {
                    return ['ok' => true, 'payment' => $existingPayment];
                }

                $order = Order::query()->lockForUpdate()->findOrFail($attempt->order_id);

                if ($existingPayment !== null) {
                    if ($attempt->status === 'initiated') {
                        $this->fail($attempt, 'order_already_paid');
                    }

                    return ['ok' => true, 'payment' => $existingPayment];
                }

                if ($amountIrr !== (int) $order->price_irr) {
                    $this->fail($attempt, 'amount_mismatch');

                    return ['ok' => false, 'code' => 'amount_mismatch', 'message' => 'مبلغ با سفارش برابر نیست.', 'status' => 422];
                }

                if ($order->status === 'pending' && $order->expires_at->lte(now())) {
                    $order->forceFill([
                        'status' => 'expired',
                        'open_checkout_coach_id' => null,
                    ])->save();
                    $this->fail($attempt, 'order_expired');

                    return ['ok' => false, 'code' => 'order_expired', 'message' => 'مهلت پرداخت سفارش تمام شده است.', 'status' => 422];
                }

                if ($order->status !== 'pending') {
                    $this->fail($attempt, 'order_not_payable');

                    return ['ok' => false, 'code' => 'order_not_payable', 'message' => 'این سفارش قابل پرداخت نیست.', 'status' => 422];
                }

                $now = now();
                $attempt->forceFill([
                    'status' => 'succeeded',
                    'gateway_reference' => $gatewayReference,
                    'finished_at' => $now,
                    'gateway_payload' => $payload ?? $attempt->gateway_payload,
                ])->save();

                $payment = Payment::query()->create([
                    'order_id' => $order->id,
                    'payment_attempt_id' => $attempt->id,
                    'amount_irr' => $order->price_irr,
                    'gateway' => $attempt->gateway,
                    'gateway_reference' => $gatewayReference,
                    'confirmed_at' => $now,
                ]);

                $order->forceFill([
                    'status' => 'paid',
                    'paid_at' => $now,
                    'open_checkout_coach_id' => null,
                ])->save();

                $period = $this->schedule->open($order->coachProfile()->firstOrFail(), $order, $now);

                return ['ok' => true, 'payment' => $payment, 'period' => $period];
            });
        } catch (UniqueConstraintViolationException) {
            throw new CommerceException('gateway_reference_taken', 'این مرجع پرداخت قبلاً ثبت شده است.');
        }

        if ($outcome['ok'] !== true) {
            throw new CommerceException($outcome['code'], $outcome['message'], $outcome['status']);
        }

        /** @var Payment $payment */
        $payment = $outcome['payment'];
        $period = $outcome['period'] ?? AccessPeriod::query()->where('order_id', $payment->order_id)->firstOrFail();

        return [
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'access_period_id' => $period->id,
            'access_status' => $period->status,
        ];
    }

    private function fail(PaymentAttempt $attempt, string $code): void
    {
        $attempt->forceFill([
            'status' => 'failed',
            'failure_code' => $code,
            'finished_at' => now(),
        ])->save();
    }
}
