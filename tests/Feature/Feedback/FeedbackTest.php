<?php

namespace Tests\Feature\Feedback;

use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutDayExercise;
use App\Models\WorkoutProgram;
use App\Models\WorkoutProgramVersion;
use Tests\Feature\Commerce\CommerceTestCase;

class FeedbackTest extends CommerceTestCase
{
    public function test_coach_notes_stay_hidden_and_the_thread_stays_readable_after_end(): void
    {
        $coach = $this->coach();
        $athlete = $this->athleteOf($coach);
        $note = $this->bearer($coach['token'])->postJson('/api/v1/coach/feedback-threads', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'visibility' => 'coach_only',
            'body' => 'راز مربی',
        ])->assertCreated();

        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/feedback-threads')
            ->assertOk()
            ->assertJsonCount(0, 'threads');

        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/feedback-threads/'.$note->json('id'))
            ->assertNotFound()
            ->assertJsonPath('code', 'thread_not_found');

        $this->bearer($coach['token'])
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'notifications');

        $this->bearer($athlete['token'])->postJson('/api/v1/athlete/feedback-threads', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'visibility' => 'coach_only',
            'body' => 'نباید ذخیره شود',
        ])->assertStatus(422)->assertJsonPath('code', 'note_forbidden');

        $threadId = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/feedback-threads', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'body' => 'سخت بود',
            'pain_level' => 0,
        ])->assertCreated()->json('id');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/feedback-threads/'.$threadId.'/messages', [
            'visibility' => 'coach_only',
            'body' => 'یادداشت داخلی',
        ])->assertCreated();

        $this->bearer($coach['token'])->postJson('/api/v1/coach/feedback-threads/'.$threadId.'/messages', [
            'visibility' => 'shared',
            'body' => 'آرام‌تر برو',
        ])->assertCreated();

        $seen = $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/feedback-threads/'.$threadId)
            ->assertOk();
        $bodies = collect($seen->json('messages'))->pluck('body');
        $this->assertTrue($bodies->contains('سخت بود'));
        $this->assertTrue($bodies->contains('آرام‌تر برو'));
        $this->assertFalse($bodies->contains('یادداشت داخلی'));
        $this->assertFalse($bodies->contains('راز مربی'));
        $this->assertSame(0, $seen->json('messages.0.pain_level'));

        $athleteNotes = collect($this->bearer($athlete['token'])->getJson('/api/v1/notifications')->json('notifications'));
        $this->assertCount(1, $athleteNotes->where('type', 'feedback_replied'));
        $this->assertSame('یک پیام جدید در بازخورد ثبت شد.', $athleteNotes->first()['body']);

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/end', [])
            ->assertOk();

        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/feedback-threads/'.$threadId)
            ->assertOk()
            ->assertJsonPath('status', 'open');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/feedback-threads/'.$threadId.'/messages', [
            'visibility' => 'shared',
            'body' => 'بعد از پایان',
        ])->assertStatus(422)->assertJsonPath('code', 'relationship_not_active');
    }

    public function test_a_subject_outside_the_relationship_is_rejected(): void
    {
        $coach = $this->coach();
        $athlete = $this->athleteOf($coach);
        $other = $this->coach('مربی دیگر');
        $otherAthlete = $this->athleteOf($other);
        $sessionId = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions', [
            'source' => 'athlete_free',
            'performed_on' => '2026-10-12',
            'coaching_relationship_id' => $athlete['relationship']->id,
        ])->assertCreated()->json('id');
        $exercise = Exercise::query()->create([
            'coach_profile_id' => null,
            'name' => 'اسکوات',
            'instructions' => 'توضیح',
            'goal' => 'هدف',
            'tracking_type' => 'weight_reps',
            'status' => 'active',
            'created_by_user_id' => $coach['user']->id,
        ]);
        $program = WorkoutProgram::query()->create([
            'coach_profile_id' => $coach['profile']->id,
            'title' => 'حجم',
            'status' => 'published',
            'current_version_id' => null,
        ]);
        $version = WorkoutProgramVersion::query()->create([
            'workout_program_id' => $program->id,
            'version_number' => 1,
            'state' => 'published',
            'note' => null,
            'published_at' => now(),
            'created_by_user_id' => $coach['user']->id,
        ]);
        $day = WorkoutDay::query()->create([
            'workout_program_version_id' => $version->id,
            'position' => 0,
            'title' => 'شنبه',
            'weekday' => 0,
        ]);
        $line = WorkoutDayExercise::query()->create([
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
            'position' => 0,
            'tracking_type' => 'weight_reps',
        ]);

        $this->bearer($other['token'])->postJson('/api/v1/coach/feedback-threads', [
            'coaching_relationship_id' => $otherAthlete['relationship']->id,
            'visibility' => 'shared',
            'body' => 'موضوع غریبه',
            'workout_session_id' => $sessionId,
        ])->assertStatus(422)->assertJsonPath('code', 'subject_not_in_relationship');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/feedback-threads', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'visibility' => 'shared',
            'body' => 'قلم تخصیص‌نداده',
            'workout_day_exercise_id' => $line->id,
        ])->assertStatus(422)->assertJsonPath('code', 'subject_not_in_relationship');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/feedback-threads', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'visibility' => 'shared',
            'body' => 'دو موضوع',
            'workout_session_id' => $sessionId,
            'exercise_id' => $exercise->id,
        ])->assertStatus(422)->assertJsonPath('code', 'subject_conflict');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/feedback-threads', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'visibility' => 'shared',
            'body' => 'همین جلسه',
            'workout_session_id' => $sessionId,
        ])->assertCreated()->assertJsonPath('workout_session_id', $sessionId);
    }

    public function test_records_notify_the_athlete_and_only_the_coach_of_that_session(): void
    {
        $coach = $this->coach();
        $athlete = $this->athleteOf($coach);
        $stranger = User::factory()->create();
        $strangerToken = $stranger->createToken('mobile')->plainTextToken;
        $personalExercise = $this->exercise($coach['user']);
        $sharedExercise = $this->exercise($coach['user'], 'پرس');

        $personalId = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions', [
            'source' => 'athlete_free',
            'performed_on' => '2026-10-12',
        ])->assertCreated()->json('id');
        $personal = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions/'.$personalId.'/sets', [
            'exercise_id' => $personalExercise,
            'set_index' => 1,
            'reps' => 5,
            'weight_g' => 100000,
        ])->assertCreated();
        $personalRecordIds = collect($personal->json('records'))->pluck('id');

        $sharedId = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions', [
            'source' => 'athlete_free',
            'performed_on' => '2026-10-12',
            'coaching_relationship_id' => $athlete['relationship']->id,
        ])->assertCreated()->json('id');
        $shared = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/workout-sessions/'.$sharedId.'/sets', [
            'exercise_id' => $sharedExercise,
            'set_index' => 1,
            'reps' => 5,
            'weight_g' => 80000,
        ])->assertCreated();
        $sharedRecordIds = collect($shared->json('records'))->pluck('id');

        $athleteNotes = collect($this->bearer($athlete['token'])->getJson('/api/v1/notifications')->assertOk()->json('notifications'))
            ->where('type', 'record_achieved');
        $coachNotes = collect($this->bearer($coach['token'])->getJson('/api/v1/notifications')->assertOk()->json('notifications'))
            ->where('type', 'record_achieved');

        $this->assertEqualsCanonicalizing(
            $personalRecordIds->merge($sharedRecordIds)->all(),
            $athleteNotes->pluck('subject_id')->all(),
        );
        $this->assertEqualsCanonicalizing($sharedRecordIds->all(), $coachNotes->pluck('subject_id')->all());

        $hiddenId = $athleteNotes->first()['id'];
        $this->bearer($strangerToken)
            ->postJson('/api/v1/notifications/'.$hiddenId.'/read')
            ->assertNotFound()
            ->assertJsonPath('code', 'notification_not_found');
        $this->assertFalse(
            collect($this->bearer($strangerToken)->getJson('/api/v1/notifications')->assertOk()->json('notifications'))
                ->pluck('id')
                ->contains($hiddenId),
        );

        $this->bearer($athlete['token'])
            ->postJson('/api/v1/notifications/'.$hiddenId.'/read')
            ->assertOk()
            ->assertJsonPath('id', $hiddenId);

        $this->assertNotNull(
            collect($this->bearer($athlete['token'])->getJson('/api/v1/notifications')->json('notifications'))
                ->firstWhere('id', $hiddenId)['read_at'],
        );
    }

    public function test_assigning_a_program_notifies_only_the_athlete(): void
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $athlete = $this->athleteOf($coach);
        $planId = $this->createPlan($admin['token'], [
            'code' => 'both-'.$coach['profile']->id,
            'features' => ['workout_programs', 'nutrition_programs'],
        ])->assertCreated()->json('id');
        $orderId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json('id');
        $attemptId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => 'both-'.$coach['profile']->id,
        ])->assertCreated()->json('id');
        $this->confirm($attemptId, 1000, 'both-'.$coach['profile']->id)->assertOk();

        $program = WorkoutProgram::query()->create([
            'coach_profile_id' => $coach['profile']->id,
            'title' => 'قدرت',
            'status' => 'published',
            'current_version_id' => null,
        ]);
        $version = WorkoutProgramVersion::query()->create([
            'workout_program_id' => $program->id,
            'version_number' => 1,
            'state' => 'published',
            'note' => null,
            'published_at' => now(),
            'created_by_user_id' => $coach['user']->id,
        ]);
        $workoutAssignmentId = $this->bearer($coach['token'])->postJson('/api/v1/coach/workout-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'workout_program_version_id' => $version->id,
            'starts_on' => '2026-10-12',
        ])->assertCreated()->json('id');

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
        $mealPlanId = $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-plans', [
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
                        'quantity_g' => 100,
                    ]],
                ]],
            ]],
        ])->assertCreated()->json('id');
        $mealVersionId = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/meal-plans/'.$mealPlanId.'/publish')
            ->assertOk()
            ->json('current_version_id');
        $mealAssignmentId = $this->bearer($coach['token'])->postJson('/api/v1/coach/meal-assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'meal_plan_version_id' => $mealVersionId,
            'starts_on' => '2026-10-12',
        ])->assertCreated()->json('id');

        $notes = collect($this->bearer($athlete['token'])->getJson('/api/v1/notifications')->assertOk()->json('notifications'))
            ->where('type', 'program_assigned');
        $this->assertEqualsCanonicalizing(
            [
                ['workout_assignment', $workoutAssignmentId],
                ['meal_assignment', $mealAssignmentId],
            ],
            $notes->map(fn (array $note): array => [$note['subject_type'], $note['subject_id']])->values()->all(),
        );

        $this->bearer($coach['token'])
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'notifications');
    }

    private function exercise(User $user, string $name = 'اسکوات'): int
    {
        return Exercise::query()->create([
            'coach_profile_id' => null,
            'name' => $name,
            'instructions' => 'توضیح حرکت',
            'goal' => 'هدف حرکت',
            'tracking_type' => 'weight_reps',
            'status' => 'active',
            'created_by_user_id' => $user->id,
        ])->id;
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
