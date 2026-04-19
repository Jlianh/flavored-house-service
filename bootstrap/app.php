<?php

use App\Http\Middleware\RequireAuth;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias custom middlewares
        $middleware->alias([
            'auth.jwt' => RequireAuth::class,
            'role'     => RequireRole::class,
        ]);

        // CORS — allow Angular front-end origins
        $middleware->append(\App\Http\Middleware\Cors::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON for every unhandled exception (API-only app)
        $exceptions->render(function (\Throwable $e, Request $request) {
            return response()->json([
                'error' => $e->getMessage() ?: 'Internal server error',
            ], method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500);
        });
    })
    ->create();
