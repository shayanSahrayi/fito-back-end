<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\PasswordChanger;
use App\Domain\Identity\PasswordLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordLoginRequest;
use App\Http\Requests\Me\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PasswordController extends Controller
{
    public function store(PasswordLoginRequest $request, PasswordLogin $login): JsonResponse
    {
        $user = $login->login(
            $request->string('phone')->toString(),
            $request->string('password')->toString(),
        );

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

    public function update(ChangePasswordRequest $request, PasswordChanger $changer): Response
    {
        $changer->change(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return response()->noContent();
    }
}
