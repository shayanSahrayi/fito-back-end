<?php

namespace Tests\Feature\Nutrition;

use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\User;
use Tests\Feature\Commerce\CommerceTestCase;

class MealPlanTest extends CommerceTestCase
{
    public function test_totals_use_half_up_math_for_grams_and_units(): void
    {
        $admin = $this->admin();
        $coach = $this->coachWithNutrition();
        $grams = $this->food($admin['token'], [
            'name' => 'برنج',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 1,
        ]);
        $units = $this->food($admin['token'], [
            'name' => 'تخم‌مرغ',
            'basis' => 'per_unit',
            'unit_code' => 'piece',
            'unit_label' => 'عدد',
            'energy_kcal' => 200,
        ]);

        $plan = $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-plans', [
            'title' => 'حجم',
            'days' => [[
                'position' => 0,
                'title' => 'شنبه',
                'meals' => [
                    [
                        'position' => 0,
                        'meal_type' => 'lunch',
                        'title' => 'ناهار',
                        'items' => [[
                            'position' => 0,
                            'food_id' => $grams,
                            'quantity_g' => 50,
                        ]],
                    ],
                    [
                        'position' => 1,
                        'meal_type' => 'snack',
                        'title' => null,
                        'items' => [
                            [
                                'position' => 0,
                                'food_id' => $grams,
                                'quantity_g' => 49,
                            ],
                            [
                                'position' => 1,
                                'food_id' => $units,
                                'quantity_unit_milli' => 1500,
                            ],
                        ],
                    ],
                ],
            ]],
        ])->assertCreated();

        $plan->assertJsonPath('draft.days.0.meals.0.items.0.totals.energy_kcal', 1);
        $plan->assertJsonPath('draft.days.0.meals.1.items.0.totals.energy_kcal', 0);
        $plan->assertJsonPath('draft.days.0.meals.1.items.1.totals.energy_kcal', 300);
        $plan->assertJsonPath('draft.days.0.meals.1.totals.energy_kcal', 300);
        $plan->assertJsonPath('draft.totals.energy_kcal', 301);
    }

    public function test_a_later_revision_does_not_change_a_published_plan(): void
    {
        $admin = $this->admin();
        $coach = $this->coachWithNutrition();
        $athlete = $this->athleteOf($coach);
        $foodId = $this->food($admin['token'], [
            'name' => 'مرغ',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 1,
        ]);
        $created = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans', $this->plan($foodId, 50))
            ->assertCreated();
        $planId = $created->json('id');
        $revisionId = $created->json('draft.days.0.meals.0.items.0.food_nutrient_revision_id');

        $published = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans/'.$planId.'/publish')
            ->assertOk();
        $versionId = $published->json('current_version_id');

        $assignmentId = $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'meal_plan_version_id' => $versionId,
            'starts_on' => '2026-10-12',
        ])->assertCreated()->json('id');

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/foods/'.$foodId.'/revisions', $this->nutrients([
                'basis' => 'per_100g',
                'unit_code' => 'g',
                'unit_label' => 'گرم',
                'energy_kcal' => 500,
            ]))
            ->assertForbidden()
            ->assertJsonPath('code', 'food_locked');

        $revised = $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/foods/'.$foodId.'/revisions', $this->nutrients([
                'basis' => 'per_100g',
                'unit_code' => 'g',
                'unit_label' => 'گرم',
                'energy_kcal' => 500,
            ]))
            ->assertOk();

        $this->assertNotSame($revisionId, $revised->json('current_revision_id'));

        $seen = $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/meal-assignments/'.$assignmentId)
            ->assertOk();

        $seen->assertJsonPath('version.days.0.meals.0.items.0.food_nutrient_revision_id', $revisionId);
        $seen->assertJsonPath('version.totals.energy_kcal', 1);

        $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/foods/'.$foodId.'/archive')
            ->assertOk()
            ->assertJsonPath('status', 'archived');

        $draft = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans/'.$planId.'/draft')
            ->assertOk();

        $draft->assertJsonPath('draft.days.0.meals.0.items.0.food_nutrient_revision_id', $revisionId);
        $draft->assertJsonPath('draft.totals.energy_kcal', 1);
        $draft->assertJsonPath('current_version.totals.energy_kcal', 1);

        $this->bearer($coach['token'])
            ->patchJson('/api/v1/coach/meal-plans/'.$planId, $this->plan($foodId, 50))
            ->assertStatus(422)
            ->assertJsonPath('code', 'food_not_available');
    }

    public function test_publish_freezes_the_assigned_version(): void
    {
        $admin = $this->admin();
        $coach = $this->coachWithNutrition();
        $athlete = $this->athleteOf($coach);
        $foodId = $this->food($admin['token'], [
            'name' => 'ماهی',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 100,
        ]);
        $planId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans', $this->plan($foodId, 100))
            ->assertCreated()
            ->json('id');
        $versionId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans/'.$planId.'/publish')
            ->assertOk()
            ->json('current_version_id');
        $assignmentId = $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'meal_plan_version_id' => $versionId,
            'starts_on' => '2026-10-12',
            'ends_on' => '2026-10-20',
        ])->assertCreated()->json('id');

        $this->bearer($coach['token'])
            ->patchJson('/api/v1/coach/meal-plans/'.$planId, ['title' => 'بعد'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'draft_required');

        $body = $this->plan($foodId, 100);
        $body['days'][0]['title'] = 'جدید';
        $opened = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans/'.$planId.'/draft')
            ->assertOk();
        $opened->assertJsonPath('status', 'published');
        $opened->assertJsonPath('current_version_id', $versionId);

        $this->bearer($coach['token'])
            ->patchJson('/api/v1/coach/meal-plans/'.$planId, $body)
            ->assertOk()
            ->assertJsonPath('draft.days.0.title', 'جدید');

        $old = $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/meal-assignments/'.$assignmentId)
            ->assertOk();
        $old->assertJsonPath('meal_plan_version_id', $versionId);
        $old->assertJsonPath('version.days.0.title', 'شنبه');
        $old->assertJsonPath('version.totals.energy_kcal', 100);
    }

    public function test_another_coachs_private_food_is_hidden(): void
    {
        $owner = $this->coachWithNutrition('مالک');
        $other = $this->coachWithNutrition('دیگر');
        $foodId = $this->bearer($owner['token'])->postJson('/api/v1/coach/foods', [
            'name' => 'خوراک خصوصی',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 10,
            'protein_mg' => 0,
            'carbohydrate_mg' => 0,
            'fat_mg' => 0,
        ])->assertCreated()->json('id');

        $ids = collect($this->bearer($other['token'])->getJson('/api/v1/coach/foods')->assertOk()->json('foods'))
            ->pluck('id');
        $this->assertFalse($ids->contains($foodId));

        $this->bearer($other['token'])
            ->postJson('/api/v1/coach/meal-plans', $this->plan($foodId, 100))
            ->assertStatus(422)
            ->assertJsonPath('code', 'food_not_available');
    }

    public function test_the_wrong_quantity_column_is_rejected(): void
    {
        $admin = $this->admin();
        $coach = $this->coachWithNutrition();
        $foodId = $this->food($admin['token'], [
            'name' => 'نان',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 10,
        ]);
        $body = $this->plan($foodId, 100);
        $body['days'][0]['meals'][0]['items'][0]['quantity_unit_milli'] = 1000;

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans', $body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'irrelevant_measure');
    }

    public function test_ending_the_relationship_ends_the_meal_assignment(): void
    {
        $admin = $this->admin();
        $coach = $this->coachWithNutrition();
        $athlete = $this->athleteOf($coach);
        $foodId = $this->food($admin['token'], [
            'name' => 'عدس',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 20,
        ]);
        $planId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans', $this->plan($foodId, 100))
            ->assertCreated()
            ->json('id');
        $versionId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans/'.$planId.'/publish')
            ->assertOk()
            ->json('current_version_id');
        $assignmentId = $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'meal_plan_version_id' => $versionId,
            'starts_on' => '2026-10-12',
        ])->assertCreated()->json('id');

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/end', [])
            ->assertOk()
            ->assertJsonPath('status', 'ended');

        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/meal-assignments/'.$assignmentId)
            ->assertOk()
            ->assertJsonPath('status', 'ended')
            ->assertJsonPath('version.totals.energy_kcal', 20);

        $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'meal_plan_version_id' => $versionId,
            'starts_on' => '2026-10-13',
        ])->assertStatus(422)->assertJsonPath('code', 'relationship_not_active');
    }

    public function test_food_catalog_is_open_but_meal_plans_need_the_feature(): void
    {
        $coach = $this->coach();

        $this->bearer($coach['token'])->postJson('/api/v1/coach/foods', [
            'name' => 'ماست',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 0,
            'protein_mg' => 0,
            'carbohydrate_mg' => 0,
            'fat_mg' => 0,
        ])->assertCreated();

        $admin = $this->admin();
        $foodId = $this->food($admin['token'], [
            'name' => 'بانک',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 1,
        ]);

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans', $this->plan($foodId, 50))
            ->assertForbidden()
            ->assertJsonPath('code', 'no_access');

        $planId = $this->createPlan($admin['token'], [
            'code' => 'work-only-'.$coach['profile']->id,
            'features' => ['workout_programs'],
        ])->assertCreated()->json('id');
        $orderId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json('id');
        $attemptId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => 'work-only-'.$coach['profile']->id,
        ])->assertCreated()->json('id');
        $this->confirm($attemptId, 1000, 'work-only-'.$coach['profile']->id)->assertOk();

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans', $this->plan($foodId, 50))
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_unavailable');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function food(string $token, array $overrides): int
    {
        return $this->bearer($token)->postJson('/api/v1/admin/foods', $this->nutrients($overrides))->assertCreated()->json('id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function nutrients(array $overrides): array
    {
        return array_merge([
            'protein_mg' => 0,
            'carbohydrate_mg' => 0,
            'fat_mg' => 0,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(int $foodId, int $grams): array
    {
        return [
            'title' => 'برنامه',
            'days' => [[
                'position' => 0,
                'title' => 'شنبه',
                'meals' => [[
                    'position' => 0,
                    'meal_type' => 'lunch',
                    'title' => 'ناهار',
                    'items' => [[
                        'position' => 0,
                        'food_id' => $foodId,
                        'quantity_g' => $grams,
                    ]],
                ]],
            ]],
        ];
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

    /**
     * @return array{user: User, profile: \App\Models\CoachProfile, token: string}
     */
    private function coachWithNutrition(string $name = 'مربی'): array
    {
        $admin = $this->admin();
        $coach = $this->coach($name);
        $planId = $this->createPlan($admin['token'], [
            'code' => 'nut-'.$coach['profile']->id,
            'features' => ['nutrition_programs'],
        ])->assertCreated()->json('id');
        $orderId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json('id');
        $attemptId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => 'nut-'.$coach['profile']->id,
        ])->assertCreated()->json('id');
        $this->confirm($attemptId, 1000, 'nut-'.$coach['profile']->id)->assertOk();

        return $coach;
    }
}
