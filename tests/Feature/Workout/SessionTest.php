<?php

namespace Tests\Feature\Workout;

use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\Exercise;
use App\Models\PersonalRecordEvent;
use App\Models\User;
use App\Models\WorkoutAssignment;
use App\Models\WorkoutDay;
use App\Models\WorkoutDayExercise;
use App\Models\WorkoutProgram;
use App\Models\WorkoutProgramVersion;
use Tests\Feature\Commerce\CommerceTestCase;

class SessionTest extends CommerceTestCase
{
    public function test_different_reps_stay_separate_from_the_prescription(): void
    {
        $coach = $this->coach();
        $athlete = $this->athleteOf($coach);
        $exerciseId = $this->exercise($coach['user']);
        $plan = $this->prescription($coach, $athlete, $exerciseId);

        $session = $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions', [
                'source' => 'prescribed',
                'performed_on' => '2026-10-12',
                'workout_assignment_id' => $plan['assignment']->id,
                'workout_day_id' => $plan['day']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('source', 'prescribed')
            ->assertJsonPath('workout_day_id', $plan['day']->id)
            ->assertJsonPath('coaching_relationship_id', $athlete['relationship']->id);

        foreach ([10, 9, 8] as $index => $reps) {
            $this->bearer($athlete['token'])
                ->postJson('/api/v1/athlete/workout-sessions/'.$session->json('id').'/sets', [
                    'workout_day_exercise_id' => $plan['item']->id,
                    'set_index' => $index + 1,
                    'reps' => $reps,
                ])
                ->assertCreated()
                ->assertJsonPath('exercise_id', $exerciseId)
                ->assertJsonPath('reps', $reps);
        }

        $this->assertSame(10, (int) WorkoutDayExercise::query()->find($plan['item']->id)->reps_min);

        WorkoutDayExercise::query()->whereKey($plan['item']->id)->update(['reps_min' => 99]);

        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/workout-sessions/'.$session->json('id'))
            ->assertOk()
            ->assertJsonPath('workout_day_id', $plan['day']->id)
            ->assertJsonPath('sets.0.reps', 10)
            ->assertJsonPath('sets.1.reps', 9)
            ->assertJsonPath('sets.2.reps', 8)
            ->assertJsonPath('sets.0.workout_day_exercise_id', $plan['item']->id);

        $this->assertSame(99, (int) WorkoutDayExercise::query()->find($plan['item']->id)->reps_min);

        $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions/'.$session->json('id').'/complete')
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions/'.$session->json('id').'/sets', [
                'workout_day_exercise_id' => $plan['item']->id,
                'set_index' => 4,
                'reps' => 7,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'session_completed');
    }

    public function test_an_equal_or_weaker_result_does_not_create_another_record(): void
    {
        $coach = $this->coach();
        $athlete = $this->athleteOf($coach);
        $exerciseId = $this->exercise($coach['user']);

        $sessionId = $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions', [
                'source' => 'athlete_free',
                'performed_on' => '2026-10-12',
            ])
            ->assertCreated()
            ->json('id');

        $first = $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions/'.$sessionId.'/sets', [
                'exercise_id' => $exerciseId,
                'set_index' => 1,
                'reps' => 5,
                'weight_g' => 100000,
            ])
            ->assertCreated();

        $setId = $first->json('id');
        $this->assertSame($setId, $first->json('records.0.workout_session_set_id'));
        $this->assertSame('max_weight_g', $first->json('records.0.metric'));
        $this->assertSame(100000, $first->json('records.0.value_int'));
        $this->assertNull($first->json('records.0.formula_code'));
        $this->assertSame($setId, $first->json('records.1.workout_session_set_id'));
        $this->assertSame('estimated_1rm_g', $first->json('records.1.metric'));
        $this->assertSame(116667, $first->json('records.1.value_int'));
        $this->assertSame('epley_v1', $first->json('records.1.formula_code'));

        $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions/'.$sessionId.'/sets', [
                'exercise_id' => $exerciseId,
                'set_index' => 2,
                'reps' => 5,
                'weight_g' => 100000,
            ])
            ->assertCreated()
            ->assertJsonCount(0, 'records');

        $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions/'.$sessionId.'/sets', [
                'exercise_id' => $exerciseId,
                'set_index' => 3,
                'reps' => 5,
                'weight_g' => 90000,
            ])
            ->assertCreated()
            ->assertJsonCount(0, 'records');

        $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions/'.$sessionId.'/sets', [
                'exercise_id' => $exerciseId,
                'set_index' => 4,
                'reps' => 1,
                'weight_g' => 200000,
                'is_completed' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('is_completed', false)
            ->assertJsonCount(0, 'records');

        $this->assertSame(2, PersonalRecordEvent::query()->count());
        $this->assertDatabaseHas('personal_record_events', [
            'workout_session_set_id' => $setId,
            'metric' => 'max_weight_g',
            'value_int' => 100000,
        ]);
        $this->assertDatabaseHas('personal_record_events', [
            'workout_session_set_id' => $setId,
            'metric' => 'estimated_1rm_g',
            'value_int' => 116667,
            'formula_code' => 'epley_v1',
        ]);
    }

    public function test_a_session_after_the_relationship_ends_cannot_use_that_id(): void
    {
        $coach = $this->coach();
        $athlete = $this->athleteOf($coach);
        $exerciseId = $this->exercise($coach['user']);

        $sessionId = $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions', [
                'source' => 'athlete_free',
                'performed_on' => '2026-10-12',
                'coaching_relationship_id' => $athlete['relationship']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('coaching_relationship_id', $athlete['relationship']->id)
            ->json('id');

        $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions/'.$sessionId.'/sets', [
                'exercise_id' => $exerciseId,
                'set_index' => 1,
                'reps' => 12,
            ])
            ->assertCreated();

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/end')
            ->assertOk();

        $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions', [
                'source' => 'athlete_free',
                'performed_on' => '2026-10-13',
                'coaching_relationship_id' => $athlete['relationship']->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'relationship_not_active');

        $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions/'.$sessionId.'/sets', [
                'exercise_id' => $exerciseId,
                'set_index' => 2,
                'reps' => 8,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'relationship_not_active');

        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/workout-sessions/'.$sessionId)
            ->assertOk()
            ->assertJsonPath('sets.0.reps', 12)
            ->assertJsonPath('coaching_relationship_id', $athlete['relationship']->id);

        $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/workout-sessions')
            ->assertOk()
            ->assertJsonCount(1, 'sessions')
            ->assertJsonPath('sessions.0.id', $sessionId);

        $personal = $this->bearer($athlete['token'])
            ->postJson('/api/v1/athlete/workout-sessions', [
                'source' => 'athlete_free',
                'performed_on' => '2026-10-13',
            ])
            ->assertCreated()
            ->assertJsonPath('coaching_relationship_id', null);

        $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/workout-sessions')
            ->assertOk()
            ->assertJsonCount(1, 'sessions')
            ->assertJsonMissing(['id' => $personal->json('id')]);
    }

    private function exercise(User $user): int
    {
        return Exercise::query()->create([
            'coach_profile_id' => null,
            'name' => 'اسکوات',
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

    /**
     * @param  array{user: User, profile: \App\Models\CoachProfile, token: string}  $coach
     * @param  array{user: User, profile: AthleteProfile, relationship: CoachingRelationship, token: string}  $athlete
     * @return array{assignment: WorkoutAssignment, day: WorkoutDay, item: WorkoutDayExercise}
     */
    private function prescription(array $coach, array $athlete, int $exerciseId): array
    {
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
            'published_at' => now(),
            'created_by_user_id' => $coach['user']->id,
        ]);
        $program->current_version_id = $version->id;
        $program->save();
        $day = WorkoutDay::query()->create([
            'workout_program_version_id' => $version->id,
            'position' => 0,
            'title' => 'شنبه پا',
            'weekday' => 0,
        ]);
        $item = WorkoutDayExercise::query()->create([
            'workout_day_id' => $day->id,
            'exercise_id' => $exerciseId,
            'position' => 0,
            'tracking_type' => 'weight_reps',
            'prescribed_sets' => 3,
            'reps_min' => 10,
            'reps_max' => 10,
        ]);
        $assignment = WorkoutAssignment::query()->create([
            'coaching_relationship_id' => $athlete['relationship']->id,
            'athlete_profile_id' => $athlete['profile']->id,
            'workout_program_version_id' => $version->id,
            'assigned_by_user_id' => $coach['user']->id,
            'starts_on' => '2026-10-12',
            'status' => 'active',
        ]);

        return [
            'assignment' => $assignment,
            'day' => $day,
            'item' => $item,
        ];
    }
}
