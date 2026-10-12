<?php

namespace Tests\Feature\Coaching;

use App\Domain\Coaching\AthleteWelcomeNotice;
use App\Domain\Identity\NationalCode;
use App\Domain\Identity\OtpCodeGenerator;
use App\Models\AthleteProfile;
use App\Models\CoachingRelationship;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Commerce\CommerceTestCase;

class InvitationTest extends CommerceTestCase
{
    private AthleteWelcomeNotice $notice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(OtpCodeGenerator::class, fn () => new class implements OtpCodeGenerator
        {
            public function generate(): string
            {
                return '54321';
            }
        });

        $this->notice = new class implements AthleteWelcomeNotice
        {
            public array $messages = [];

            public function send(string $phone, string $initialPassword): void
            {
                $this->messages[] = [$phone, $initialPassword];
            }
        };
        $this->app->instance(AthleteWelcomeNotice::class, $this->notice);
    }

    public function test_a_new_phone_gets_an_account_and_an_active_relationship(): void
    {
        $coach = $this->coachWithAccess();
        $nationalCode = NationalCode::withCheckDigit('123456789');

        $created = $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => '09130000001',
            'national_code' => $nationalCode,
            'display_name' => 'ورزشکار نو',
        ])->assertCreated();

        $created->assertJsonPath('relationship.status', 'active');
        $created->assertJsonPath('relationship.athlete.display_name', 'ورزشکار نو');
        $created->assertJsonMissingPath('relationship.athlete.phone');

        $athlete = User::query()->where('phone', '09130000001')->firstOrFail();
        $this->assertTrue($athlete->password_is_initial);
        $this->assertTrue(Hash::check($nationalCode, (string) $athlete->password));
        $this->assertSame([['09130000001', $nationalCode]], $this->notice->messages);
        $this->assertDatabaseCount('coaching_relationships', 1);
        $this->assertDatabaseHas('coaching_relationship_events', ['event_type' => 'started']);

        $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => '09130000001',
            'national_code' => $nationalCode,
            'display_name' => 'نام دیگر',
        ])->assertOk()->assertJsonPath('relationship.id', $created->json('relationship.id'));

        $this->assertDatabaseCount('users', 3);
        $this->assertSame('ورزشکار نو', $athlete->athleteProfile()->firstOrFail()->display_name);
    }

    public function test_an_existing_account_keeps_its_password_until_it_accepts(): void
    {
        $coach = $this->coachWithAccess();
        $athlete = User::factory()->create(['password' => 'secret-pass']);
        AthleteProfile::query()->create([
            'user_id' => $athlete->id,
            'display_name' => 'نام قبلی',
            'status' => 'active',
        ]);
        $hash = $athlete->refresh()->password;

        $invited = $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $athlete->phone,
            'national_code' => $athlete->national_code,
            'display_name' => 'نام جدید',
        ])->assertCreated();

        $invited->assertJsonPath('relationship', null);
        $invited->assertJsonPath('invitation.status', 'pending');
        $this->assertSame($hash, $athlete->refresh()->password);
        $this->assertSame('نام قبلی', $athlete->athleteProfile()->firstOrFail()->display_name);
        $this->assertSame([], $this->notice->messages);

        $this->postJson('/api/v1/auth/otp', [
            'phone' => $athlete->phone,
            'purpose' => 'invitation',
            'invitation_id' => $invited->json('invitation.id'),
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $athlete->phone,
            'purpose' => 'invitation',
            'code' => '54321',
            'invitation_id' => $invited->json('invitation.id'),
        ])->assertOk()->assertJsonPath('relationship.status', 'active');

        $this->assertSame($hash, $athlete->refresh()->password);
        $this->assertDatabaseHas('coaching_relationships', [
            'coach_profile_id' => $coach['profile']->id,
            'athlete_profile_id' => $athlete->athleteProfile()->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    public function test_capacity_blocks_a_second_new_athlete(): void
    {
        $coach = $this->coachWithAccess(1);

        $this->register($coach['token'], '09130000001', '123456789')->assertCreated();

        $this->register($coach['token'], '09130000002', '223456789')
            ->assertStatus(422)
            ->assertJson(['code' => 'capacity_full']);

        $this->assertDatabaseMissing('users', ['phone' => '09130000002']);
        $this->assertDatabaseCount('coaching_relationships', 1);
    }

    public function test_an_ended_relationship_is_restored_when_the_athlete_accepts_again(): void
    {
        $coach = $this->coachWithAccess(1);
        $nationalCode = NationalCode::withCheckDigit('123456789');
        $created = $this->register($coach['token'], '09130000001', '123456789')->assertCreated();
        $relationshipId = $created->json('relationship.id');

        $this->bearer($coach['token'])->postJson('/api/v1/coach/relationships/'.$relationshipId.'/end', [
            'end_reason' => 'پایان دوره',
        ])->assertOk()->assertJson(['status' => 'ended']);

        $invited = $this->register($coach['token'], '09130000001', '123456789')->assertCreated();
        $this->assertNull($invited->json('relationship'));

        $this->postJson('/api/v1/auth/otp', [
            'phone' => '09130000001',
            'purpose' => 'invitation',
            'invitation_id' => $invited->json('invitation.id'),
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '09130000001',
            'purpose' => 'invitation',
            'code' => '54321',
            'invitation_id' => $invited->json('invitation.id'),
        ])->assertOk()->assertJsonPath('relationship.id', $relationshipId);

        $this->assertDatabaseHas('coaching_relationships', ['id' => $relationshipId, 'status' => 'active']);
        $this->assertDatabaseHas('coaching_relationship_events', [
            'coaching_relationship_id' => $relationshipId,
            'event_type' => 'reactivated',
        ]);
        $this->assertSame($nationalCode, User::query()->where('phone', '09130000001')->firstOrFail()->national_code);
    }

    public function test_two_coaches_can_connect_the_same_athlete(): void
    {
        $first = $this->coachWithAccess();
        $second = $this->coachWithAccess();
        $nationalCode = NationalCode::withCheckDigit('323456789');

        $this->bearer($first['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => '09130000009',
            'national_code' => $nationalCode,
            'display_name' => 'مشترک',
        ])->assertCreated();

        $athlete = User::query()->where('phone', '09130000009')->firstOrFail();
        $hash = $athlete->password;

        $invited = $this->bearer($second['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => '09130000009',
            'national_code' => $nationalCode,
            'display_name' => 'مشترک',
        ])->assertCreated();

        $this->assertSame($hash, $athlete->refresh()->password);

        $this->postJson('/api/v1/auth/otp', [
            'phone' => '09130000009',
            'purpose' => 'invitation',
            'invitation_id' => $invited->json('invitation.id'),
        ])->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '09130000009',
            'purpose' => 'invitation',
            'code' => '54321',
            'invitation_id' => $invited->json('invitation.id'),
        ])->assertOk();

        $this->assertSame(2, CoachingRelationship::query()->where('status', 'active')->count());

        $this->bearer($first['token'])->getJson('/api/v1/coach/relationships')
            ->assertOk()
            ->assertJsonCount(1, 'relationships')
            ->assertJsonMissingPath('relationships.0.athlete.national_code');

        $login = $this->postJson('/api/v1/auth/password', [
            'phone' => '09130000009',
            'password' => $nationalCode,
            'client' => 'mobile',
        ])->assertOk();

        $this->bearer($login->json('token'))->getJson('/api/v1/athlete/relationships')
            ->assertOk()
            ->assertJsonCount(2, 'relationships')
            ->assertJsonMissingPath('relationships.0.coach.phone');
    }

    public function test_password_login_can_replace_the_initial_password(): void
    {
        $coach = $this->coachWithAccess();
        $nationalCode = NationalCode::withCheckDigit('423456789');
        $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => '09130000008',
            'national_code' => $nationalCode,
            'display_name' => 'نو',
        ])->assertCreated();

        $login = $this->postJson('/api/v1/auth/password', [
            'phone' => '09130000008',
            'password' => $nationalCode,
            'client' => 'mobile',
        ])->assertOk();

        $this->bearer($login->json('token'))->postJson('/api/v1/me/password', [
            'current_password' => $nationalCode,
            'password' => 'new-password',
        ])->assertNoContent();

        $athlete = User::query()->where('phone', '09130000008')->firstOrFail();
        $this->assertFalse($athlete->password_is_initial);
        $this->assertSame($nationalCode, $athlete->national_code);
        $this->assertTrue(Hash::check('new-password', (string) $athlete->password));

        $this->postJson('/api/v1/auth/password', [
            'phone' => '09130000008',
            'password' => $nationalCode,
            'client' => 'mobile',
        ])->assertStatus(422)->assertJson(['code' => 'password_invalid']);
    }

    public function test_a_coach_cannot_invite_without_access_or_invite_themselves(): void
    {
        $coach = $this->coach();

        $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => '09130000007',
            'national_code' => NationalCode::withCheckDigit('523456789'),
            'display_name' => 'کسی',
        ])->assertStatus(403)->assertJson(['code' => 'no_access']);

        $ready = $this->coachWithAccess();

        $this->bearer($ready['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $ready['user']->phone,
            'national_code' => $ready['user']->national_code,
            'display_name' => 'خودش',
        ])->assertStatus(422)->assertJson(['code' => 'self_coaching']);
    }

    public function test_a_mismatched_national_code_does_not_open_an_invitation(): void
    {
        $coach = $this->coachWithAccess();
        $athlete = User::factory()->create();

        $this->bearer($coach['token'])->postJson('/api/v1/coach/athletes', [
            'phone' => $athlete->phone,
            'national_code' => NationalCode::withCheckDigit('623456789'),
            'display_name' => 'غریبه',
        ])->assertStatus(422)->assertJson(['code' => 'national_code_mismatch']);

        $this->assertDatabaseCount('athlete_invitations', 0);
    }

    /**
     * @return array{user: User, profile: \App\Models\CoachProfile, token: string}
     */
    private function coachWithAccess(int $capacity = 5): array
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $planId = $this->createPlan($admin['token'], [
            'code' => 'rel-'.$coach['profile']->id,
            'athlete_capacity' => $capacity,
        ])->assertCreated()->json('id');

        $orderId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders', [
            'plan_id' => $planId,
        ])->assertCreated()->json('id');

        $attemptId = $this->bearer($coach['token'])->postJson('/api/v1/coach/orders/'.$orderId.'/attempts', [
            'idempotency_key' => 'rel-'.$coach['profile']->id,
        ])->assertCreated()->json('id');

        $this->confirm($attemptId, 1000, 'rel-'.$coach['profile']->id)->assertOk();

        return $coach;
    }

    private function register(string $token, string $phone, string $firstNine): \Illuminate\Testing\TestResponse
    {
        return $this->bearer($token)->postJson('/api/v1/coach/athletes', [
            'phone' => $phone,
            'national_code' => NationalCode::withCheckDigit($firstNine),
            'display_name' => 'ورزشکار',
        ]);
    }
}
