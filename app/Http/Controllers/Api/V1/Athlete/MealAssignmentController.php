<?php

namespace App\Http\Controllers\Api\V1\Athlete;

use App\Domain\Nutrition\MealAssigner;
use App\Http\Controllers\Controller;
use App\Models\MealAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealAssignmentController extends Controller
{
    public function index(Request $request, MealAssigner $assignments): JsonResponse
    {
        return response()->json([
            'assignments' => $assignments->forAthlete($request->user()),
        ]);
    }

    public function show(Request $request, MealAssignment $mealAssignment, MealAssigner $assignments): JsonResponse
    {
        return response()->json($assignments->showForAthlete($request->user(), $mealAssignment));
    }
}
