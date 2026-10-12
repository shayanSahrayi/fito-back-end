<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Commerce\CommerceActor;
use App\Domain\Nutrition\MealAssigner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nutrition\AssignMealRequest;
use App\Models\MealAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealAssignmentController extends Controller
{
    public function index(Request $request, CommerceActor $actor, MealAssigner $assignments): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'assignments' => $assignments->forCoach($coach),
        ]);
    }

    public function store(AssignMealRequest $request, CommerceActor $actor, MealAssigner $assignments): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assignments->assign($request->user(), $coach, $request->validated()), 201);
    }

    public function end(Request $request, MealAssignment $mealAssignment, CommerceActor $actor, MealAssigner $assignments): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assignments->end($coach, $mealAssignment));
    }
}
