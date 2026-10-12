<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Commerce\CommerceActor;
use App\Domain\Nutrition\MealPlanWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nutrition\SaveMealPlanRequest;
use App\Models\MealPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPlanController extends Controller
{
    public function index(Request $request, CommerceActor $actor, MealPlanWriter $plans): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'meal_plans' => $plans->forCoach($coach),
        ]);
    }

    public function store(SaveMealPlanRequest $request, CommerceActor $actor, MealPlanWriter $plans): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($plans->create($request->user(), $coach, $request->validated()), 201);
    }

    public function show(Request $request, MealPlan $mealPlan, CommerceActor $actor, MealPlanWriter $plans): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($plans->show($coach, $mealPlan));
    }

    public function update(SaveMealPlanRequest $request, MealPlan $mealPlan, CommerceActor $actor, MealPlanWriter $plans): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($plans->updateDraft($coach, $mealPlan, $request->validated()));
    }

    public function publish(Request $request, MealPlan $mealPlan, CommerceActor $actor, MealPlanWriter $plans): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($plans->publish($coach, $mealPlan));
    }

    public function draft(Request $request, MealPlan $mealPlan, CommerceActor $actor, MealPlanWriter $plans): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($plans->openNextDraft($request->user(), $coach, $mealPlan));
    }

    public function archive(Request $request, MealPlan $mealPlan, CommerceActor $actor, MealPlanWriter $plans): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($plans->archive($coach, $mealPlan));
    }
}
