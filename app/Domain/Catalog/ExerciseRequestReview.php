<?php

namespace App\Domain\Catalog;

use App\Models\CoachProfile;
use App\Models\ExerciseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ExerciseRequestReview
{
    public function __construct(private readonly ExerciseWriter $exercises) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function submit(CoachProfile $coach, array $data): array
    {
        $request = ExerciseRequest::query()->create([
            'coach_profile_id' => $coach->id,
            'proposed_name' => CatalogRules::boundedText($data['proposed_name'] ?? null, 160, 'invalid_name', 'نام حرکت معتبر نیست.'),
            'instructions' => CatalogRules::requiredText($data['instructions'] ?? null, 'invalid_instructions', 'آموزش حرکت لازم است.'),
            'goal' => CatalogRules::requiredText($data['goal'] ?? null, 'invalid_goal', 'هدف حرکت لازم است.'),
            'tracking_type' => CatalogRules::tracking($data['tracking_type'] ?? null),
            'status' => 'pending',
        ]);

        return $this->present($request);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAdmin(): array
    {
        return ExerciseRequest::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (ExerciseRequest $request): array => $this->present($request))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function approve(User $admin, ExerciseRequest $request, array $data): array
    {
        return DB::transaction(function () use ($admin, $request, $data): array {
            $locked = $this->lockPending($request);
            $exercise = $this->exercises->createPlatform($admin, [
                'name' => $locked->proposed_name,
                'instructions' => $locked->instructions,
                'goal' => $locked->goal,
                'tracking_type' => $locked->tracking_type,
                'muscles' => $data['muscles'] ?? [],
                'media' => $data['media'] ?? [],
            ]);

            $locked->status = 'approved';
            $locked->reviewed_by_user_id = $admin->id;
            $locked->exercise_id = $exercise['id'];
            $locked->save();

            return array_merge($this->present($locked), [
                'exercise' => $exercise,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function reject(User $admin, ExerciseRequest $request, array $data): array
    {
        return DB::transaction(function () use ($admin, $request, $data): array {
            $locked = $this->lockPending($request);
            $locked->status = 'rejected';
            $locked->review_note = CatalogRules::optionalText($data['review_note'] ?? null, 500);
            $locked->reviewed_by_user_id = $admin->id;
            $locked->save();

            return $this->present($locked);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ExerciseRequest $request): array
    {
        return [
            'id' => $request->id,
            'coach_profile_id' => (int) $request->coach_profile_id,
            'proposed_name' => $request->proposed_name,
            'instructions' => $request->instructions,
            'goal' => $request->goal,
            'tracking_type' => $request->tracking_type,
            'status' => $request->status,
            'review_note' => $request->review_note,
            'exercise_id' => $request->exercise_id === null ? null : (int) $request->exercise_id,
        ];
    }

    private function lockPending(ExerciseRequest $request): ExerciseRequest
    {
        $locked = ExerciseRequest::query()->whereKey($request->id)->lockForUpdate()->first();

        if ($locked === null) {
            throw new CatalogException('exercise_request_not_found', 'درخواست حرکت پیدا نشد.', 404);
        }

        if ($locked->status !== 'pending') {
            throw new CatalogException('already_reviewed', 'این درخواست قبلاً بررسی شده است.');
        }

        return $locked;
    }
}
