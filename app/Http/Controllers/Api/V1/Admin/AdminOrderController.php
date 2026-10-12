<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Commerce\AdminOrderList;
use App\Domain\Commerce\CommerceActor;
use App\Domain\Commerce\ManualPaymentRecorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\ManualPaymentRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request, CommerceActor $actor, AdminOrderList $orders): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($orders->all());
    }

    public function storePayment(ManualPaymentRequest $request, Order $order, CommerceActor $actor, ManualPaymentRecorder $recorder): JsonResponse
    {
        $admin = $actor->admin($request->user());
        $payment = $recorder->record(
            $admin,
            $order,
            $request->integer('amount_irr'),
            $request->string('gateway_reference')->toString(),
        );

        return response()->json($payment);
    }
}
