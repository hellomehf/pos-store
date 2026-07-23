<?php

use App\Exceptions\InsufficientStockException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->renderable(function (InsufficientStockException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        });

        $exceptions->renderable(function (ValidationException $e) {
            if (request()->is('api/*')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->renderable(function (\Throwable $e) {
            if (request()->is('api/*')) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'An internal server error occurred.';
                return new JsonResponse([
                    'success' => false,
                    'message' => $message,
                ], $status);
            }
        });

        $exceptions->reportable(function (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        });
    })->create();
