<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Commerce\CommerceActor;
use App\Domain\Commerce\PlanWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlanController extends Controller
{
    public function index(Request $request, CommerceActor $actor, PlanWriter $plans): JsonResponse
    {
        $actor->admin($request->user());

        $rows = Plan::query()->with('features')->orderByDesc('id')->get();

        return response()->json([
            'plans' => $rows->map(fn (Plan $plan): array => $plans->present($plan))->all(),
        ]);
    }

    public function store(PlanRequest $request, CommerceActor $actor, PlanWriter $plans): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($plans->create($request->validated()), 201);
    }

    public function show(Request $request, Plan $plan, CommerceActor $actor, PlanWriter $plans): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($plans->present($plan));
    }

    public function update(PlanRequest $request, Plan $plan, CommerceActor $actor, PlanWriter $plans): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($plans->update($plan, $request->validated()));
    }

    public function destroy(Request $request, Plan $plan, CommerceActor $actor, PlanWriter $plans): Response
    {
        $actor->admin($request->user());
        $plans->delete($plan);

        return response()->noContent();
    }
}
