<?php

namespace Tests\Feature\Acceptance;

use App\Domain\Coaching\AthleteWelcomeNotice;
use App\Domain\Identity\NationalCode;
use App\Domain\Identity\OtpCodeGenerator;
use App\Models\AccessPeriod;
use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\Exercise;
use App\Models\FoodNutrientRevision;
use App\Models\MealAssignment;
use App\Models\OtpChallenge;
use App\Models\Payment;
use App\Models\SignupDraft;
use App\Models\User;
use App\Models\WorkoutAssignment;
use App\Models\WorkoutDayExercise;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Commerce\CommerceTestCase;

class ScenarioJourneyTest extends CommerceTestCase
{
    private RecordingWelcomeNotice $notice;

    private int $serial = 1000000;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->app->bind(OtpCodeGenerator::class, fn () => new class implements OtpCodeGenerator
        {
            public function generate(): string
            {
                return '54321';
            }
        });
        $this->notice = new RecordingWelcomeNotice;
        $this->app->instance(AthleteWelcomeNotice::class, $this->notice);
    }

    public function test_scenario_1_signup_creates_only_the_coach_profile(): void
    {
        $phone = $this->phone();
        $nationalCode = $this->nationalCode();

        $this->postJson('/api/v1/auth/otp', [
            'phone' => $phone,
            'purpose' => 'signup',
        ])->assertOk();

        $this->assertDatabaseHas('signup_drafts', [
            'phone' => $phone,
            'open_phone' => $phone,
        ]);
        $this->assertDatabaseHas('otp_challenges', [
            'phone' => $phone,
            'purpose' => 'signup',
            'consumed_at' => null,
        ]);
        $this->assertDatabaseCount('users', 0);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'purpose' => 'signup',
            'code' => '54321',
        ])->assertOk()->assertJsonPath('phone_verified', true);

        $this->assertNotNull(SignupDraft::query()->where('phone', $phone)->firstOrFail()->phone_verified_at);
        $this->assertNotNull(OtpChallenge::query()->where('phone', $phone)->where('purpose', 'signup')->firstOrFail()->consumed_at);

        $created = $this->postJson('/api/v1/auth/signup', [
            'phone' => $phone,
            'national_code' => $nationalCode,
            'display_name' => 'شایان',
            'panel' => 'coach',
        ])->assertCreated();

        $draft = SignupDraft::query()->where('created_user_id', $created->json('user_id'))->firstOrFail();
        $this->assertNull($draft->open_phone);
        $this->assertNotNull($draft->consumed_at);
        $this->assertDatabaseCount('coach_profiles', 1);
        $this->assertDatabaseCount('athlete_profiles', 0);
        $this->assertDatabaseCount('orders', 0);

        $this->postJson('/api/v1/auth/otp', [
            'phone' => $phone,
            'purpose' => 'login',
        ])->assertOk();
        $web = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'purpose' => 'login',
            'code' => '54321',
            'client' => 'web',
        ])->assertOk();
        $web->assertJsonMissingPath('token');
        $this->assertAuthenticatedAs(User::query()->findOrFail($created->json('user_id')));

        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/otp', [
            'phone' => $phone,
            'purpose' => 'login',
        ])->assertOk();
        $mobile = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'purpose' => 'login',
            'code' => '54321',
            'client' => 'mobile',
        ])->assertOk();
        $this->assertIsString($mobile->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->bearer($mobile->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('profiles.coach.id', $created->json('profile_id'))
            ->assertJsonPath('profiles.athlete', null);

        $this->withoutToken();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();

        $this->postJson('/api/v1/auth/otp', [
            'phone' => $phone,
            'purpose' => 'signup',
        ])->assertStatus(422)->assertJsonPath('code', 'phone_taken');
        $this->assertSame(1, User::query()->where('phone', $phone)->count());
    }

    public function test_scenario_2_payment_opens_one_period_and_keeps_the_order_snapshot(): void
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $other = $this->coach('دیگر');
        $athlete = User::factory()->create();
        AthleteProfile::query()->create([
            'user_id' => $athlete->id,
            'display_name' => 'ورزشکار',
            'status' => 'active',
        ]);
        $planId = $this->createPlan($admin['token'], [
            'code' => 'f11-pay-'.$coach['profile']->id,
            'features' => ['workout_programs'],
        ])->assertCreated()->json('id');

        $opened = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated();
        $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertOk()->assertJsonPath('id', $opened->json('id'));

        $this->bearer($other['token'])->postJson('/api/v1/coach/orders/'.$opened->json('id').'/attempts', [
            'idempotency_key' => 'stolen',
        ])->assertNotFound()->assertJsonPath('code', 'order_not_found');

        $this->bearer($athlete->createToken('mobile')->plainTextToken)
            ->postJson('/api/v1/coach/orders', ['plan_id' => $planId])
            ->assertForbidden()
            ->assertJsonPath('code', 'coach_profile_required');

        $attemptId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$opened->json('id').'/attempts', [
            'idempotency_key' => 'pay-wrong',
        ])->assertCreated()->json('id');
        $this->confirm($attemptId, 1001, 'ref-wrong')->assertStatus(422)->assertJsonPath('code', 'amount_mismatch');
        $this->confirm($attemptId, 1001, 'ref-wrong-again')->assertStatus(422)->assertJsonPath('code', 'amount_mismatch');
        $this->assertSame(0, Payment::query()->count());

        $paidAttempt = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$opened->json('id').'/attempts', [
            'idempotency_key' => 'pay-ok',
        ])->assertCreated()->json('id');
        $paid = $this->confirm($paidAttempt, 1000, 'ref-ok')->assertOk();
        $again = $this->confirm($paidAttempt, 1000, 'ref-ok')->assertOk();
        $this->assertSame($paid->json('payment_id'), $again->json('payment_id'));
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, AccessPeriod::query()->count());
        $this->assertDatabaseHas('orders', [
            'id' => $opened->json('id'),
            'status' => 'paid',
            'price_irr' => 1000,
            'open_checkout_coach_id' => null,
        ]);

        $this->bearer($admin['token'])->patchJson('/api/v1/admin/plans/'.$planId, [
            'price_irr' => 5,
            'features' => ['body_analysis'],
        ])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $opened->json('id'), 'price_irr' => 1000]);
        $this->assertDatabaseHas('order_features', [
            'order_id' => $opened->json('id'),
            'feature_key' => 'workout_programs',
        ]);
        $this->assertDatabaseMissing('order_features', [
            'order_id' => $opened->json('id'),
            'feature_key' => 'body_analysis',
        ]);
    }

    public function test_scenario_3_a_new_phone_becomes_an_athlete_and_locks_capacity(): void
    {
        $blocked = $this->coach('بدون دوره');
        $this->bearer($blocked['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $this->phone(),
            'national_code' => $this->nationalCode(),
            'display_name' => 'کسی',
        ])->assertForbidden()->assertJsonPath('code', 'no_access');

        $coach = $this->coachWith(['workout_programs'], 1);
        $phone = $this->phone();
        $nationalCode = $this->nationalCode();
        $created = $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $phone,
            'national_code' => $nationalCode,
            'display_name' => 'ورزشکار نو',
        ])->assertCreated();

        $athlete = User::query()->where('phone', $phone)->firstOrFail();
        $this->assertTrue($athlete->password_is_initial);
        $this->assertTrue(Hash::check($nationalCode, (string) $athlete->password));
        $this->assertSame([[$phone, $nationalCode]], $this->notice->messages);
        $this->assertDatabaseHas('coaching_relationship_events', [
            'coaching_relationship_id' => $created->json('relationship.id'),
            'event_type' => 'started',
        ]);
        $this->assertSame(1, CoachingRelationship::query()->where('status', 'active')->count());

        $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $phone,
            'national_code' => $nationalCode,
            'display_name' => 'نام دیگر',
        ])->assertOk()->assertJsonPath('relationship.id', $created->json('relationship.id'));
        $this->assertSame(1, User::query()->where('phone', $phone)->count());

        $secondPhone = $this->phone();
        $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $secondPhone,
            'national_code' => $this->nationalCode(),
            'display_name' => 'دوم',
        ])->assertStatus(422)->assertJsonPath('code', 'capacity_full');
        $this->assertDatabaseMissing('users', ['phone' => $secondPhone]);
    }

    public function test_scenario_4_an_existing_athlete_connects_and_can_return(): void
    {
        $first = $this->coachWith(['workout_programs'], 2);
        $second = $this->coachWith(['workout_programs'], 2, 'مربی دوم');
        $athlete = User::factory()->create(['password' => 'secret-pass']);
        $hash = $athlete->refresh()->password;
        $stranger = User::factory()->create();

        $invited = $this->bearer($first['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $athlete->phone,
            'national_code' => $athlete->national_code,
            'display_name' => 'وصل',
        ])->assertCreated();
        $this->assertNull($invited->json('relationship'));
        $this->assertSame($hash, $athlete->refresh()->password);

        $this->postJson('/api/v1/auth/otp', [
            'phone' => $stranger->phone,
            'purpose' => 'invitation',
            'invitation_id' => $invited->json('invitation.id'),
        ])->assertNotFound()->assertJsonPath('code', 'invitation_not_found');

        $this->acceptInvitation($athlete->phone, $invited->json('invitation.id'));
        $relationshipId = CoachingRelationship::query()->where('coach_profile_id', $first['profile']->id)->value('id');
        $this->assertNotNull($athlete->refresh()->athleteProfile);

        $this->bearer($first['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $athlete->phone,
            'national_code' => $athlete->national_code,
            'display_name' => 'دوباره',
        ])->assertOk()->assertJsonPath('relationship.id', $relationshipId);
        $this->assertSame(1, CoachingRelationship::query()->count());

        $otherInvite = $this->bearer($second['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $athlete->phone,
            'national_code' => $athlete->national_code,
            'display_name' => 'وصل',
        ])->assertCreated();
        $this->acceptInvitation($athlete->phone, $otherInvite->json('invitation.id'));
        $this->assertSame(2, CoachingRelationship::query()->where('status', 'active')->count());

        $this->bearer($first['token'])->postJson('/api/v1/coach/relationships/'.$relationshipId.'/end', [
            'end_reason' => 'پایان',
        ])->assertOk();
        $returned = $this->bearer($first['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $athlete->phone,
            'national_code' => $athlete->national_code,
            'display_name' => 'بازگشت',
        ])->assertCreated();
        $this->assertNull($returned->json('relationship'));
        $this->acceptInvitation($athlete->phone, $returned->json('invitation.id'));
        $this->assertDatabaseHas('coaching_relationships', [
            'id' => $relationshipId,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('coaching_relationship_events', [
            'coaching_relationship_id' => $relationshipId,
            'event_type' => 'reactivated',
        ]);
        $this->assertSame($hash, $athlete->refresh()->password);
    }

    public function test_scenario_5_a_published_workout_version_stays_on_its_assignment(): void
    {
        $coach = $this->coachWith(['workout_programs']);
        $other = $this->coachWith(['workout_programs'], 5, 'دیگر');
        $athlete = $this->athleteOf($coach);
        $exerciseId = $this->exercise($coach['user']);
        $privateId = $this->exercise($other['user'], $other['profile']->id, 'خصوصی');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/workout-programs', $this->programPayload($privateId))
            ->assertStatus(422)
            ->assertJsonPath('code', 'exercise_not_available');

        $programId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs', $this->programPayload($exerciseId))
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->json('id');
        $published = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs/'.$programId.'/publish')
            ->assertOk();
        $firstVersionId = $published->json('current_version_id');

        $this->bearer($coach['token'])
            ->patchJson('/api/v1/coach/workout-programs/'.$programId, $this->programPayload($exerciseId))
            ->assertStatus(422)
            ->assertJsonPath('code', 'draft_required');

        $assignment = $this->bearer($coach['token'])->postJson('/api/v1/coach/workout-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'workout_program_version_id' => $firstVersionId,
            'starts_on' => '2026-10-12',
        ])->assertCreated();

        $this->bearer($athlete['token'])->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'program_assigned',
                'subject_type' => 'workout_assignment',
                'subject_id' => $assignment->json('id'),
            ]);

        $this->bearer($other['token'])->postJson('/api/v1/coach/workout-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'workout_program_version_id' => $firstVersionId,
            'starts_on' => '2026-10-12',
        ])->assertNotFound()->assertJsonPath('code', 'relationship_not_found');

        $replacement = $this->programPayload($exerciseId);
        $replacement['days'][0]['title'] = 'فول بادی';
        $this->bearer($coach['token'])->postJson('/api/v1/coach/workout-programs/'.$programId.'/draft')->assertOk();
        $this->bearer($coach['token'])->patchJson('/api/v1/coach/workout-programs/'.$programId, $replacement)->assertOk();
        $this->bearer($coach['token'])->postJson('/api/v1/coach/workout-programs/'.$programId.'/publish')->assertOk();

        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/workout-assignments/'.$assignment->json('id'))
            ->assertOk()
            ->assertJsonPath('workout_program_version_id', $firstVersionId)
            ->assertJsonPath('version.days.0.title', 'شنبه پا');
    }

    public function test_scenario_6_sets_stay_separate_and_only_a_stronger_result_is_a_record(): void
    {
        $coach = $this->coachWith(['workout_programs']);
        $athlete = $this->athleteOf($coach);
        $other = $this->athleteOf($coach);
        $exerciseId = $this->exercise($coach['user']);
        $versionId = $this->publishProgram($coach, $exerciseId);
        $dayExercise = WorkoutDayExercise::query()->whereHas('day', fn ($query) => $query->where('workout_program_version_id', $versionId))->firstOrFail();
        $assignment = WorkoutAssignment::query()->create([
            'coaching_relationship_id' => $athlete['relationship']->id,
            'athlete_profile_id' => $athlete['profile']->id,
            'workout_program_version_id' => $versionId,
            'assigned_by_user_id' => $coach['user']->id,
            'starts_on' => '2026-10-12',
            'status' => 'active',
        ]);

        $session = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions', [
            'source' => 'prescribed',
            'performed_on' => '2026-10-12',
            'workout_assignment_id' => $assignment->id,
            'workout_day_id' => $dayExercise->workout_day_id,
        ])->assertCreated();
        $this->assertSame($athlete['relationship']->id, $session->json('coaching_relationship_id'));

        foreach ([10, 9, 8] as $index => $reps) {
            $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions/'.$session->json('id').'/sets', [
                'workout_day_exercise_id' => $dayExercise->id,
                'set_index' => $index + 1,
                'reps' => $reps,
            ])->assertCreated()->assertJsonPath('reps', $reps);
        }

        $this->bearer($other['token'])->postJson('/api/v1/athlete/workout-sessions/'.$session->json('id').'/sets', [
            'workout_day_exercise_id' => $dayExercise->id,
            'set_index' => 4,
            'reps' => 1,
        ])->assertNotFound()->assertJsonPath('code', 'session_not_found');

        WorkoutDayExercise::query()->whereKey($dayExercise->id)->update(['reps_min' => 99]);
        $shown = $this->bearer($athlete['token'])->getJson('/api/v1/athlete/workout-sessions/'.$session->json('id'))->assertOk();
        $this->assertSame($dayExercise->workout_day_id, $shown->json('workout_day_id'));
        $this->assertSame([10, 9, 8], collect($shown->json('sets'))->pluck('reps')->all());

        $freeId = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions', [
            'source' => 'athlete_free',
            'performed_on' => '2026-10-12',
            'coaching_relationship_id' => $athlete['relationship']->id,
        ])->assertCreated()->json('id');
        $record = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions/'.$freeId.'/sets', [
            'exercise_id' => $exerciseId,
            'set_index' => 1,
            'reps' => 5,
            'weight_g' => 100000,
        ])->assertCreated();
        $this->assertSame('max_weight_g', $record->json('records.0.metric'));
        $this->assertSame('estimated_1rm_g', $record->json('records.1.metric'));
        $this->assertSame('epley_v1', $record->json('records.1.formula_code'));

        $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions/'.$freeId.'/sets', [
            'exercise_id' => $exerciseId,
            'set_index' => 2,
            'reps' => 5,
            'weight_g' => 100000,
        ])->assertCreated()->assertJsonCount(0, 'records');

        $this->bearer($athlete['token'])->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment(['type' => 'record_achieved']);
        $this->bearer($coach['token'])->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment(['type' => 'record_achieved']);
    }

    public function test_scenario_7_a_meal_revision_does_not_rewrite_the_published_plan(): void
    {
        $admin = $this->admin();
        $coach = $this->coachWith(['nutrition_programs']);
        $other = $this->coachWith(['nutrition_programs'], 5, 'دیگر');
        $athlete = $this->athleteOf($coach);
        $foodId = $this->bearer($admin['token'])->postJson('/api/v1/admin/foods', [
            'name' => 'برنج',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 100,
            'protein_mg' => 0,
            'carbohydrate_mg' => 0,
            'fat_mg' => 0,
        ])->assertCreated()->json('id');
        $privateId = $this->bearer($other['token'])->postJson('/api/v1/coach/foods', [
            'name' => 'خصوصی',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 10,
            'protein_mg' => 0,
            'carbohydrate_mg' => 0,
            'fat_mg' => 0,
        ])->assertCreated()->json('id');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-plans', $this->mealPayload($privateId, 100))
            ->assertStatus(422)
            ->assertJsonPath('code', 'food_not_available');

        $planId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans', $this->mealPayload($foodId, 100))
            ->assertCreated()
            ->json('id');
        $shown = $this->bearer($coach['token'])->getJson('/api/v1/coach/meal-plans/'.$planId)->assertOk();
        $revisionId = $shown->json('draft.days.0.meals.0.items.0.food_nutrient_revision_id');
        $shown->assertJsonPath('draft.days.0.meals.0.totals.energy_kcal', 100);

        $versionId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans/'.$planId.'/publish')
            ->assertOk()
            ->json('current_version_id');
        $assignmentId = $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'meal_plan_version_id' => $versionId,
            'starts_on' => '2026-10-12',
        ])->assertCreated()->json('id');

        $this->bearer($other['token'])->postJson('/api/v1/coach/meal-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'meal_plan_version_id' => $versionId,
            'starts_on' => '2026-10-12',
        ])->assertNotFound()->assertJsonPath('code', 'relationship_not_found');

        $this->bearer($admin['token'])->postJson('/api/v1/admin/foods/'.$foodId.'/revisions', [
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 500,
            'protein_mg' => 0,
            'carbohydrate_mg' => 0,
            'fat_mg' => 0,
        ])->assertOk();

        $this->assertSame(100, (int) FoodNutrientRevision::query()->findOrFail($revisionId)->energy_kcal);
        $this->assertSame(2, FoodNutrientRevision::query()->where('food_id', $foodId)->count());
        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/meal-assignments/'.$assignmentId)
            ->assertOk()
            ->assertJsonPath('version.days.0.meals.0.items.0.food_nutrient_revision_id', $revisionId)
            ->assertJsonPath('version.totals.energy_kcal', 100);
    }

    public function test_scenario_8_body_observations_keep_only_measured_values(): void
    {
        $coach = $this->coach();
        $other = $this->coach('دیگر');
        $athlete = $this->athleteOf($coach);

        $before = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/body-observations', [
            'measured_at' => '2026-10-01T08:00:00Z',
            'visibility' => 'relationship',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'metrics' => ['waist_cm' => 80],
        ])->assertCreated();
        $this->assertArrayNotHasKey('weight_kg', $before->json('metrics'));
        $this->assertArrayNotHasKey('bmi_calculated', $before->json('metrics'));

        $after = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/body-observations', [
            'measured_at' => '2026-10-12T08:00:00Z',
            'visibility' => 'relationship',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'metrics' => ['weight_kg' => 70, 'height_cm' => 175],
        ])->assertCreated();
        $after->assertJsonPath('metrics.bmi_calculated', '22.86');
        $this->assertArrayNotHasKey('waist_cm', $after->json('metrics'));

        $compared = $this->bearer($athlete['token'])->getJson(
            '/api/v1/athlete/body-observations/compare?before_id='.$before->json('id').'&after_id='.$after->json('id')
        )->assertOk();
        $rows = collect($compared->json('metrics'))->keyBy('metric');
        $this->assertSame(80, $rows['waist_cm']['before']);
        $this->assertNull($rows['waist_cm']['after']);
        $this->assertSame('22.86', $rows['bmi_calculated']['after']);

        $this->bearer($coach['token'])->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'body_observation_recorded',
                'subject_id' => $after->json('id'),
            ]);

        $this->bearer($other['token'])
            ->getJson('/api/v1/coach/body-observations/'.$after->json('id'))
            ->assertNotFound()
            ->assertJsonPath('code', 'observation_not_found');
    }

    public function test_scenario_9_an_unapproved_ai_proposal_does_not_assign_a_program(): void
    {
        $coach = $this->coachWith(['ai_coach_assistant', 'ai_athlete_access', 'workout_programs'], 5, 'مربی', 3);
        $other = $this->coachWith(['ai_coach_assistant'], 5, 'دیگر', 2);
        $blocked = $this->coachWith(['ai_coach_assistant'], 5, 'بدون ورزشکار', 2);
        $athlete = $this->athleteOf($coach);
        $blockedAthlete = $this->athleteOf($blocked);
        $this->exercise($coach['user']);

        $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/generations', [
            'kind' => 'workout_program',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'برنامه',
        ])->assertStatus(422)->assertJsonPath('code', 'onboarding_required');
        $this->assertSame(0, (int) AccessPeriod::query()->where('coach_profile_id', $coach['profile']->id)->value('ai_requests_used'));

        $this->settings($coach['token']);
        $this->settings($other['token']);
        $this->settings($blocked['token']);
        $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/knowledge', [
            'title' => 'روش',
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

        $draft = $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/generations', [
            'kind' => 'workout_program',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'برنامه',
        ])->assertCreated();
        $this->assertSame('draft', $draft->json('status'));
        $this->assertNull($draft->json('workout_program_version_id'));
        $this->assertSame(0, WorkoutAssignment::query()->count());

        $approved = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/ai/generations/'.$draft->json('id').'/approve')
            ->assertOk();
        $this->assertSame('materialized', $approved->json('status'));
        $this->assertNotNull($approved->json('workout_program_version_id'));
        $this->assertSame(0, WorkoutAssignment::query()->count());

        $rejected = $this->bearer($coach['token'])->postJson('/api/v1/coach/ai/generations', [
            'kind' => 'progress_review',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'مرور',
        ])->assertCreated();
        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/ai/generations/'.$rejected->json('id').'/reject')
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('workout_program_version_id', null);

        $this->bearer($blockedAthlete['token'])->postJson('/api/v1/athlete/ai/conversations', [
            'coaching_relationship_id' => $blockedAthlete['relationship']->id,
            'body' => 'سلام',
        ])->assertForbidden()->assertJsonPath('code', 'feature_unavailable');
    }

    public function test_scenario_10_ending_the_relationship_keeps_history_and_closes_photos(): void
    {
        $admin = $this->admin();
        $coach = $this->coachWith(['workout_programs', 'nutrition_programs'], 1);
        $other = $this->coach('غریبه');
        $phone = $this->phone();
        $nationalCode = $this->nationalCode();
        $created = $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $phone,
            'national_code' => $nationalCode,
            'display_name' => 'ورزشکار',
        ])->assertCreated();
        $relationshipId = $created->json('relationship.id');
        $login = $this->postJson('/api/v1/auth/password', [
            'phone' => $phone,
            'password' => $nationalCode,
            'client' => 'mobile',
        ])->assertOk();
        $athleteToken = $login->json('token');

        $exerciseId = $this->exercise($coach['user']);
        $versionId = $this->publishProgram($coach, $exerciseId);
        $assignmentId = $this->bearer($coach['token'])->postJson('/api/v1/coach/workout-assignments', [
            'coaching_relationship_id' => $relationshipId,
            'workout_program_version_id' => $versionId,
            'starts_on' => '2026-10-12',
        ])->assertCreated()->json('id');

        $foodId = $this->bearer($admin['token'])->postJson('/api/v1/admin/foods', [
            'name' => 'نان',
            'basis' => 'per_100g',
            'unit_code' => 'g',
            'unit_label' => 'گرم',
            'energy_kcal' => 20,
            'protein_mg' => 0,
            'carbohydrate_mg' => 0,
            'fat_mg' => 0,
        ])->assertCreated()->json('id');
        $mealId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans', $this->mealPayload($foodId, 100))
            ->assertCreated()
            ->json('id');
        $mealVersionId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans/'.$mealId.'/publish')
            ->assertOk()
            ->json('current_version_id');
        $mealAssignmentId = $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-assignments', [
            'coaching_relationship_id' => $relationshipId,
            'meal_plan_version_id' => $mealVersionId,
            'starts_on' => '2026-10-12',
        ])->assertCreated()->json('id');

        $threadId = $this->bearer($athleteToken)->postJson('/api/v1/athlete/feedback-threads', [
            'coaching_relationship_id' => $relationshipId,
            'body' => 'پیام می‌ماند',
        ])->assertCreated()->json('id');
        $measuredAt = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $observationId = $this->bearer($athleteToken)->postJson('/api/v1/athlete/body-observations', [
            'measured_at' => $measuredAt,
            'visibility' => 'relationship',
            'coaching_relationship_id' => $relationshipId,
            'metrics' => ['weight_kg' => 70],
        ])->assertCreated()->json('id');
        $photoId = $this->bearer($athleteToken)->post('/api/v1/athlete/body-photos', [
            'photo' => UploadedFile::fake()->image('body.jpg'),
            'taken_at' => $measuredAt,
            'coaching_relationship_id' => $relationshipId,
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $sessionId = $this->bearer($athleteToken)->postJson('/api/v1/athlete/workout-sessions', [
            'source' => 'athlete_free',
            'performed_on' => '2026-10-12',
            'coaching_relationship_id' => $relationshipId,
        ])->assertCreated()->json('id');
        $this->bearer($athleteToken)->postJson('/api/v1/athlete/workout-sessions/'.$sessionId.'/sets', [
            'exercise_id' => $exerciseId,
            'set_index' => 1,
            'reps' => 8,
        ])->assertCreated();

        $this->bearer($coach['token'])->postJson('/api/v1/coach/relationships/'.$relationshipId.'/end', [
            'end_reason' => 'پایان',
        ])->assertOk()->assertJsonPath('status', 'ended');

        $this->assertDatabaseHas('coaching_relationship_events', [
            'coaching_relationship_id' => $relationshipId,
            'event_type' => 'ended',
        ]);
        $this->assertSame('ended', WorkoutAssignment::query()->findOrFail($assignmentId)->status);
        $this->assertSame('ended', MealAssignment::query()->findOrFail($mealAssignmentId)->status);
        $this->assertNotNull(WorkoutSession::query()->find($sessionId));
        $this->bearer($coach['token'])->getJson('/api/v1/coach/feedback-threads/'.$threadId)
            ->assertOk()
            ->assertJsonFragment(['body' => 'پیام می‌ماند']);
        $this->bearer($coach['token'])->getJson('/api/v1/coach/body-observations/'.$observationId)
            ->assertOk()
            ->assertJsonPath('metrics.weight_kg', 70);
        $this->bearer($coach['token'])->getJson('/api/v1/coach/workout-programs')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->programId($coach)]);
        $this->bearer($coach['token'])
            ->get('/api/v1/coach/body-photos/'.$photoId.'/file')
            ->assertForbidden()
            ->assertJsonPath('code', 'photo_closed');
        $this->bearer($other['token'])
            ->getJson('/api/v1/coach/body-observations/'.$observationId)
            ->assertNotFound();

        $this->bearer($athleteToken)->postJson('/api/v1/athlete/workout-sessions', [
            'source' => 'athlete_free',
            'performed_on' => '2026-10-13',
            'coaching_relationship_id' => $relationshipId,
        ])->assertStatus(422)->assertJsonPath('code', 'relationship_not_active');
        $this->bearer($athleteToken)->postJson('/api/v1/athlete/workout-sessions', [
            'source' => 'athlete_free',
            'performed_on' => '2026-10-13',
        ])->assertCreated()->assertJsonPath('coaching_relationship_id', null);

        $nextPhone = $this->phone();
        $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $nextPhone,
            'national_code' => $this->nationalCode(),
            'display_name' => 'بعدی',
        ])->assertCreated();
        $this->assertDatabaseHas('users', ['phone' => $nextPhone]);
    }

    public function test_scenario_11_expiry_closes_new_work_and_a_smaller_plan_keeps_athletes(): void
    {
        try {
            $admin = $this->admin();
            $expiredCoach = $this->coachWith(['workout_programs', 'ai_coach_assistant'], 5, 'منقضی', 5);
            $expiredAthlete = $this->athleteOf($expiredCoach);
            $this->settings($expiredCoach['token']);
            $exerciseId = $this->exercise($expiredCoach['user']);
            $versionId = $this->publishProgram($expiredCoach, $exerciseId);
            $expiredEnds = $this->bearer($expiredCoach['token'])->getJson('/api/v1/coach/access')->assertOk()->json('access.ends_at');

            $coach = $this->coach('زمان‌بندی');
            $wideId = $this->createPlan($admin['token'], [
                'code' => 'f11-wide-'.$coach['profile']->id,
                'athlete_capacity' => 2,
                'features' => ['workout_programs'],
            ])->assertCreated()->json('id');
            $narrowId = $this->createPlan($admin['token'], [
                'code' => 'f11-narrow-'.$coach['profile']->id,
                'athlete_capacity' => 1,
                'features' => ['workout_programs'],
            ])->assertCreated()->json('id');
            $first = $this->pay($coach['token'], $wideId, 'wide');
            $this->assertSame('active', $first['access_status']);
            $wideEnds = $this->bearer($coach['token'])->getJson('/api/v1/coach/access')->assertOk()->json('access.ends_at');
            $pendingId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
                'plan_id' => $narrowId,
            ])->assertCreated()->json('id');
            $failedAttempt = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$pendingId.'/attempts', [
                'idempotency_key' => 'narrow-fail',
            ])->assertCreated()->json('id');
            $this->confirm($failedAttempt, 1001, 'narrow-wrong')->assertStatus(422);
            $this->confirm($failedAttempt, 1001, 'narrow-wrong-2')->assertStatus(422);
            $this->assertSame(1, Payment::query()->whereHas('order', fn ($query) => $query->where('coach_profile_id', $coach['profile']->id))->count());
            $secondAttempt = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$pendingId.'/attempts', [
                'idempotency_key' => 'narrow-ok',
            ])->assertCreated()->json('id');
            $second = $this->confirm($secondAttempt, 1000, 'narrow-ok')->assertOk()->json();
            $this->assertSame('scheduled', $second['access_status']);

            $kept = [
                $this->provision($coach['token']),
                $this->provision($coach['token']),
            ];
            $this->assertSame(2, CoachingRelationship::query()->where('coach_profile_id', $coach['profile']->id)->where('status', 'active')->count());

            $stranger = $this->coach('بدون دوره');
            $this->bearer($stranger['token'])->postJson('/api/v1/coach/athletes', [
                'phone' => $this->phone(),
                'national_code' => $this->nationalCode(),
                'display_name' => 'نه',
            ])->assertForbidden()->assertJsonPath('code', 'no_access');

            $this->travelTo(CarbonImmutable::parse($expiredEnds)->max(CarbonImmutable::parse($wideEnds))->addSecond());

            $this->bearer($expiredCoach['token'])->getJson('/api/v1/coach/access')
                ->assertOk()
                ->assertJsonPath('access', null);
            $this->assertDatabaseHas('access_periods', [
                'coach_profile_id' => $expiredCoach['profile']->id,
                'status' => 'expired',
                'active_coach_id' => null,
            ]);
            $this->assertDatabaseHas('coaching_relationships', [
                'id' => $expiredAthlete['relationship']->id,
                'status' => 'active',
            ]);
            $blockedPhone = $this->phone();
            $this->bearer($expiredCoach['token'])->postJson('/api/v1/coach/athletes', [
                'phone' => $blockedPhone,
                'national_code' => $this->nationalCode(),
                'display_name' => 'بعد از انقضا',
            ])->assertForbidden()->assertJsonPath('code', 'no_access');
            $this->assertDatabaseMissing('users', ['phone' => $blockedPhone]);
            $this->bearer($expiredCoach['token'])->postJson('/api/v1/coach/workout-assignments', [
                'coaching_relationship_id' => $expiredAthlete['relationship']->id,
                'workout_program_version_id' => $versionId,
                'starts_on' => '2026-10-13',
            ])->assertForbidden()->assertJsonPath('code', 'no_access');
            $this->bearer($expiredCoach['token'])->postJson('/api/v1/coach/ai/generations', [
                'kind' => 'progress_review',
                'coaching_relationship_id' => $expiredAthlete['relationship']->id,
                'body' => 'بعد',
            ])->assertForbidden()->assertJsonPath('code', 'no_access');
            $this->bearer($expiredCoach['token'])->getJson('/api/v1/coach/workout-programs')
                ->assertOk()
                ->assertJsonFragment(['id' => $this->programId($expiredCoach)]);

            $this->bearer($coach['token'])->getJson('/api/v1/coach/access')
                ->assertOk()
                ->assertJsonPath('access.id', $second['access_period_id'])
                ->assertJsonPath('access.status', 'active')
                ->assertJsonPath('access.athlete_capacity', 1);
            $this->assertSame('expired', AccessPeriod::query()->findOrFail($first['access_period_id'])->status);
            foreach ($kept as $relationshipId) {
                $this->assertDatabaseHas('coaching_relationships', [
                    'id' => $relationshipId,
                    'status' => 'active',
                ]);
            }
            $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
                'phone' => $this->phone(),
                'national_code' => $this->nationalCode(),
                'display_name' => 'سوم',
            ])->assertStatus(422)->assertJsonPath('code', 'capacity_full');
        } finally {
            $this->travelBack();
        }
    }

    public function test_a_suspended_user_cannot_continue_the_relationship(): void
    {
        $coach = $this->coachWith(['workout_programs']);
        $athlete = $this->athleteOf($coach);
        $exerciseId = $this->exercise($coach['user']);
        $sessionId = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions', [
            'source' => 'athlete_free',
            'performed_on' => '2026-10-12',
            'coaching_relationship_id' => $athlete['relationship']->id,
        ])->assertCreated()->json('id');

        $coach['user']->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
        ])->save();

        $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $this->phone(),
            'national_code' => $this->nationalCode(),
            'display_name' => 'بعد از تعلیق',
        ])->assertForbidden()->assertJsonPath('code', 'account_suspended');
        $this->assertDatabaseHas('coaching_relationships', [
            'id' => $athlete['relationship']->id,
            'status' => 'active',
        ]);
        $this->postJson('/api/v1/auth/otp', [
            'phone' => $coach['user']->phone,
            'purpose' => 'login',
        ])->assertForbidden()->assertJsonPath('code', 'account_suspended');

        $athlete['user']->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
        ])->save();
        $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions/'.$sessionId.'/sets', [
            'exercise_id' => $exerciseId,
            'set_index' => 1,
            'reps' => 5,
        ])->assertForbidden()->assertJsonPath('code', 'account_suspended');
        $this->assertSame(0, WorkoutSession::query()->findOrFail($sessionId)->sets()->count());
        $this->assertNotNull(WorkoutSession::query()->find($sessionId));
    }

    private function acceptInvitation(string $phone, int $invitationId): void
    {
        $this->postJson('/api/v1/auth/otp', [
            'phone' => $phone,
            'purpose' => 'invitation',
            'invitation_id' => $invitationId,
        ])->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'purpose' => 'invitation',
            'code' => '54321',
            'invitation_id' => $invitationId,
        ])->assertOk()->assertJsonPath('relationship.status', 'active');
    }

    /**
     * @param  list<string>  $features
     * @return array{user: User, profile: \App\Models\CoachProfile, token: string}
     */
    private function coachWith(array $features, int $capacity = 5, string $name = 'مربی', ?int $quota = null): array
    {
        $admin = $this->admin();
        $coach = $this->coach($name);
        $this->pay($coach['token'], $this->createPlan($admin['token'], [
            'code' => 'f11-'.$coach['profile']->id.'-'.$this->serial,
            'features' => $features,
            'athlete_capacity' => $capacity,
            'ai_request_quota' => $quota,
        ])->assertCreated()->json('id'), 'f11-'.$this->serial);
        $this->serial++;

        return $coach;
    }

    /**
     * @return array{order_id: int, access_period_id: int, access_status: string}
     */
    private function pay(string $token, int $planId, string $key): array
    {
        $orderId = $this->bearer($token)->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json('id');
        $attemptId = $this->bearer($token)->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => $key,
        ])->assertCreated()->json('id');

        $paid = $this->confirm($attemptId, 1000, $key)->assertOk()->json();

        return [
            'order_id' => $orderId,
            'access_period_id' => $paid['access_period_id'],
            'access_status' => $paid['access_status'],
        ];
    }

    private function provision(string $token): int
    {
        return $this->bearer($token)->postJson('/api/v1/coach/athletes', [
            'phone' => $this->phone(),
            'national_code' => $this->nationalCode(),
            'display_name' => 'ورزشکار',
        ])->assertCreated()->json('relationship.id');
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
            'started_at' => '2026-01-01 00:00:00',
        ]);

        return [
            'user' => $user,
            'profile' => $profile,
            'relationship' => $relationship,
            'token' => $user->createToken('mobile')->plainTextToken,
        ];
    }

    private function exercise(User $user, ?int $coachProfileId = null, string $name = 'اسکوات'): int
    {
        return Exercise::query()->create([
            'coach_profile_id' => $coachProfileId,
            'name' => $name,
            'instructions' => 'توضیح',
            'goal' => 'هدف',
            'tracking_type' => 'weight_reps',
            'status' => 'active',
            'created_by_user_id' => $user->id,
        ])->id;
    }

    /**
     * @param  array{user: User, profile: \App\Models\CoachProfile, token: string}  $coach
     */
    private function publishProgram(array $coach, int $exerciseId): int
    {
        $programId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs', $this->programPayload($exerciseId))
            ->assertCreated()
            ->json('id');

        return $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs/'.$programId.'/publish')
            ->assertOk()
            ->json('current_version_id');
    }

    /**
     * @param  array{user: User, profile: \App\Models\CoachProfile, token: string}  $coach
     */
    private function programId(array $coach): int
    {
        return (int) $this->bearer($coach['token'])->getJson('/api/v1/coach/workout-programs')->json('programs.0.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function programPayload(int $exerciseId): array
    {
        return [
            'title' => 'قدرت',
            'note' => 'هفته اول',
            'days' => [[
                'position' => 0,
                'title' => 'شنبه پا',
                'weekday' => 0,
                'exercises' => [[
                    'position' => 0,
                    'exercise_id' => $exerciseId,
                    'tracking_type' => 'weight_reps',
                    'prescribed_sets' => 3,
                    'reps_min' => 8,
                    'reps_max' => 10,
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mealPayload(int $foodId, int $grams): array
    {
        return [
            'title' => 'غذا',
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

    private function settings(string $token): void
    {
        $this->bearer($token)->postJson('/api/v1/coach/ai/settings', [
            'methodology' => 'حجم',
            'preferences' => 'سه روز',
            'constraints' => 'بدون درد',
        ])->assertCreated();
    }

    private function phone(): string
    {
        $this->serial++;

        return '0912'.str_pad((string) ($this->serial % 10000000), 7, '0', STR_PAD_LEFT);
    }

    private function nationalCode(): string
    {
        $this->serial++;

        return NationalCode::withCheckDigit(str_pad((string) ($this->serial % 1000000000), 9, '0', STR_PAD_LEFT));
    }
}

final class RecordingWelcomeNotice implements AthleteWelcomeNotice
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    public array $messages = [];

    public function send(string $phone, string $initialPassword): void
    {
        $this->messages[] = [$phone, $initialPassword];
    }
}
