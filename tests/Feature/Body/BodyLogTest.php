<?php

namespace Tests\Feature\Body;

use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Commerce\CommerceTestCase;

class BodyLogTest extends CommerceTestCase
{
    public function test_missing_metrics_stay_absent_and_previous_height_builds_bmi(): void
    {
        $coach = $this->coach();
        $athlete = $this->athleteOf($coach);

        $waist = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/body-observations', [
            'measured_at' => '2026-10-01T08:00:00Z',
            'visibility' => 'relationship',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'metrics' => ['waist_cm' => 80],
        ])->assertCreated();
        $waist->assertJsonPath('metrics.waist_cm', 80);
        $this->assertArrayNotHasKey('weight_kg', $waist->json('metrics'));
        $this->assertArrayNotHasKey('bmi_calculated', $waist->json('metrics'));

        $height = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/body-observations', [
            'measured_at' => '2026-10-02T08:00:00Z',
            'visibility' => 'athlete_private',
            'metrics' => ['height_cm' => 175],
        ])->assertCreated();
        $this->assertArrayNotHasKey('bmi_calculated', $height->json('metrics'));

        $weight = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/body-observations', [
            'measured_at' => '2026-10-12T08:00:00Z',
            'visibility' => 'relationship',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'metrics' => ['weight_kg' => '70.5', 'bmi' => '25'],
        ])->assertCreated();
        $weight->assertJsonPath('metrics.weight_kg', '70.5');
        $weight->assertJsonPath('metrics.bmi', 25);
        $weight->assertJsonPath('metrics.bmi_calculated', '23.02');

        $exact = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/body-observations', [
            'measured_at' => '2026-10-12T09:00:00Z',
            'visibility' => 'athlete_private',
            'metrics' => ['weight_kg' => 70, 'height_cm' => '175.5'],
        ])->assertCreated();
        $exact->assertJsonPath('metrics.height_cm', '175.5');
        $exact->assertJsonPath('metrics.bmi_calculated', '22.73');

        $reference = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/body-observations', [
            'measured_at' => '2026-10-12T10:00:00Z',
            'visibility' => 'athlete_private',
            'metrics' => ['weight_kg' => 70, 'height_cm' => 175],
        ])->assertCreated();
        $reference->assertJsonPath('metrics.bmi_calculated', '22.86');

        $compared = $this->bearer($athlete['token'])->getJson('/api/v1/athlete/body-observations/compare?before_id='.$waist->json('id').'&after_id='.$weight->json('id'))
            ->assertOk();
        $rows = collect($compared->json('metrics'))->keyBy('metric');
        $this->assertSame(80, $rows['waist_cm']['before']);
        $this->assertNull($rows['waist_cm']['after']);
        $this->assertNull($rows['waist_cm']['delta']);
        $this->assertNull($rows['weight_kg']['before']);
        $this->assertSame('70.5', $rows['weight_kg']['after']);

        $series = collect($this->bearer($athlete['token'])
            ->getJson('/api/v1/athlete/body-series?metric=weight_kg')
            ->assertOk()
            ->json('points'))
            ->pluck('observation_id');
        $this->assertFalse($series->contains($waist->json('id')));
        $this->assertTrue($series->contains($weight->json('id')));

        $coachIds = collect($this->bearer($coach['token'])
            ->getJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/body-observations')
            ->assertOk()
            ->json('observations'))
            ->pluck('id');
        $this->assertTrue($coachIds->contains($waist->json('id')));
        $this->assertFalse($coachIds->contains($height->json('id')));

        $this->bearer($coach['token'])
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'body_observation_recorded',
                'subject_id' => $weight->json('id'),
            ]);
    }

    public function test_another_coach_cannot_see_the_observation_or_the_photo_after_the_end(): void
    {
        Storage::fake('local');
        $coach = $this->coach();
        $other = $this->coach('دیگر');
        $athlete = $this->athleteOf($coach);
        $otherAthlete = $this->athleteOf($other);
        $observationId = $this->bearer($athlete['token'])->postJson('/api/v1/athlete/body-observations', [
            'measured_at' => '2026-10-12T08:00:00Z',
            'visibility' => 'relationship',
            'coaching_relationship_id' => $athlete['relationship']->id,
            'metrics' => ['weight_kg' => 70],
        ])->assertCreated()->json('id');

        $this->bearer($other['token'])
            ->getJson('/api/v1/coach/body-observations/'.$observationId)
            ->assertNotFound()
            ->assertJsonPath('code', 'observation_not_found');

        $this->bearer($other['token'])
            ->getJson('/api/v1/coach/relationships/'.$otherAthlete['relationship']->id.'/body-observations')
            ->assertOk()
            ->assertJsonCount(0, 'observations');

        $photoId = $this->bearer($athlete['token'])->post('/api/v1/athlete/body-photos', [
            'photo' => UploadedFile::fake()->image('body.jpg'),
            'taken_at' => '2026-10-12T08:00:00Z',
            'coaching_relationship_id' => $athlete['relationship']->id,
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $this->bearer($coach['token'])
            ->get('/api/v1/coach/body-photos/'.$photoId.'/file')
            ->assertOk();

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/end', [])
            ->assertOk();

        $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/body-observations/'.$observationId)
            ->assertOk()
            ->assertJsonPath('metrics.weight_kg', 70);

        $this->bearer($coach['token'])
            ->getJson('/api/v1/coach/body-photos/'.$photoId.'/file')
            ->assertForbidden()
            ->assertJsonPath('code', 'photo_closed');

        $this->bearer($athlete['token'])
            ->get('/api/v1/athlete/body-photos/'.$photoId.'/file')
            ->assertOk();
    }

    public function test_coach_writes_need_body_analysis(): void
    {
        $coach = $this->coach();
        $athlete = $this->athleteOf($coach);
        $body = [
            'measured_at' => '2026-10-12T08:00:00Z',
            'metrics' => ['weight_kg' => 70],
        ];

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/body-observations', $body)
            ->assertForbidden()
            ->assertJsonPath('code', 'no_access');

        $admin = $this->admin();
        $planId = $this->createPlan($admin['token'], [
            'code' => 'work-body-'.$coach['profile']->id,
            'features' => ['workout_programs'],
        ])->assertCreated()->json('id');
        $orderId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json('id');
        $attemptId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => 'work-body-'.$coach['profile']->id,
        ])->assertCreated()->json('id');
        $this->confirm($attemptId, 1000, 'work-body-'.$coach['profile']->id)->assertOk();

        $this->bearer($coach['token'])
            ->postJson('/api/v1/coach/relationships/'.$athlete['relationship']->id.'/body-observations', $body)
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_unavailable');
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
}
