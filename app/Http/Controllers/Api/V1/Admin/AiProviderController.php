<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Ai\PlatformProvider;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiProviderController extends Controller
{
    public function show(Request $request, CommerceActor $actor, PlatformProvider $providers): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($providers->show());
    }

    public function update(Request $request, CommerceActor $actor, PlatformProvider $providers): JsonResponse
    {
        $admin = $actor->admin($request->user());

        return response()->json($providers->save($admin, $request->all()));
    }
}
