<?php

namespace Tests\Feature\Ai;

use App\Models\AccessPeriod;
use App\Models\AiUsageEntry;
use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutAssignment;
use Tests\Feature\Commerce\CommerceTestCase;

class AssistantTest extends CommerceTestCase
{
    public function test_admin_provider_hides_the_api_key(): void
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $secret = 'sk-test-secret-9999';

        $saved = $this->bearer($admin['token'])->putJson('/api/v1/admin/ai-provider', [
            'provider' => 'openai',
            'model_name' => 'gpt-test',
            'api_key' => $secret,
        ])->assertOk();

        $saved->assertJsonPath('configured', true);
        $saved->assertJsonPath('key_hint', '9999');
        $this->assertStringNotContainsString($secret, $saved->getContent());

        $again = $this->bearer($admin['token'])->putJson('/api/v1/admin/ai-provider', [
            'provider' => 'anthropic',
            'model_name' => 'claude-test',
        ])->assertOk();
        $again->assertJsonPath('provider', 'anthropic');
        $again->assertJsonPath('key_hint', '9999');
        $this->assertStringNotContainsString($secret, $again->getContent());

        $this->bearer($coach['token'])
            ->getJson('/api/v1/admin/ai-provider')
            ->assertForbidden();
    }

    public function test_the_last_quota_unit_is_consumed_once_and_knowledge_stays_with_its_coach(): void
    {
        $coach = $this->coachWithAi(['ai_coach_assistant'], 1);
        $other = $this->coachWithAi(['ai_coach_assistant'], 2, 'دیگر');
        $athlete = $this->athleteOf($coach);
        $this->settings($coach['token']);
        $this->settings($other['token']);

        $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/knowledge', [
            'title' => 'روش من',
            'body' => 'دانش-مالک',
        ])->assertCreated();
        $this->bearer($other['token'])->postJson('/api/v1/coach/ai/knowledge', [
            'title' => 'روش او',
            'body' => 'راز-دانش',
        ])->assertCreated();

        $this->bearer($coach['token'])->postJson('/api/v1/coach/feedback-threads', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'visibility' => 'coach_only',
            'body' => 'یادداشت داخلی',
        ])->assertCreated();
        $this->bearer($athlete['token'])->postJson('/api/v1/athlete/feedback-threads', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'پیام مشترک',
        ])->assertCreated();

        $conversation = $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/conversations', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'دانش-مالک',
        ])->assertCreated();
        $bodies = collect($conversation->json('messages'))->pluck('body')->implode("\n");
        $this->assertStringContainsString('دانش-مالک', $bodies);
        $this->assertStringContainsString('پیام مشترک', $bodies);
        $this->assertStringNotContainsString('راز-دانش', $bodies);
        $this->assertStringNotContainsString('یادداشت داخلی', $bodies);

        $this->bearer($other['token'])
            ->getJson('/api/v1/coach/ai/conversations/'.$conversation->json('id'))
            ->assertNotFound()
            ->assertJsonPath('code', 'conversation_not_found');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/conversations', [
            'body' => 'دوباره',
        ])->assertStatus(422)->assertJsonPath('code', 'quota_exhausted');

        $used = AccessPeriod::query()->where('coach_profile_id', $coach['profile']->id)->value('ai_requests_used');
        $this->assertSame(1, (int) $used);
        $this->assertSame(1, AiUsageEntry::query()->where('coach_profile_id', $coach['profile']->id)->count());
    }

    public function test_unapproved_generation_does_not_assign_and_athlete_access_is_separate(): void
    {
        $admin = $this->admin();
        $coach = $this->coachWithAi(['ai_coach_assistant', 'ai_athlete_access'], 3);
        $blocked = $this->coachWithAi(['ai_coach_assistant'], 2, 'بدون ورزشکار');
        $athlete = $this->athleteOf($coach);
        $blockedAthlete = $this->athleteOf($blocked);
        $this->settings($blocked['token']);
        Exercise::query()->create([
            'coach_profile_id' => null,
            'name' => 'اسکوات',
            'instructions' => 'توضیح',
            'goal' => 'هدف',
            'tracking_type' => 'weight_reps',
            'status' => 'active',
            'created_by_user_id' => $coach['user']->id,
        ]);
        $this->bearer($admin['token'])->postJson('/api/v1/admin/foods', [
            'name' => 'برنج',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 100,
            'protein_mg' => 0,
            'carbohydrate_mg' => 0,
            'fat_mg' => 0,
        ])->assertCreated();

        $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/generations', [
            'kind' => 'workout_program',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'برنامه بده',
        ])->assertStatus(422)->assertJsonPath('code', 'onboarding_required');

        $this->settings($coach['token']);

        $draft = $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/generations', [
            'kind' => 'workout_program',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'برنامه بده',
        ])->assertCreated();
        $draft->assertJsonPath('status', 'draft');
        $this->assertNull($draft->json('workout_program_version_id'));
        $this->assertSame(0, WorkoutAssignment::query()->count());

        $approved = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/ai/generations/'.$draft->json('id').'/approve')
            ->assertOk();
        $approved->assertJsonPath('status', 'materialized');
        $this->assertNotNull($approved->json('workout_program_version_id'));
        $this->assertSame(0, WorkoutAssignment::query()->count());

        $meal = $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/generations', [
            'kind' => 'meal_plan',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'غذا بده',
        ])->assertCreated();
        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/ai/generations/'.$meal->json('id').'/reject')
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('meal_plan_version_id', null);

        $this->bearer($blockedAthlete['token'])->postJson('/api/v1/athlete/ai/conversations', [
            'coaching_relationship_id' => $blockedAthlete['relationship']->id,
            'body' => 'سلام',
        ])->assertForbidden()->assertJsonPath('code', 'feature_unavailable');

        $this->bearer($athlete['token'])->postJson('/api/v1/athlete/ai/conversations', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'سلام',
        ])->assertCreated()->assertJsonPath('audience', 'athlete');
    }

    /**
     * @param  list<string>  $features
     * @return array{user: User, profile: \App\Models\CoachProfile, token: string}
     */
    private function coachWithAi(array $features, int $quota, string $name = 'مربی'): array
    {
        $admin = $this->admin();
        $coach = $this->coach($name);
        $planId = $this->createPlan($admin['token'], [
            'code' => 'ai-'.$coach['profile']->id,
            'features' => $features,
            'ai_request_quota' => $quota,
        ])->assertCreated()->json('id');
        $orderId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json('id');
        $attemptId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => 'ai-'.$coach['profile']->id,
        ])->assertCreated()->json('id');
        $this->confirm($attemptId, 1000, 'ai-'.$coach['profile']->id)->assertOk();

        return $coach;
    }

    private function settings(string $token): void
    {
        $this->bearer($token)->postJson('/api/v1/coach/ai/settings', [
            'methodology' => 'حجم',
            'preferences' => 'سه روز',
            'constraints' => 'بدون درد',
        ])->assertCreated();
    }

    /**
     * @param  array{user: User, profile: \App\Models\CoachProfile, token: string}  $coach
     * @return array{user: User, profile: AthleteProfile, relationship: CoachingRelationship, token: string}
     */
    private function athleteOf(array $coach): array
    {
        $user = User::factory()->create();
        $profile = AthleteProfile::query()->create([
            'user_id' => $user->id,
            'display_name' => 'ورزشکار',
            'status' => 'active',
        ]);
        $relationship = CoachingRelationship::query()->create([
            'coach_profile_id' => $coach['profile']->id,
            'athlete_profile_id' => $profile->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        return [
            'user' => $user,
            'profile' => $profile,
            'relationship' => $relationship,
            'token' => $user->createToken('mobile')->plainTextToken,
        ];
    }
}
