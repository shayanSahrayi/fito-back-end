<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Commerce\CommerceActor;
use App\Domain\Workout\WorkoutProgramWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\SaveWorkoutProgramRequest;
use App\Models\WorkoutProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutProgramController extends Controller
{
    public function index(Request $request, CommerceActor $actor, WorkoutProgramWriter $programs): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'programs' => $programs->forCoach($coach),
        ]);
    }

    public function store(SaveWorkoutProgramRequest $request, CommerceActor $actor, WorkoutProgramWriter $programs): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($programs->create($request->user(), $coach, $request->validated()), 201);
    }

    public function show(Request $request, WorkoutProgram $workoutProgram, CommerceActor $actor, WorkoutProgramWriter $programs): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($programs->show($coach, $workoutProgram));
    }

    public function update(SaveWorkoutProgramRequest $request, WorkoutProgram $workoutProgram, CommerceActor $actor, WorkoutProgramWriter $programs): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($programs->updateDraft($coach, $workoutProgram, $request->validated()));
    }

    public function publish(Request $request, WorkoutProgram $workoutProgram, CommerceActor $actor, WorkoutProgramWriter $programs): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($programs->publish($coach, $workoutProgram));
    }

    public function draft(Request $request, WorkoutProgram $workoutProgram, CommerceActor $actor, WorkoutProgramWriter $programs): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($programs->openNextDraft($request->user(), $coach, $workoutProgram));
    }

    public function archive(Request $request, WorkoutProgram $workoutProgram, CommerceActor $actor, WorkoutProgramWriter $programs): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($programs->archive($coach, $workoutProgram));
    }
}
