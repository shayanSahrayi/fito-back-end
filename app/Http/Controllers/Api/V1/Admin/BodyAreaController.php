<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Catalog\AnatomyWriter;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\BodyAreaRequest;
use App\Models\BodyModelArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BodyAreaController extends Controller
{
    public function index(Request $request, CommerceActor $actor, AnatomyWriter $anatomy): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json([
            'areas' => $anatomy->areas(),
        ]);
    }

    public function store(BodyAreaRequest $request, CommerceActor $actor, AnatomyWriter $anatomy): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($anatomy->createArea($request->validated()), 201);
    }

    public function update(BodyAreaRequest $request, BodyModelArea $bodyArea, CommerceActor $actor, AnatomyWriter $anatomy): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($anatomy->updateArea($bodyArea, $request->validated()));
    }
}
