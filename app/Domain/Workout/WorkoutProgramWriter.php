<?php

namespace App\Domain\Workout;

use App\Domain\Catalog\CatalogRules;
use App\Models\CoachProfile;
use App\Models\User;
use App\Models\WorkoutProgram;
use App\Models\WorkoutProgramVersion;
use Illuminate\Support\Facades\DB;

final class WorkoutProgramWriter
{
    public function __construct(
        private readonly WorkoutAccess $access,
        private readonly Prescription $prescription,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(User $user, CoachProfile $coach, array $data): array
    {
        return DB::transaction(function () use ($user, $coach, $data): array {
            $this->access->assert($coach);

            $program = WorkoutProgram::query()->create([
                'coach_profile_id' => $coach->id,
                'title' => $this->title($data['title'] ?? null),
                'status' => 'draft',
                'current_version_id' => null,
            ]);

            $version = $this->newVersion($program, $user, 1, $data['note'] ?? null);
            $this->prescription->replace($version, $data['days'] ?? [], $coach);

            return $this->present($program);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateDraft(CoachProfile $coach, WorkoutProgram $program, array $data): array
    {
        return DB::transaction(function () use ($coach, $program, $data): array {
            $locked = $this->lockOwned($coach, $program);
            $this->assertWritable($locked);
            $this->access->assert($coach);
            $draft = $this->openDraft($locked);

            if (array_key_exists('title', $data)) {
                $locked->title = $this->title($data['title']);
                $locked->save();
            }

            if (array_key_exists('note', $data)) {
                $draft->note = CatalogRules::optionalText($data['note']);
                $draft->save();
            }

            if (array_key_exists('days', $data)) {
                $this->prescription->replace($draft, $data['days'], $coach);
            }

            return $this->present($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(CoachProfile $coach, WorkoutProgram $program): array
    {
        return DB::transaction(function () use ($coach, $program): array {
            $locked = $this->lockOwned($coach, $program);
            $this->assertWritable($locked);
            $this->access->assert($coach);
            $draft = $this->openDraft($locked);
            $draft->load('days.exercises');

            $empty = $draft->days->isEmpty()
                || $draft->days->contains(fn ($day): bool => $day->exercises->isEmpty());

            if ($empty) {
                throw new WorkoutException('days_required', 'برنامه حداقل یک روز و یک حرکت لازم دارد.');
            }

            $draft->forceFill([
                'state' => 'published',
                'published_at' => now(),
            ])->save();

            $locked->forceFill([
                'status' => 'published',
                'current_version_id' => $draft->id,
            ])->save();

            return $this->present($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function openNextDraft(User $user, CoachProfile $coach, WorkoutProgram $program): array
    {
        return DB::transaction(function () use ($user, $coach, $program): array {
            $locked = $this->lockOwned($coach, $program);
            $this->assertWritable($locked);
            $this->access->assert($coach);

            if ($this->findDraft($locked) !== null) {
                throw new WorkoutException('draft_open', 'یک پیش‌نویس باز وجود دارد.');
            }

            if ($locked->current_version_id === null) {
                throw new WorkoutException('nothing_published', 'اول این برنامه را منتشر کن.');
            }

            $current = WorkoutProgramVersion::query()->find($locked->current_version_id);

            if ($current === null || $current->state !== 'published') {
                throw new WorkoutException('nothing_published', 'اول این برنامه را منتشر کن.');
            }

            $next = $this->newVersion(
                $locked,
                $user,
                ((int) WorkoutProgramVersion::query()->where('workout_program_id', $locked->id)->max('version_number')) + 1,
                $current->note,
            );
            $this->prescription->copy($current, $next);

            return $this->present($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(CoachProfile $coach, WorkoutProgram $program): array
    {
        return DB::transaction(function () use ($coach, $program): array {
            $locked = $this->lockOwned($coach, $program);
            $this->access->assert($coach);
            $locked->status = 'archived';
            $locked->save();

            return $this->present($locked);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCoach(CoachProfile $coach): array
    {
        return WorkoutProgram::query()
            ->where('coach_profile_id', $coach->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (WorkoutProgram $program): array => $this->summary($program))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(CoachProfile $coach, WorkoutProgram $program): array
    {
        $this->assertOwned($coach, $program);

        return $this->present($program);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(WorkoutProgram $program): array
    {
        $program->refresh();
        $draft = $this->findDraft($program);
        $current = $program->current_version_id === null
            ? null
            : WorkoutProgramVersion::query()->find($program->current_version_id);

        return [
            'id' => $program->id,
            'title' => $program->title,
            'status' => $program->status,
            'current_version_id' => $program->current_version_id === null ? null : (int) $program->current_version_id,
            'current_version' => $current === null ? null : $this->prescription->presentVersion($current),
            'draft' => $draft === null ? null : $this->prescription->presentVersion($draft),
        ];
    }

    private function newVersion(WorkoutProgram $program, User $user, int $number, mixed $note): WorkoutProgramVersion
    {
        return $program->versions()->create([
            'version_number' => $number,
            'state' => 'draft',
            'note' => CatalogRules::optionalText($note),
            'published_at' => null,
            'created_by_user_id' => $user->id,
        ]);
    }

    private function lockOwned(CoachProfile $coach, WorkoutProgram $program): WorkoutProgram
    {
        $locked = WorkoutProgram::query()->whereKey($program->id)->lockForUpdate()->first();

        if ($locked === null) {
            throw new WorkoutException('program_not_found', 'برنامه پیدا نشد.', 404);
        }

        $this->assertOwned($coach, $locked);

        return $locked;
    }

    private function assertOwned(CoachProfile $coach, WorkoutProgram $program): void
    {
        if ((int) $program->coach_profile_id !== $coach->id) {
            throw new WorkoutException('program_not_found', 'برنامه پیدا نشد.', 404);
        }
    }

    private function assertWritable(WorkoutProgram $program): void
    {
        if ($program->status === 'archived') {
            throw new WorkoutException('program_archived', 'برنامه آرشیو شده است.');
        }
    }

    private function openDraft(WorkoutProgram $program): WorkoutProgramVersion
    {
        $draft = $this->findDraft($program);

        if ($draft === null) {
            throw new WorkoutException('draft_required', 'برای ویرایش اول باید پیش‌نویس باز شود.');
        }

        return $draft;
    }

    private function findDraft(WorkoutProgram $program): ?WorkoutProgramVersion
    {
        return WorkoutProgramVersion::query()
            ->where('workout_program_id', $program->id)
            ->where('state', 'draft')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WorkoutProgram $program): array
    {
        $draftId = WorkoutProgramVersion::query()
            ->where('workout_program_id', $program->id)
            ->where('state', 'draft')
            ->orderByDesc('id')
            ->value('id');

        return [
            'id' => $program->id,
            'title' => $program->title,
            'status' => $program->status,
            'current_version_id' => $program->current_version_id === null ? null : (int) $program->current_version_id,
            'draft_version_id' => $draftId === null ? null : (int) $draftId,
        ];
    }

    private function title(mixed $value): string
    {
        return CatalogRules::boundedText($value, 160, 'invalid_title', 'عنوان برنامه معتبر نیست.');
    }
}
