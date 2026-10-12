<?php

namespace App\Http\Controllers\Api\V1\Athlete;

use App\Domain\Workout\SessionLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\SaveWorkoutSessionRequest;
use App\Http\Requests\Workout\StoreWorkoutSetRequest;
use App\Models\WorkoutSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutSessionController extends Controller
{
    public function index(Request $request, SessionLogger $sessions): JsonResponse
    {
        return response()->json([
            'sessions' => $sessions->forAthlete($request->user()),
        ]);
    }

    public function store(SaveWorkoutSessionRequest $request, SessionLogger $sessions): JsonResponse
    {
        return response()->json($sessions->start($request->user(), $request->validated()), 201);
    }

    public function show(Request $request, WorkoutSession $workoutSession, SessionLogger $sessions): JsonResponse
    {
        return response()->json($sessions->show($request->user(), $workoutSession));
    }

    public function storeSet(StoreWorkoutSetRequest $request, WorkoutSession $workoutSession, SessionLogger $sessions): JsonResponse
    {
        return response()->json($sessions->addSet($request->user(), $workoutSession, $request->validated()), 201);
    }

    public function complete(Request $request, WorkoutSession $workoutSession, SessionLogger $sessions): JsonResponse
    {
        return response()->json($sessions->complete($request->user(), $workoutSession));
    }
}
