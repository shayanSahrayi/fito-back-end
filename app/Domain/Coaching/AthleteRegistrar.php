<?php

namespace App\Domain\Coaching;

use App\Domain\Commerce\AccessSchedule;
use App\Domain\Commerce\CommerceException;
use App\Domain\Identity\AccountStatus;
use App\Domain\Identity\IdentityException;
use App\Domain\Identity\NationalCode;
use App\Domain\Identity\PhoneNumber;
use App\Models\AccessPeriod;
use App\Models\AthleteInvitation;
use App\Models\AthleteProfile;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\CoachingRelationshipEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class AthleteRegistrar
{
    public function __construct(
        private readonly AccessSchedule $schedule,
        private readonly CapacityGuard $capacity,
        private readonly InvitationClock $clock,
        private readonly AthleteWelcomeNotice $notice,
    ) {}

    /**
     * @return array{created: bool, invitation: array<string, mixed>|null, relationship: array<string, mixed>|null}
     */
    public function register(User $coachUser, CoachProfile $coach, string $rawPhone, string $displayName, string $rawNationalCode): array
    {
        $phone = PhoneNumber::normalize($rawPhone);
        $nationalCode = NationalCode::parse($rawNationalCode);
        $displayName = $this->displayName($displayName);

        if ($coachUser->phone === $phone) {
            throw new CommerceException('self_coaching', 'رابطه شخص با خودش مجاز نیست.');
        }

        $active = $this->requireActivePeriod($coach);

        try {
            $result = DB::transaction(fn (): array => $this->withinPeriod(
                $coachUser,
                $coach,
                $active,
                $phone,
                $displayName,
                $nationalCode,
            ));
        } catch (UniqueConstraintViolationException) {
            $result = DB::transaction(fn (): array => $this->withinPeriod(
                $coachUser,
                $coach,
                $active,
                $phone,
                $displayName,
                $nationalCode,
                existingOnly: true,
            ));
        }

        if (is_string($result['plain_password'] ?? null)) {
            $this->notice->send($phone, $result['plain_password']);
        }

        unset($result['plain_password']);

        return $result;
    }

    /**
     * @return array{created: bool, invitation: array<string, mixed>|null, relationship: array<string, mixed>|null, plain_password?: string}
     */
    private function withinPeriod(
        User $coachUser,
        CoachProfile $coach,
        AccessPeriod $active,
        string $phone,
        string $displayName,
        string $nationalCode,
        bool $existingOnly = false,
    ): array {
        $period = AccessPeriod::query()->whereKey($active->id)->lockForUpdate()->first();

        if ($period === null || $period->status !== 'active' || $period->active_coach_id !== $coach->id || $period->ends_at->lte(now())) {
            throw new CommerceException('no_access', 'دوره دسترسی فعالی نیست.', 403);
        }

        $athleteUser = User::query()->where('phone', $phone)->lockForUpdate()->first();

        if ($athleteUser === null) {
            if ($existingOnly || User::query()->where('national_code', $nationalCode)->exists()) {
                throw new IdentityException('national_code_taken', 'این کد ملی قبلاً ثبت شده است.');
            }

            return $this->provisionNew($coachUser, $coach, $period, $phone, $displayName, $nationalCode);
        }

        return $this->inviteExisting($coachUser, $coach, $athleteUser, $displayName, $nationalCode);
    }

    /**
     * @return array{created: bool, invitation: array<string, mixed>|null, relationship: array<string, mixed>|null, plain_password: string}
     */
    private function provisionNew(
        User $coachUser,
        CoachProfile $coach,
        AccessPeriod $period,
        string $phone,
        string $displayName,
        string $nationalCode,
    ): array {
        $this->capacity->assertOpen($period, $coach);

        $athleteUser = User::query()->create([
            'phone' => $phone,
            'national_code' => $nationalCode,
            'password' => $nationalCode,
            'password_is_initial' => true,
            'status' => 'active',
        ]);

        $profile = AthleteProfile::query()->create([
            'user_id' => $athleteUser->id,
            'display_name' => $displayName,
            'status' => 'active',
        ]);

        $relationship = $this->startRelationship($coach, $profile, $coachUser);
        $invitation = $this->storeInvitation($coach, $coachUser, $phone, 'accepted', $athleteUser, $relationship);

        return [
            'created' => true,
            'invitation' => RelationshipView::invitation($invitation),
            'relationship' => RelationshipView::forCoach($relationship),
            'plain_password' => $nationalCode,
        ];
    }

    /**
     * @return array{created: bool, invitation: array<string, mixed>|null, relationship: array<string, mixed>|null}
     */
    private function inviteExisting(
        User $coachUser,
        CoachProfile $coach,
        User $athleteUser,
        string $displayName,
        string $nationalCode,
    ): array {
        AccountStatus::assertActive($athleteUser);

        if ($athleteUser->id === $coachUser->id || $athleteUser->national_code !== $nationalCode) {
            if ($athleteUser->id === $coachUser->id) {
                throw new CommerceException('self_coaching', 'رابطه شخص با خودش مجاز نیست.');
            }

            throw new IdentityException('national_code_mismatch', 'کد ملی با این شماره یکسان نیست.');
        }

        $profile = $athleteUser->athleteProfile;

        if ($profile === null) {
            $profile = AthleteProfile::query()->create([
                'user_id' => $athleteUser->id,
                'display_name' => $displayName,
                'status' => 'active',
            ]);
        }

        $relationship = CoachingRelationship::query()
            ->where('coach_profile_id', $coach->id)
            ->where('athlete_profile_id', $profile->id)
            ->first();

        if ($relationship?->status === 'active') {
            return [
                'created' => false,
                'invitation' => null,
                'relationship' => RelationshipView::forCoach($relationship),
            ];
        }

        $invitation = $this->openInvitation($coach, $coachUser, $athleteUser->phone);

        return [
            'created' => $invitation['created'],
            'invitation' => RelationshipView::invitation($invitation['invitation']),
            'relationship' => null,
        ];
    }

    /**
     * @return array{created: bool, invitation: AthleteInvitation}
     */
    private function openInvitation(CoachProfile $coach, User $coachUser, string $phone): array
    {
        $key = $coach->id.':'.$phone;

        if (strlen($key) > 32) {
            throw new CommerceException('invitation_key', 'کلید دعوت ساخته نشد.');
        }

        $existing = AthleteInvitation::query()->where('pending_key', $key)->lockForUpdate()->first();

        if ($existing !== null) {
            $this->clock->expireIfDue($existing);

            if ($existing->status === 'pending') {
                return [
                    'created' => false,
                    'invitation' => $existing,
                ];
            }
        }

        $invitation = AthleteInvitation::query()->create([
            'coach_profile_id' => $coach->id,
            'phone' => $phone,
            'status' => 'pending',
            'pending_key' => $key,
            'expires_at' => now()->addSeconds((int) config('fito.invitation.ttl_seconds')),
            'created_by_user_id' => $coachUser->id,
        ]);

        return [
            'created' => true,
            'invitation' => $invitation,
        ];
    }

    private function startRelationship(CoachProfile $coach, AthleteProfile $profile, User $actor): CoachingRelationship
    {
        $relationship = CoachingRelationship::query()->create([
            'coach_profile_id' => $coach->id,
            'athlete_profile_id' => $profile->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->event($relationship, 'started', $actor);

        return $relationship;
    }

    private function storeInvitation(
        CoachProfile $coach,
        User $coachUser,
        string $phone,
        string $status,
        User $athleteUser,
        CoachingRelationship $relationship,
    ): AthleteInvitation {
        return AthleteInvitation::query()->create([
            'coach_profile_id' => $coach->id,
            'phone' => $phone,
            'status' => $status,
            'pending_key' => null,
            'expires_at' => now()->addSeconds((int) config('fito.invitation.ttl_seconds')),
            'accepted_at' => now(),
            'accepted_by_user_id' => $athleteUser->id,
            'coaching_relationship_id' => $relationship->id,
            'created_by_user_id' => $coachUser->id,
        ]);
    }

    private function event(CoachingRelationship $relationship, string $type, User $actor): void
    {
        CoachingRelationshipEvent::query()->create([
            'coaching_relationship_id' => $relationship->id,
            'event_type' => $type,
            'actor_user_id' => $actor->id,
            'happened_at' => now(),
        ]);
    }

    private function requireActivePeriod(CoachProfile $coach): AccessPeriod
    {
        $active = $this->schedule->promote($coach, now());

        if ($active === null) {
            throw new CommerceException('no_access', 'دوره دسترسی فعالی نیست.', 403);
        }

        return $active;
    }

    private function displayName(string $displayName): string
    {
        $displayName = trim($displayName);

        if ($displayName === '' || mb_strlen($displayName) > 120) {
            throw new IdentityException('invalid_display_name', 'نام نمایشی معتبر نیست.');
        }

        return $displayName;
    }
}
