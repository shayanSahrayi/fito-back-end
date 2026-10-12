<?php

namespace App\Domain\Commerce;

use App\Models\CoachProfile;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class PaymentAttemptStarter
{
    /**
     * @return array{created: bool, attempt: array<string, mixed>}
     */
    public function start(CoachProfile $coach, Order $order, string $idempotencyKey): array
    {
        if (! TestGateway::enabled((string) app()->environment())) {
            throw new CommerceException('test_gateway_disabled', 'درگاه آزمایشی در این محیط بسته است.', 403);
        }

        if ($order->coach_profile_id !== $coach->id) {
            throw new CommerceException('order_not_found', 'سفارش پیدا نشد.', 404);
        }

        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            throw new CommerceException('invalid_idempotency_key', 'کلید تکرار پرداخت معتبر نیست.');
        }

        $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            if ($existing->order_id !== $order->id) {
                throw new CommerceException('idempotency_conflict', 'این کلید تکرار برای سفارش دیگری است.');
            }

            return [
                'created' => false,
                'attempt' => $this->present($existing),
            ];
        }

        try {
            $attempt = DB::transaction(function () use ($order, $idempotencyKey): PaymentAttempt {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $this->assertPayable($locked);

                return PaymentAttempt::query()->create([
                    'order_id' => $locked->id,
                    'gateway' => 'test',
                    'amount_irr' => $locked->price_irr,
                    'status' => 'initiated',
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing === null || $existing->order_id !== $order->id) {
                throw new CommerceException('idempotency_conflict', 'این کلید تکرار برای سفارش دیگری است.');
            }

            return [
                'created' => false,
                'attempt' => $this->present($existing),
            ];
        }

        return [
            'created' => true,
            'attempt' => $this->present($attempt),
        ];
    }

    private function assertPayable(Order $order): void
    {
        if ($order->status === 'pending' && $order->expires_at->lte(now())) {
            $order->forceFill([
                'status' => 'expired',
                'open_checkout_coach_id' => null,
            ])->save();
        }

        if ($order->status !== 'pending') {
            throw new CommerceException('order_expired', 'مهلت پرداخت سفارش تمام شده است.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PaymentAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'order_id' => $attempt->order_id,
            'gateway' => $attempt->gateway,
            'amount_irr' => (int) $attempt->amount_irr,
            'status' => $attempt->status,
            'idempotency_key' => $attempt->idempotency_key,
        ];
    }
}
