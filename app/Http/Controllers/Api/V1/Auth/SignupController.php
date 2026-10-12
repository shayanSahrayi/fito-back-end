<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\SignupCompleter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteSignupRequest;
use Illuminate\Http\JsonResponse;

class SignupController extends Controller
{
    public function store(CompleteSignupRequest $request, SignupCompleter $signup): JsonResponse
    {
        $result = $signup->complete(
            $request->string('phone')->toString(),
            $request->string('national_code')->toString(),
            $request->string('display_name')->toString(),
            $request->string('panel')->toString(),
        );

        return response()->json($result, 201);
    }
}
