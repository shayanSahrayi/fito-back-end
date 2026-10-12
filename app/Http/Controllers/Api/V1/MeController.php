<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\CurrentAccount;
use App\Domain\Identity\MissingProfileAdder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\AddProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request, CurrentAccount $account): JsonResponse
    {
        return response()->json($account->describe($request->user()));
    }

    public function storeProfile(AddProfileRequest $request, MissingProfileAdder $adder): JsonResponse
    {
        $created = $adder->add(
            $request->user(),
            $request->string('panel')->toString(),
            $request->string('display_name')->toString(),
        );

        return response()->json($created, 201);
    }
}
