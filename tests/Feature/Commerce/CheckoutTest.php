<?php

namespace Tests\Feature\Commerce;

use App\Models\Order;
use Carbon\CarbonImmutable;

class CheckoutTest extends CommerceTestCase
{
    public function test_a_coach_without_a_purchase_has_no_open_features(): void
    {
        $coach = $this->coach();

        $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/access')
            ->assertOk()
            ->assertJson([
                'access' => null,
                'features' => [],
            ]);

        $this->bearer($this->athleteToken())
            ->postJson('/api/v1/coach/orders', ['plan_id' => 1])
            ->assertStatus(403)
            ->assertJson(['code' => 'coach_profile_required']);
    }

    public function test_one_open_order_is_reused_until_it_expires(): void
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $firstPlan = $this->createPlan($admin['token'])->assertCreated()->json('id');
        $secondPlan = $this->createPlan($admin['token'], ['code' => 'plus'])->assertCreated()->json('id');

        $opened = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $firstPlan,
        ])->assertCreated();

        $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $secondPlan,
        ])->assertOk()->assertJsonPath('id', $opened->json('id'));

        $this->assertDatabaseCount('orders', 1);

        $this->travel(1801)->seconds();

        $renewed = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $secondPlan,
        ])->assertCreated();

        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseHas('orders', [
            'id' => $opened->json('id'),
            'status' => 'expired',
            'open_checkout_coach_id' => null,
        ]);
        $this->assertSame(1, Order::query()->whereNotNull('open_checkout_coach_id')->count());
        $this->assertSame($secondPlan, $renewed->json('plan_id'));

        $this->travelBack();
    }

    public function test_a_repeated_callback_and_a_wrong_amount_do_not_create_a_second_period(): void
    {
        [$coach, $planId] = $this->sellableCoach();
        $order = $this->openOrder($coach['token'], $planId);
        $first = $this->attempt($coach['token'], $order['id'], 'pay-1')->assertCreated()->json();
        $second = $this->attempt($coach['token'], $order['id'], 'pay-2')->assertCreated()->json();

        $this->confirm($first['id'], 1001, 'ref-wrong')->assertStatus(422)->assertJson(['code' => 'amount_mismatch']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'pending']);

        $paid = $this->confirm($first['id'], 1000, 'ref-ok')->assertOk();
        $repeated = $this->confirm($first['id'], 1000, 'ref-ok')->assertOk();
        $this->assertSame($paid->json('payment_id'), $repeated->json('payment_id'));

        $this->confirm($second['id'], 1000, 'ref-other')->assertOk()->assertJsonPath('payment_id', $paid->json('payment_id'));

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('access_periods', 1);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $second['id'],
            'status' => 'failed',
            'failure_code' => 'order_already_paid',
        ]);
    }

    public function test_editing_a_plan_does_not_change_a_paid_order(): void
    {
        $admin = $this->admin();
        [$coach, $planId] = $this->sellableCoach($admin['token']);
        $order = $this->openOrder($coach['token'], $planId);
        $attempt = $this->attempt($coach['token'], $order['id'], 'pay-1')->assertCreated()->json();
        $this->confirm($attempt['id'], 1000, 'ref-ok')->assertOk();

        $this->bearer($admin['token'])->patchJson('/api/v1/admin/plans/'.$planId, [
            'price_irr' => 5,
            'features' => ['body_analysis'],
        ])->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'price_irr' => 1000]);
        $this->assertDatabaseHas('order_features', ['order_id' => $order['id'], 'feature_key' => 'workout_programs']);
        $this->assertDatabaseMissing('order_features', ['order_id' => $order['id'], 'feature_key' => 'body_analysis']);
        $this->assertDatabaseHas('plan_features', ['plan_id' => $planId, 'feature_key' => 'body_analysis']);
        $this->assertDatabaseMissing('plan_features', ['plan_id' => $planId, 'feature_key' => 'workout_programs']);
    }

    public function test_a_scheduled_period_becomes_active_when_the_current_one_ends(): void
    {
        [$coach, $planId] = $this->sellableCoach();
        $firstOrder = $this->openOrder($coach['token'], $planId);
        $firstAttempt = $this->attempt($coach['token'], $firstOrder['id'], 'pay-1')->json();
        $firstPayment = $this->confirm($firstAttempt['id'], 1000, 'ref-1')->assertOk()->json();
        $this->assertSame('active', $firstPayment['access_status']);

        $secondOrder = $this->openOrder($coach['token'], $planId);
        $secondAttempt = $this->attempt($coach['token'], $secondOrder['id'], 'pay-2')->json();
        $secondPayment = $this->confirm($secondAttempt['id'], 1000, 'ref-2')->assertOk()->json();
        $this->assertSame('scheduled', $secondPayment['access_status']);

        $current = $this->bearer($coach['token'])->getJson('/api/v1/coach/access')->assertOk();
        $this->assertSame($firstPayment['access_period_id'], $current->json('access.id'));
        $this->assertSame(['workout_programs'], $current->json('features'));

        $this->travelTo(CarbonImmutable::parse($current->json('access.ends_at'))->addSecond());

        $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/access')
            ->assertOk()
            ->assertJsonPath('access.id', $secondPayment['access_period_id'])
            ->assertJsonPath('access.status', 'active');

        $this->assertDatabaseHas('access_periods', [
            'id' => $firstPayment['access_period_id'],
            'status' => 'expired',
            'active_coach_id' => null,
        ]);
        $this->assertDatabaseHas('access_periods', [
            'id' => $secondPayment['access_period_id'],
            'status' => 'active',
            'active_coach_id' => $coach['profile']->id,
        ]);

        $this->travelBack();
    }

    public function test_an_admin_can_record_a_manual_payment_for_a_coach_order(): void
    {
        $admin = $this->admin();
        [$coach, $planId] = $this->sellableCoach($admin['token']);
        $order = $this->openOrder($coach['token'], $planId);

        $this->bearer($admin['token'])->postJson('/api/v1/admin/orders/'.$order['id'].'/manual-payments', [
            'amount_irr' => 1000,
            'gateway_reference' => 'manual-1',
        ])->assertOk()->assertJson(['access_status' => 'active']);

        $this->bearer($admin['token'])
            ->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.payment.gateway', 'manual')
            ->assertJsonPath('orders.0.payment.confirmed_by_user_id', $admin['user']->id);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'paid', 'coach_profile_id' => $coach['profile']->id]);
    }

    public function test_a_coach_cannot_start_payment_for_another_coach(): void
    {
        [$coach, $planId] = $this->sellableCoach();
        $order = $this->openOrder($coach['token'], $planId);
        $other = $this->coach('مربی دیگر');

        $this->bearer($other['token'])
            ->postJson('/api/v1/coach/orders/'.$order['id'].'/attempts', ['idempotency_key' => 'pay-1'])
            ->assertStatus(404)
            ->assertJson(['code' => 'order_not_found']);

        $this->bearer($other['token'])
            ->getJson('/api/v1/coach/access')
            ->assertOk()
            ->assertJsonPath('access', null);
    }

    public function test_an_unsellable_plan_cannot_be_ordered(): void
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $planId = $this->createPlan($admin['token'], ['is_sellable' => false])->assertCreated()->json('id');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertStatus(422)->assertJson(['code' => 'plan_not_sellable']);

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @return array{0: array{user: \App\Models\User, profile: \App\Models\CoachProfile, token: string}, 1: int}
     */
    private function sellableCoach(?string $adminToken = null): array
    {
        $adminToken ??= $this->admin()['token'];
        $planId = $this->createPlan($adminToken)->assertCreated()->json('id');

        return [$this->coach(), $planId];
    }

    /**
     * @return array<string, mixed>
     */
    private function openOrder(string $token, int $planId): array
    {
        return $this->bearer($token)->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json();
    }

    private function attempt(string $token, int $orderId, string $key): \Illuminate\Testing\TestResponse
    {
        return $this->bearer($token)->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => $key,
        ]);
    }
}
