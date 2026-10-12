<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Commerce\CommerceActor;
use App\Domain\Commerce\OrderOpener;
use App\Domain\Commerce\PaymentAttemptStarter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\OpenOrderRequest;
use App\Http\Requests\Commerce\StartAttemptRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(OpenOrderRequest $request, CommerceActor $actor, OrderOpener $opener): JsonResponse
    {
        $coach = $actor->coach($request->user());
        $opened = $opener->open($request->user(), $coach, $request->integer('plan_id'));

        return response()->json($opened['order'], $opened['created'] ? 201 : 200);
    }

    public function storeAttempt(StartAttemptRequest $request, Order $order, CommerceActor $actor, PaymentAttemptStarter $starter): JsonResponse
    {
        $coach = $actor->coach($request->user());
        $started = $starter->start($coach, $order, $request->string('idempotency_key')->toString());

        return response()->json($started['attempt'], $started['created'] ? 201 : 200);
    }
}
