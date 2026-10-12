<?php

namespace App\Domain\Body;

use App\Domain\Commerce\CommerceException;
use App\Domain\Identity\AccountStatus;
use App\Models\AthleteProfile;
use App\Models\BodyPhoto;
use App\Models\CoachProfile;
use App\Models\CoachingRelationship;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BodyPhotos
{
    public function __construct(private readonly BodyAccess $access) {}

    /**
     * @return array<string, mixed>
     */
    public function storeAsAthlete(User $user, UploadedFile $file, array $data): array
    {
        $athlete = $this->athlete($user);

        return DB::transaction(function () use ($user, $athlete, $file, $data): array {
            $relationship = null;
            $relationshipId = $data['coaching_relationship_id'] ?? null;

            if ($relationshipId !== null && $relationshipId !== '') {
                $relationship = CoachingRelationship::query()->whereKey((int) $relationshipId)->first();

                if ($relationship === null || (int) $relationship->athlete_profile_id !== $athlete->id) {
                    throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
                }

                if ($relationship->status !== 'active') {
                    throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
                }
            }

            return $this->store($user, $athlete, $relationship, $file, $data['taken_at'] ?? null);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function storeAsCoach(User $user, CoachProfile $coach, CoachingRelationship $relationship, UploadedFile $file, array $data): array
    {
        AccountStatus::assertActive($user);

        return DB::transaction(function () use ($user, $coach, $relationship, $file, $data): array {
            $locked = CoachingRelationship::query()->whereKey($relationship->id)->lockForUpdate()->first();

            if ($locked === null || (int) $locked->coach_profile_id !== $coach->id) {
                throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
            }

            if ($locked->status !== 'active') {
                throw new CommerceException('relationship_not_active', 'این رابطه فعال نیست.');
            }

            $this->access->assert($coach);
            $athlete = AthleteProfile::query()->find($locked->athlete_profile_id);

            if ($athlete === null) {
                throw new BodyException('athlete_profile_required', 'ورزشکار این رابطه پیدا نشد.', 404);
            }

            return $this->store($user, $athlete, $locked, $file, $data['taken_at'] ?? null);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAthlete(User $user): array
    {
        $athlete = $this->athlete($user);

        return BodyPhoto::query()
            ->where('athlete_profile_id', $athlete->id)
            ->orderByDesc('taken_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BodyPhoto $photo): array => $this->present($photo))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCoach(CoachProfile $coach, CoachingRelationship $relationship): array
    {
        $locked = $this->activeCoachRelationship($coach, $relationship);

        return BodyPhoto::query()
            ->where('coaching_relationship_id', $locked->id)
            ->orderByDesc('taken_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BodyPhoto $photo): array => $this->present($photo))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showForAthlete(User $user, BodyPhoto $photo): array
    {
        $this->ownedByAthlete($user, $photo);

        return $this->present($photo);
    }

    /**
     * @return array<string, mixed>
     */
    public function showForCoach(CoachProfile $coach, BodyPhoto $photo): array
    {
        $this->visibleToCoach($coach, $photo);

        return $this->present($photo);
    }

    public function pathForAthlete(User $user, BodyPhoto $photo): string
    {
        $this->ownedByAthlete($user, $photo);

        return $photo->storage_path;
    }

    public function pathForCoach(CoachProfile $coach, BodyPhoto $photo): string
    {
        $this->visibleToCoach($coach, $photo);

        return $photo->storage_path;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function store(
        User $user,
        AthleteProfile $athlete,
        ?CoachingRelationship $relationship,
        UploadedFile $file,
        mixed $takenAt,
    ): array {
        $taken = $this->moment($takenAt);

        if ($relationship !== null && $taken->lt($relationship->started_at)) {
            throw new BodyException('measured_before_contract', 'زمان عکس قبل از شروع قرارداد است.');
        }

        $extension = $file->guessExtension() ?: 'jpg';
        $path = 'body-photos/'.$athlete->id.'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk('local')->putFileAs(
            'body-photos/'.$athlete->id,
            $file,
            basename($path),
        );

        $photo = BodyPhoto::query()->create([
            'athlete_profile_id' => $athlete->id,
            'coaching_relationship_id' => $relationship?->id,
            'uploaded_by_user_id' => $user->id,
            'storage_path' => $path,
            'taken_at' => $taken,
            'sent_to_coach_at' => $relationship === null ? null : now(),
        ]);

        return $this->present($photo);
    }

    private function visibleToCoach(CoachProfile $coach, BodyPhoto $photo): void
    {
        if ($photo->coaching_relationship_id === null) {
            throw new BodyException('photo_not_found', 'عکس پیدا نشد.', 404);
        }

        $relationship = CoachingRelationship::query()->find($photo->coaching_relationship_id);

        if ($relationship === null || (int) $relationship->coach_profile_id !== $coach->id) {
            throw new BodyException('photo_not_found', 'عکس پیدا نشد.', 404);
        }

        if ($relationship->status !== 'active') {
            throw new BodyException('photo_closed', 'عکس بدن بعد از پایان رابطه برای مربی بسته است.', 403);
        }
    }

    private function ownedByAthlete(User $user, BodyPhoto $photo): void
    {
        $athlete = $this->athlete($user);

        if ((int) $photo->athlete_profile_id !== $athlete->id) {
            throw new BodyException('photo_not_found', 'عکس پیدا نشد.', 404);
        }
    }

    private function activeCoachRelationship(CoachProfile $coach, CoachingRelationship $relationship): CoachingRelationship
    {
        $locked = CoachingRelationship::query()->find($relationship->id);

        if ($locked === null || (int) $locked->coach_profile_id !== $coach->id) {
            throw new CommerceException('relationship_not_found', 'رابطه پیدا نشد.', 404);
        }

        if ($locked->status !== 'active') {
            throw new BodyException('photo_closed', 'عکس بدن بعد از پایان رابطه برای مربی بسته است.', 403);
        }

        return $locked;
    }

    private function athlete(User $user): AthleteProfile
    {
        AccountStatus::assertActive($user);
        $athlete = $user->athleteProfile;

        if ($athlete === null) {
            throw new BodyException('athlete_profile_required', 'این مسیر فقط برای ورزشکار است.', 403);
        }

        return $athlete;
    }

    private function moment(mixed $value): Carbon
    {
        $text = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})?$/', $text) !== 1) {
            throw new BodyException('invalid_time', 'زمان عکس معتبر نیست.');
        }

        return Carbon::parse($text)->utc();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BodyPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'athlete_profile_id' => (int) $photo->athlete_profile_id,
            'coaching_relationship_id' => $photo->coaching_relationship_id === null ? null : (int) $photo->coaching_relationship_id,
            'taken_at' => $photo->taken_at->toJSON(),
            'sent_to_coach_at' => $photo->sent_to_coach_at?->toJSON(),
        ];
    }
}
