<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Coaching\InvitationAccepter;
use App\Domain\Identity\OtpIssuer;
use App\Domain\Identity\OtpVerifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OtpController extends Controller
{
    public function store(RequestOtpRequest $request, OtpIssuer $issuer): JsonResponse
    {
        $expiresIn = $issuer->issue(
            $request->string('phone')->toString(),
            $request->string('purpose')->toString(),
            $request->filled('invitation_id') ? $request->integer('invitation_id') : null,
        );

        return response()->json([
            'expires_in' => $expiresIn,
        ]);
    }

    public function verify(VerifyOtpRequest $request, OtpVerifier $verifier, InvitationAccepter $accepter): JsonResponse
    {
        $phone = $request->string('phone')->toString();
        $code = $request->string('code')->toString();
        $purpose = $request->string('purpose')->toString();

        if ($purpose === 'signup') {
            $verifier->verifySignup($phone, $code);

            return response()->json([
                'phone_verified' => true,
            ]);
        }

        if ($purpose === 'invitation') {
            return response()->json($accepter->accept($phone, $code, $request->integer('invitation_id')));
        }

        $user = $verifier->verifyLogin($phone, $code);

        if ($request->string('client')->toString() === 'web') {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return response()->json([
                'user_id' => $user->id,
            ]);
        }

        return response()->json([
            'user_id' => $user->id,
            'token' => $user->createToken('mobile')->plainTextToken,
        ]);
    }
}
