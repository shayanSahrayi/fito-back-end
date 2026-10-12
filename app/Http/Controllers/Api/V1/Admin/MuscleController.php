<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Catalog\AnatomyWriter;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\MuscleAreasRequest;
use App\Http\Requests\Catalog\MuscleRequest;
use App\Models\Muscle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MuscleController extends Controller
{
    public function index(Request $request, CommerceActor $actor, AnatomyWriter $anatomy): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json([
            'muscles' => $anatomy->muscles(),
        ]);
    }

    public function store(MuscleRequest $request, CommerceActor $actor, AnatomyWriter $anatomy): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($anatomy->createMuscle($request->validated()), 201);
    }

    public function show(Request $request, Muscle $muscle, CommerceActor $actor, AnatomyWriter $anatomy): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($anatomy->presentMuscle($muscle));
    }

    public function update(MuscleRequest $request, Muscle $muscle, CommerceActor $actor, AnatomyWriter $anatomy): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($anatomy->updateMuscle($muscle, $request->validated()));
    }

    public function syncAreas(MuscleAreasRequest $request, Muscle $muscle, CommerceActor $actor, AnatomyWriter $anatomy): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($anatomy->syncAreas($muscle, $request->validated('body_model_area_ids')));
    }
}
