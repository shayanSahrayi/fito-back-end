<?php

namespace App\Http\Controllers\Api\V1\Athlete;

use App\Domain\Workout\WorkoutAssigner;
use App\Http\Controllers\Controller;
use App\Models\WorkoutAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutAssignmentController extends Controller
{
    public function index(Request $request, WorkoutAssigner $assignments): JsonResponse
    {
        return response()->json([
            'assignments' => $assignments->forAthlete($request->user()),
        ]);
    }

    public function show(Request $request, WorkoutAssignment $workoutAssignment, WorkoutAssigner $assignments): JsonResponse
    {
        return response()->json($assignments->showForAthlete($request->user(), $workoutAssignment));
    }
}
