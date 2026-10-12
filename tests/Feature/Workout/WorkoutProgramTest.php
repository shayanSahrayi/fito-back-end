<?php

namespace Tests\Feature\Workout;

use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutProgramVersion;
use Tests\Feature\Commerce\CommerceTestCase;

class WorkoutProgramTest extends CommerceTestCase
{
    public function test_publishing_locks_the_version_and_keeps_the_old_assignment(): void
    {
        $coach = $this->coachWithAccess();
        $athlete = $this->athleteOf($coach);
        $exerciseId = $this->exercise($coach['user'], null, 'اسکوات');

        $created = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs', $this->programPayload($exerciseId))
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('current_version_id', null)
            ->assertJsonPath('draft.days.0.title', 'شنبه پا');

        $programId = $created->json('id');

        $published = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs/'.$programId.'/publish')
            ->assertOk()
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('draft', null);

        $firstVersionId = $published->json('current_version_id');

        $assignment = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-assignments', [
                'coaching_relationship_id' => $athlete['relationship']->id,
                'workout_program_version_id' => $firstVersionId,
                'starts_on' => '2026-10-12',
            ])
            ->assertCreated()
            ->assertJsonPath('workout_program_version_id', $firstVersionId)
            ->assertJsonPath('athlete_profile_id', $athlete['profile']->id);

        $this->bearer($coach['token'])
            ->patchJson('/api/v1/coach/workout-programs/'.$programId, [
                'title' => 'نباید روی نسخه قفل بنشیند',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'draft_required');

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs/'.$programId.'/draft')
            ->assertOk()
            ->assertJsonPath('current_version_id', $firstVersionId)
            ->assertJsonPath('draft.version_number', 2);

        $replacement = $this->programPayload($exerciseId);
        $replacement['days'][0]['title'] = 'فول بادی';

        $this->bearer($coach['token'])
            ->patchJson('/api/v1/coach/workout-programs/'.$programId, $replacement)
            ->assertOk();

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs/'.$programId.'/publish')
            ->assertOk()
            ->assertJsonPath('draft', null)
            ->assertJsonPath('current_version.days.0.title', 'فول بادی');

        $this->assertNotSame($firstVersionId, $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/workout-programs/'.$programId)
            ->assertOk()
            ->json('current_version_id'));

        $this->assertSame(
            'شنبه پا',
            WorkoutDay::query()->where('workout_program_version_id', $firstVersionId)->value('title'),
        );
        $this->assertSame(2, WorkoutProgramVersion::query()->count());

        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/workout-assignments/'.$assignment->json('id'))
            ->assertOk()
            ->assertJsonPath('workout_program_version_id', $firstVersionId)
            ->assertJsonPath('version.days.0.title', 'شنبه پا');
    }

    public function test_exercises_come_back_in_position_order(): void
    {
        $coach = $this->coachWithAccess();
        $squat = $this->exercise($coach['user'], null, 'اسکوات');
        $plank = $this->exercise($coach['user'], $coach['profile']->id, 'پلانک', 'duration');
        $payload = $this->programPayload($squat);
        $payload['days'][0]['exercises'] = [
            [
                'position' => 1,
                'exercise_id' => $squat,
                'tracking_type' => 'weight_reps',
                'reps_min' => 8,
                'reps_max' => 10,
            ],
            [
                'position' => 0,
                'exercise_id' => $plank,
                'tracking_type' => 'duration',
                'duration_seconds' => 45,
            ],
        ];

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs', $payload)
            ->assertCreated()
            ->assertJsonPath('draft.days.0.exercises.0.position', 0)
            ->assertJsonPath('draft.days.0.exercises.0.exercise_id', $plank)
            ->assertJsonPath('draft.days.0.exercises.1.position', 1)
            ->assertJsonPath('draft.days.0.exercises.1.exercise_id', $squat);
    }

    public function test_an_ended_relationship_cannot_receive_a_program(): void
    {
        $coach = $this->coachWithAccess();
        $athlete = $this->athleteOf($coach);
        $exerciseId = $this->exercise($coach['user'], null, 'اسکوات');
        $program = $this->publishProgram($coach['token'], $exerciseId);

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-assignments', [
                'coaching_relationship_id' => $athlete['relationship']->id,
                'workout_program_version_id' => $program['version_id'],
                'starts_on' => '2026-10-12',
            ])
            ->assertCreated();

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/end')
            ->assertOk();

        $this->assertDatabaseHas('workout_assignments', [
            'coaching_relationship_id' => $athlete['relationship']->id,
            'status' => 'ended',
        ]);

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-assignments', [
                'coaching_relationship_id' => $athlete['relationship']->id,
                'workout_program_version_id' => $program['version_id'],
                'starts_on' => '2026-10-13',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'relationship_not_active');

        $this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/workout-assignments')
            ->assertOk()
            ->assertJsonPath('assignments.0.status', 'ended')
            ->assertJsonPath('assignments.0.version.days.0.title', 'شنبه پا');
    }

    public function test_a_plan_without_the_feature_cannot_write_a_program(): void
    {
        $coach = $this->coach();
        $exerciseId = $this->exercise($coach['user'], null, 'اسکوات');

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs', $this->programPayload($exerciseId))
            ->assertForbidden()
            ->assertJsonPath('code', 'no_access');

        $admin = $this->admin();
        $planId = $this->createPlan($admin['token'], [
            'code' => 'food-'.$coach['profile']->id,
            'features' => ['nutrition_programs'],
        ])->assertCreated()->json('id');

        $orderId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json('id');

        $attemptId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => 'food-'.$coach['profile']->id,
        ])->assertCreated()->json('id');

        $this->confirm($attemptId, 1000, 'food-'.$coach['profile']->id)->assertOk();

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs', $this->programPayload($exerciseId))
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_unavailable');
    }

    public function test_another_coachs_exercise_cannot_be_prescribed(): void
    {
        $coach = $this->coachWithAccess();
        $other = $this->coach('مربی دیگر');
        $privateId = $this->exercise($other['user'], $other['profile']->id, 'حرکت خصوصی');
        $ownId = $this->exercise($coach['user'], null, 'اسکوات');

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs', $this->programPayload($privateId))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'exercise_not_available');

        $payload = $this->programPayload($ownId);
        $payload['days'][0]['exercises'][0]['tracking_type'] = 'duration';
        $payload['days'][0]['exercises'][0]['duration_seconds'] = 30;
        $payload['days'][0]['exercises'][0]['target_weight_g'] = 1000;
        unset($payload['days'][0]['exercises'][0]['reps_min'], $payload['days'][0]['exercises'][0]['reps_max'], $payload['days'][0]['exercises'][0]['rest_seconds'], $payload['days'][0]['exercises'][0]['prescribed_sets']);

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/workout-programs', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'irrelevant_measure');
    }

    private function exercise(User $user, ?int $coachProfileId, string $name, string $tracking = 'weight_reps'): int
    {
        return Exercise::query()->create([
            'coach_profile_id' => $coachProfileId,
            'name' => $name,
            'instructions' => 'توضیح حرکت',
            'goal' => 'هدف حرکت',
            'tracking_type' => $tracking,
            'status' => 'active',
            'created_by_user_id' => $user->id,
        ])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function programPayload(int $exerciseId): array
    {
        return [
            'title' => 'قدرت',
            'note' => 'هفته اول',
            'days' => [
                [
                    'position' => 0,
                    'title' => 'شنبه پا',
                    'weekday' => 0,
                    'exercises' => [
                        [
                            'position' => 0,
                            'exercise_id' => $exerciseId,
                            'tracking_type' => 'weight_reps',
                            'prescribed_sets' => 3,
                            'reps_min' => 8,
                            'reps_max' => 10,
                            'rest_seconds' => 90,
                            'coach_note' => 'عمق کامل',
                        ],
                    ],
                ],
            ],
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
    private function coachWithAccess(): array
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $planId = $this->createPlan($admin['token'], [
            'code' => 'work-'.$coach['profile']->id,
        ])->assertCreated()->json('id');

        $orderId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json('id');

        $attemptId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => 'work-'.$coach['profile']->id,
        ])->assertCreated()->json('id');

        $this->confirm($attemptId, 1000, 'work-'.$coach['profile']->id)->assertOk();

        return $coach;
    }

    /**
     * @return array{program_id: int, version_id: int}
     */
    private function publishProgram(string $token, int $exerciseId): array
    {
        $programId = $this->bearer($token)
            ->postJson('/api/v1/coach/workout-programs', $this->programPayload($exerciseId))
            ->assertCreated()
            ->json('id');

        $versionId = $this->bearer($token)
            ->postJson('/api/v1/coach/workout-programs/'.$programId.'/publish')
            ->assertOk()
            ->json('current_version_id');

        return [
            'program_id' => $programId,
            'version_id' => $versionId,
        ];
    }
}
