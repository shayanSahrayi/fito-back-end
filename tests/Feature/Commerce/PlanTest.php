<?php

namespace Tests\Feature\Commerce;

class PlanTest extends CommerceTestCase
{
    public function test_only_an_admin_can_create_and_delete_an_unordered_plan(): void
    {
        $admin = $this->admin();
        $coach = $this->coach();

        $this->bearer($coach['token'])
            ->postJson('/api/v1/admin/plans', $this->planPayload())
            ->assertStatus(403)
            ->assertJson(['code' => 'forbidden']);

        $created = $this->createPlan($admin['token'])->assertCreated()->json();

        $this->bearer($admin['token'])
            ->deleteJson('/api/v1/admin/plans/'.$created['id'])
            ->assertNoContent();

        $this->assertDatabaseCount('plans', 0);
        $this->assertDatabaseCount('plan_features', 0);
    }

    public function test_an_ordered_plan_is_closed_instead_of_deleted(): void
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $planId = $this->createPlan($admin['token'])->assertCreated()->json('id');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated();

        $this->bearer($admin['token'])
            ->deleteJson('/api/v1/admin/plans/'.$planId)
            ->assertStatus(422)
            ->assertJson(['code' => 'plan_ordered']);

        $this->bearer($admin['token'])
            ->patchJson('/api/v1/admin/plans/'.$planId, ['is_sellable' => false])
            ->assertOk()
            ->assertJsonPath('is_sellable', false);
    }

    public function test_ai_features_require_a_quota_and_unknown_features_are_rejected(): void
    {
        $admin = $this->admin();

        $this->createPlan($admin['token'], [
            'features' => ['ai_coach_assistant'],
        ])->assertStatus(422)->assertJson(['code' => 'ai_quota_required']);

        $this->createPlan($admin['token'], [
            'features' => ['not_a_feature'],
        ])->assertStatus(422)->assertJsonValidationErrors('features.0');
    }
}
