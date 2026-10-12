<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Commerce\CommerceActor;
use App\Domain\Workout\WorkoutAssigner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\AssignWorkoutRequest;
use App\Models\WorkoutAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutAssignmentController extends Controller
{
    public function index(Request $request, CommerceActor $actor, WorkoutAssigner $assignments): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'assignments' => $assignments->forCoach($coach),
        ]);
    }

    public function store(AssignWorkoutRequest $request, CommerceActor $actor, WorkoutAssigner $assignments): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assignments->assign($request->user(), $coach, $request->validated()), 201);
    }

    public function end(Request $request, WorkoutAssignment $workoutAssignment, CommerceActor $actor, WorkoutAssigner $assignments): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assignments->end($coach, $workoutAssignment));
    }
}
