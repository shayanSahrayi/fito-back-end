<?php

namespace App\Http\Controllers\Api\V1\Admin;

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
        $actor->admin($request->user());

        return response()->json([
            'foods' => $foods->forAdmin(),
        ]);
    }

    public function store(SaveFoodRequest $request, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($foods->createPlatform($request->validated()), 201);
    }

    public function show(Request $request, Food $food, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($foods->showForAdmin($food));
    }

    public function update(RenameFoodRequest $request, Food $food, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($foods->renamePlatform($food, $request->validated()));
    }

    public function revise(ReviseFoodRequest $request, Food $food, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($foods->revisePlatform($food, $request->validated()));
    }

    public function archive(Request $request, Food $food, CommerceActor $actor, FoodCatalog $foods): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($foods->archivePlatform($food));
    }
}
