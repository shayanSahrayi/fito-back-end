<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Commerce\AccessReader;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    public function show(Request $request, CommerceActor $actor, AccessReader $access): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($access->current($coach));
    }
}
