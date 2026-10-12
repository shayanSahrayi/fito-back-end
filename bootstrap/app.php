<?php

use App\Domain\Catalog\CatalogException;
use App\Domain\Commerce\CommerceException;
use App\Domain\Ai\AiException;
use App\Domain\Body\BodyException;
use App\Domain\Feedback\FeedbackException;
use App\Domain\Identity\IdentityException;
use App\Domain\Nutrition\NutritionException;
use App\Domain\Workout\WorkoutException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('auth-session', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $render = function (IdentityException|CommerceException|CatalogException|WorkoutException|NutritionException|FeedbackException|BodyException|AiException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'code' => $exception->errorCode,
                ], $exception->status);
            }
        };

        $exceptions->render(function (IdentityException $exception, Request $request) use ($render) {
            return $render($exception, $request);
        });
        $exceptions->render(function (CommerceException $exception, Request $request) use ($render) {
            return $render($exception, $request);
        });
        $exceptions->render(function (CatalogException $exception, Request $request) use ($render) {
            return $render($exception, $request);
        });
        $exceptions->render(function (WorkoutException $exception, Request $request) use ($render) {
            return $render($exception, $request);
        });
        $exceptions->render(function (NutritionException $exception, Request $request) use ($render) {
            return $render($exception, $request);
        });
        $exceptions->render(function (FeedbackException $exception, Request $request) use ($render) {
            return $render($exception, $request);
        });
        $exceptions->render(function (BodyException $exception, Request $request) use ($render) {
            return $render($exception, $request);
        });
        $exceptions->render(function (AiException $exception, Request $request) use ($render) {
            return $render($exception, $request);
        });
    })->create();
