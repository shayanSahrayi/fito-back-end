<?php

namespace App\Domain\Catalog;

use App\Models\CoachProfile;
use App\Models\Exercise;
use App\Models\ExerciseMuscle;
use App\Models\Muscle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ExerciseWriter
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPlatform(User $admin, array $data): array
    {
        return $this->create($admin, null, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPrivate(User $coach, CoachProfile $profile, array $data): array
    {
        return $this->create($coach, $profile->id, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePlatform(Exercise $exercise, array $data): array
    {
        $this->assertPlatform($exercise);

        return $this->update($exercise, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePrivate(CoachProfile $coach, Exercise $exercise, array $data): array
    {
        $this->assertOwn($coach, $exercise);

        return $this->update($exercise, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function archivePlatform(Exercise $exercise): array
    {
        $this->assertPlatform($exercise);

        return $this->archive($exercise);
    }

    /**
     * @return array<string, mixed>
     */
    public function archivePrivate(CoachProfile $coach, Exercise $exercise): array
    {
        $this->assertOwn($coach, $exercise);

        return $this->archive($exercise);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAdmin(): array
    {
        return Exercise::query()
            ->with(['exerciseMuscles.muscle', 'media'])
            ->whereNull('coach_profile_id')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Exercise $exercise): array => $this->present($exercise))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCoach(CoachProfile $coach): array
    {
        return Exercise::query()
            ->with(['exerciseMuscles.muscle', 'media'])
            ->where(function ($query) use ($coach): void {
                $query->where(function ($bank): void {
                    $bank->whereNull('coach_profile_id')->where('status', 'active');
                })->orWhere('coach_profile_id', $coach->id);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Exercise $exercise): array => $this->present($exercise))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showForAdmin(Exercise $exercise): array
    {
        $this->assertPlatform($exercise);

        return $this->present($exercise);
    }

    /**
     * @return array<string, mixed>
     */
    public function showForCoach(CoachProfile $coach, Exercise $exercise): array
    {
        $isPlatform = $exercise->coach_profile_id === null;
        $visible = $isPlatform
            ? $exercise->status === 'active'
            : (int) $exercise->coach_profile_id === $coach->id;

        if (! $visible) {
            throw new CatalogException('exercise_not_found', 'حرکت پیدا نشد.', 404);
        }

        return $this->present($exercise);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Exercise $exercise): array
    {
        $exercise->loadMissing(['exerciseMuscles.muscle', 'media']);

        return [
            'id' => $exercise->id,
            'coach_profile_id' => $exercise->coach_profile_id === null ? null : (int) $exercise->coach_profile_id,
            'name' => $exercise->name,
            'instructions' => $exercise->instructions,
            'goal' => $exercise->goal,
            'tracking_type' => $exercise->tracking_type,
            'status' => $exercise->status,
            'muscles' => $exercise->exerciseMuscles
                ->sortBy('id')
                ->map(function (ExerciseMuscle $row): ?array {
                    if ($row->muscle === null) {
                        return null;
                    }

                    return [
                        'muscle_id' => (int) $row->muscle_id,
                        'code' => $row->muscle->code,
                        'name_fa' => $row->muscle->name_fa,
                        'involvement' => $row->involvement,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
            'media' => $exercise->media
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'media_type' => $media->media_type,
                    'path' => $media->path,
                    'sort_order' => $media->sort_order,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function create(User $user, ?int $coachProfileId, array $data): array
    {
        return DB::transaction(function () use ($user, $coachProfileId, $data): array {
            $exercise = Exercise::query()->create([
                'coach_profile_id' => $coachProfileId,
                'name' => CatalogRules::boundedText($data['name'] ?? null, 160, 'invalid_name', 'نام حرکت معتبر نیست.'),
                'instructions' => CatalogRules::requiredText($data['instructions'] ?? null, 'invalid_instructions', 'آموزش حرکت لازم است.'),
                'goal' => CatalogRules::requiredText($data['goal'] ?? null, 'invalid_goal', 'هدف حرکت لازم است.'),
                'tracking_type' => CatalogRules::tracking($data['tracking_type'] ?? null),
                'status' => 'active',
                'created_by_user_id' => $user->id,
            ]);

            $this->replaceMuscles($exercise, $data['muscles'] ?? []);
            $this->replaceMedia($exercise, $data['media'] ?? []);

            return $this->present($exercise);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function update(Exercise $exercise, array $data): array
    {
        return DB::transaction(function () use ($exercise, $data): array {
            $locked = Exercise::query()->whereKey($exercise->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw new CatalogException('exercise_not_found', 'حرکت پیدا نشد.', 404);
            }

            if (array_key_exists('name', $data)) {
                $locked->name = CatalogRules::boundedText($data['name'], 160, 'invalid_name', 'نام حرکت معتبر نیست.');
            }

            if (array_key_exists('instructions', $data)) {
                $locked->instructions = CatalogRules::requiredText($data['instructions'], 'invalid_instructions', 'آموزش حرکت لازم است.');
            }

            if (array_key_exists('goal', $data)) {
                $locked->goal = CatalogRules::requiredText($data['goal'], 'invalid_goal', 'هدف حرکت لازم است.');
            }

            if (array_key_exists('tracking_type', $data)) {
                $locked->tracking_type = CatalogRules::tracking($data['tracking_type']);
            }

            $locked->save();

            if (array_key_exists('muscles', $data)) {
                $this->replaceMuscles($locked, $data['muscles']);
            }

            if (array_key_exists('media', $data)) {
                $this->replaceMedia($locked, $data['media']);
            }

            return $this->present($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function archive(Exercise $exercise): array
    {
        $exercise->status = 'archived';
        $exercise->save();

        return $this->present($exercise);
    }

    /**
     * @param  list<array<string, mixed>>  $muscles
     */
    private function replaceMuscles(Exercise $exercise, array $muscles): void
    {
        if ($muscles === []) {
            throw new CatalogException('muscles_required', 'حداقل یک عضله برای حرکت لازم است.');
        }

        $rows = [];
        $seen = [];

        foreach ($muscles as $muscle) {
            $id = (int) ($muscle['muscle_id'] ?? 0);

            if (isset($seen[$id])) {
                throw new CatalogException('muscle_duplicate', 'هر عضله فقط یک بار در حرکت می‌آید.');
            }

            $involvement = (string) ($muscle['involvement'] ?? '');

            if (! in_array($involvement, CatalogRules::INVOLVEMENTS, true)) {
                throw new CatalogException('invalid_involvement', 'نوع درگیری عضله معتبر نیست.');
            }

            $seen[$id] = true;
            $rows[] = [
                'muscle_id' => $id,
                'involvement' => $involvement,
            ];
        }

        $found = Muscle::query()->whereIn('id', array_keys($seen))->count();

        if ($found !== count($seen)) {
            throw new CatalogException('muscle_not_found', 'عضله پیدا نشد.');
        }

        $exercise->exerciseMuscles()->delete();

        foreach ($rows as $row) {
            $exercise->exerciseMuscles()->create($row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $media
     */
    private function replaceMedia(Exercise $exercise, array $media): void
    {
        $exercise->media()->delete();

        foreach ($media as $item) {
            $type = (string) ($item['media_type'] ?? '');

            if (! in_array($type, CatalogRules::MEDIA_TYPES, true)) {
                throw new CatalogException('invalid_media_type', 'نوع رسانه معتبر نیست.');
            }

            $path = trim((string) ($item['path'] ?? ''));

            if ($path === '' || mb_strlen($path) > 255) {
                throw new CatalogException('invalid_media_path', 'مسیر رسانه معتبر نیست.');
            }

            $sort = $item['sort_order'] ?? null;

            if (! is_int($sort) && ! (is_string($sort) && ctype_digit($sort))) {
                throw new CatalogException('invalid_media_order', 'ترتیب رسانه معتبر نیست.');
            }

            $sort = (int) $sort;

            if ($sort < 0 || $sort > 65535) {
                throw new CatalogException('invalid_media_order', 'ترتیب رسانه معتبر نیست.');
            }

            $exercise->media()->create([
                'media_type' => $type,
                'path' => $path,
                'sort_order' => $sort,
            ]);
        }
    }

    private function assertPlatform(Exercise $exercise): void
    {
        if ($exercise->coach_profile_id !== null) {
            throw new CatalogException('exercise_not_found', 'حرکت پیدا نشد.', 404);
        }
    }

    private function assertOwn(CoachProfile $coach, Exercise $exercise): void
    {
        if ($exercise->coach_profile_id === null) {
            throw new CatalogException('platform_exercise_locked', 'حرکت بانک فقط توسط مدیر ویرایش می‌شود.', 403);
        }

        if ((int) $exercise->coach_profile_id !== $coach->id) {
            throw new CatalogException('exercise_not_found', 'حرکت پیدا نشد.', 404);
        }
    }
}
