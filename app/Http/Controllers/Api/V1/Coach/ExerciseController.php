<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Catalog\ExerciseWriter;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveExerciseRequest;
use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function index(Request $request, CommerceActor $actor, ExerciseWriter $exercises): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'exercises' => $exercises->forCoach($coach),
        ]);
    }

    public function store(SaveExerciseRequest $request, CommerceActor $actor, ExerciseWriter $exercises): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($exercises->createPrivate($request->user(), $coach, $request->validated()), 201);
    }

    public function show(Request $request, Exercise $exercise, CommerceActor $actor, ExerciseWriter $exercises): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($exercises->showForCoach($coach, $exercise));
    }

    public function update(SaveExerciseRequest $request, Exercise $exercise, CommerceActor $actor, ExerciseWriter $exercises): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($exercises->updatePrivate($coach, $exercise, $request->validated()));
    }

    public function archive(Request $request, Exercise $exercise, CommerceActor $actor, ExerciseWriter $exercises): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($exercises->archivePrivate($coach, $exercise));
    }
}
