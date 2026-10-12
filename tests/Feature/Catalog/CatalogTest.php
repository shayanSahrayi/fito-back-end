<?php

namespace Tests\Feature\Catalog;

use App\Models\Exercise;
use Tests\Feature\Commerce\CommerceTestCase;

class CatalogTest extends CommerceTestCase
{
    public function test_private_exercise_stays_with_its_coach(): void
    {
        $admin = $this->admin();
        $muscleId = $this->muscle($admin['token']);
        $first = $this->coach('مربی الف');
        $second = $this->coach('مربی ب');

        $created = $this->bearer($first['token'])
            ->postJson('/api/v1/coach/exercises', $this->exercisePayload($muscleId, 'اسکوات خصوصی'))
            ->assertCreated()
            ->assertJsonPath('coach_profile_id', $first['profile']->id);

        $this->bearer($first['token'])
            ->postJson('/api/v1/coach/exercises/'.$created->json('id').'/archive')
            ->assertOk()
            ->assertJsonPath('status', 'archived');

        $this->bearer($first['token'])
            ->getJson('/api/v1/coach/exercises')
            ->assertOk()
            ->assertJsonPath('exercises.0.id', $created->json('id'))
            ->assertJsonPath('exercises.0.status', 'archived');

        $this->bearer($second['token'])
            ->getJson('/api/v1/coach/exercises')
            ->assertOk()
            ->assertJsonCount(0, 'exercises');

        $this->bearer($second['token'])
            ->patchJson('/api/v1/coach/exercises/'.$created->json('id'), [
                'name' => 'دزدیده',
            ])
            ->assertNotFound()
            ->assertJsonPath('code', 'exercise_not_found');
    }

    public function test_duplicate_muscle_is_rejected(): void
    {
        $admin = $this->admin();
        $muscleId = $this->muscle($admin['token']);
        $coach = $this->coach();
        $payload = $this->exercisePayload($muscleId);
        $payload['muscles'][] = [
            'muscle_id' => $muscleId,
            'involvement' => 'secondary',
        ];

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/exercises', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('muscles.1.muscle_id');

        $this->assertSame(0, Exercise::query()->count());
    }

    public function test_coach_cannot_change_a_platform_exercise(): void
    {
        $admin = $this->admin();
        $muscleId = $this->muscle($admin['token']);
        $coach = $this->coach();

        $created = $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/exercises', $this->exercisePayload($muscleId, 'اسکوات بانک'))
            ->assertCreated()
            ->assertJsonPath('coach_profile_id', null);

        $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/exercises')
            ->assertOk()
            ->assertJsonPath('exercises.0.id', $created->json('id'))
            ->assertJsonPath('exercises.0.name', 'اسکوات بانک');

        $this->bearer($coach['token'])
            ->patchJson('/api/v1/coach/exercises/'.$created->json('id'), [
                'name' => 'تغییر مربی',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'platform_exercise_locked');

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/exercises/'.$created->json('id').'/archive')
            ->assertForbidden();

        $this->assertDatabaseHas('exercises', [
            'id' => $created->json('id'),
            'name' => 'اسکوات بانک',
            'status' => 'active',
        ]);

        $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/exercises/'.$created->json('id').'/archive')
            ->assertOk()
            ->assertJsonPath('status', 'archived');

        $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/exercises')
            ->assertOk()
            ->assertJsonCount(0, 'exercises');
    }

    public function test_approval_creates_one_bank_exercise(): void
    {
        $admin = $this->admin();
        $muscleId = $this->muscle($admin['token']);
        $coach = $this->coach();

        $request = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/exercise-requests', [
                'proposed_name' => 'لانج',
                'instructions' => 'گام بلند به جلو',
                'goal' => 'تعادل و قدرت پا',
                'tracking_type' => 'reps',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $approve = [
            'muscles' => [
                ['muscle_id' => $muscleId, 'involvement' => 'primary'],
            ],
        ];

        $this->bearer($coach['token'])
            ->postJson('/api/v1/admin/exercise-requests/'.$request->json('id').'/approve', $approve)
            ->assertForbidden();

        $this->assertSame(0, Exercise::query()->count());

        $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/exercise-requests/'.$request->json('id').'/approve', $approve)
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('exercise.name', 'لانج')
            ->assertJsonPath('exercise.coach_profile_id', null)
            ->assertJsonPath('exercise.tracking_type', 'reps');

        $this->assertSame(1, Exercise::query()->whereNull('coach_profile_id')->count());

        $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/exercise-requests/'.$request->json('id').'/approve', $approve)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'already_reviewed');

        $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/exercise-requests/'.$request->json('id').'/reject', [
                'review_note' => 'دیر شده',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'already_reviewed');

        $this->assertSame(1, Exercise::query()->count());

        $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/exercises')
            ->assertOk()
            ->assertJsonPath('exercises.0.name', 'لانج')
            ->assertJsonPath('exercises.0.coach_profile_id', null);
    }

    public function test_rejection_does_not_create_an_exercise(): void
    {
        $admin = $this->admin();
        $coach = $this->coach();

        $request = $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/exercise-requests', [
                'proposed_name' => 'حرکت تکراری',
                'instructions' => 'توضیح',
                'goal' => 'هدف',
                'tracking_type' => 'duration',
            ])
            ->assertCreated();

        $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/exercise-requests/'.$request->json('id').'/reject', [
                'review_note' => 'در بانک هست',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('exercise_id', null)
            ->assertJsonPath('review_note', 'در بانک هست');

        $this->assertSame(0, Exercise::query()->count());

        $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/exercise-requests/'.$request->json('id').'/reject', [
                'review_note' => 'دوباره',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'already_reviewed');

        $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/exercise-requests/'.$request->json('id').'/approve', [
                'muscles' => [
                    ['muscle_id' => 1, 'involvement' => 'primary'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'already_reviewed');

        $this->assertSame(0, Exercise::query()->count());
    }

    public function test_admin_links_a_muscle_to_a_body_area_without_a_measurement(): void
    {
        $admin = $this->admin();

        $muscle = $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/muscles', [
                'code' => 'quads',
                'name_fa' => 'چهارسر',
                'name_en' => 'quadriceps',
            ])
            ->assertCreated();

        $area = $this->bearer($admin['token'])
            ->postJson('/api/v1/admin/body-areas', [
                'code' => 'thigh',
                'name_fa' => 'ران',
            ])
            ->assertCreated();

        $this->bearer($admin['token'])
            ->putJson('/api/v1/admin/muscles/'.$muscle->json('id').'/areas', [
                'body_model_area_ids' => [$area->json('id')],
            ])
            ->assertOk()
            ->assertJsonPath('areas.0.code', 'thigh');

        $this->bearer($this->athleteToken())
            ->getJson('/api/v1/coach/exercises')
            ->assertForbidden()
            ->assertJsonPath('code', 'coach_profile_required');
    }

    private function muscle(string $adminToken, string $code = 'quads'): int
    {
        return $this->bearer($adminToken)
            ->postJson('/api/v1/admin/muscles', [
                'code' => $code,
                'name_fa' => 'چهارسر',
            ])
            ->assertCreated()
            ->json('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function exercisePayload(int $muscleId, string $name = 'اسکوات'): array
    {
        return [
            'name' => $name,
            'instructions' => 'پا به عرض شانه',
            'goal' => 'قدرت پا',
            'tracking_type' => 'weight_reps',
            'muscles' => [
                ['muscle_id' => $muscleId, 'involvement' => 'primary'],
            ],
            'media' => [
                ['media_type' => 'image', 'path' => 'exercises/squat.jpg', 'sort_order' => 0],
            ],
        ];
    }
}
