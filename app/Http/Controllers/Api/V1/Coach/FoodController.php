<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Commerce\CommerceActor;
use App\Domain\Nutrition\FoodCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nutrition\RenameFoodRequest;
use App\Http\Requests\Nutrition\ReviseFoodRequest;
use App\Http\Requests\Nutrition\SaveFoodRequest;
use App\Models\Food;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoodController extends Controller
{
    public function index(Request $request, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'foods' => $foods->forCoach($coach),
        ]);
    }

    public function store(SaveFoodRequest $request, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($foods->createPrivate($coach, $request->validated()), 201);
    }

    public function show(Request $request, Food $food, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($foods->showForCoach($coach, $food));
    }

    public function update(RenameFoodRequest $request, Food $food, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($foods->renamePrivate($coach, $food, $request->validated()));
    }

    public function revise(ReviseFoodRequest $request, Food $food, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($foods->revisePrivate($coach, $food, $request->validated()));
    }

    public function archive(Request $request, Food $food, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($foods->archivePrivate($coach, $food));
    }
}
