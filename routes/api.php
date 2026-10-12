<?php

use App\Domain\Commerce\TestGateway;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PlanController;
use App\Http\Controllers\Api\V1\Athlete\InvitationController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\SignupController;
use App\Http\Controllers\Api\V1\Coach\AccessController;
use App\Http\Controllers\Api\V1\Coach\AthleteController;
use App\Http\Controllers\Api\V1\Coach\OrderController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Payments\TestCallbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->middleware('auth-session')->group(function () {
    Route::post('otp', [OtpController::class, 'store']);
    Route::post('otp/verify', [OtpController::class, 'verify']);
    Route::post('signup', [SignupController::class, 'store']);
    Route::post('password', [PasswordController::class, 'store']);
    Route::post('logout', LogoutController::class)->middleware('auth:sanctum');
});

Route::prefix('v1/me')->middleware(['auth-session', 'auth:sanctum'])->group(function () {
    Route::get('/', [MeController::class, 'show']);
    Route::post('profiles', [MeController::class, 'storeProfile']);
    Route::post('password', [PasswordController::class, 'update']);
});

Route::prefix('v1/admin')->middleware(['auth-session', 'auth:sanctum'])->group(function () {
    Route::get('plans', [PlanController::class, 'index']);
    Route::post('plans', [PlanController::class, 'store']);
    Route::get('plans/{plan}', [PlanController::class, 'show']);
    Route::patch('plans/{plan}', [PlanController::class, 'update']);
    Route::delete('plans/{plan}', [PlanController::class, 'destroy']);
    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::post('orders/{order}/manual-payments', [AdminOrderController::class, 'storePayment']);
});

Route::prefix('v1/coach')->middleware(['auth-session', 'auth:sanctum'])->group(function () {
    Route::post('orders', [OrderController::class, 'store']);
    Route::post('orders/{order}/attempts', [OrderController::class, 'storeAttempt']);
    Route::get('access', [AccessController::class, 'show']);
    Route::post('athletes', [AthleteController::class, 'store']);
    Route::get('relationships', [AthleteController::class, 'index']);
    Route::post('invitations/{invitation}/revoke', [AthleteController::class, 'revoke']);
    Route::post('relationships/{relationship}/end', [AthleteController::class, 'end']);
});

Route::prefix('v1/athlete')->middleware(['auth-session', 'auth:sanctum'])->group(function () {
    Route::get('relationships', [InvitationController::class, 'relationships']);
    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations/{invitation}/decline', [InvitationController::class, 'decline']);
});

if (TestGateway::enabled((string) app()->environment())) {
    Route::post('v1/payments/test/callback', TestCallbackController::class);
}
