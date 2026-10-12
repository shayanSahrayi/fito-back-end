<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Domain\Commerce\PaymentConfirmer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\ConfirmPaymentRequest;
use Illuminate\Http\JsonResponse;

class TestCallbackController extends Controller
{
    public function __invoke(ConfirmPaymentRequest $request, PaymentConfirmer $confirmer): JsonResponse
    {
        return response()->json($confirmer->confirm(
            $request->integer('attempt_id'),
            $request->integer('amount_irr'),
            $request->string('gateway_reference')->toString(),
            'test',
            [
                'amount_irr' => $request->integer('amount_irr'),
                'gateway_reference' => $request->string('gateway_reference')->toString(),
            ],
        ));
    }
}
