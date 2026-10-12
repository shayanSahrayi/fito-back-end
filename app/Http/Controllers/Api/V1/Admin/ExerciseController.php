<?php

namespace App\Http\Controllers\Api\V1\Admin;

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
        $actor->admin($request->user());

        return response()->json([
            'exercises' => $exercises->forAdmin(),
        ]);
    }

    public function store(SaveExerciseRequest $request, CommerceActor $actor, ExerciseWriter $exercises): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($exercises->createPlatform($request->user(), $request->validated()), 201);
    }

    public function show(Request $request, Exercise $exercise, CommerceActor $actor, ExerciseWriter $exercises): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($exercises->showForAdmin($exercise));
    }

    public function update(SaveExerciseRequest $request, Exercise $exercise, CommerceActor $actor, ExerciseWriter $exercises): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($exercises->updatePlatform($exercise, $request->validated()));
    }

    public function archive(Request $request, Exercise $exercise, CommerceActor $actor, ExerciseWriter $exercises): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($exercises->archivePlatform($exercise));
    }
}
