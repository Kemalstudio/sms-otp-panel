<?php

use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\DeviceTokenAuth;
use App\Http\Middleware\IdempotencyKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.key' => ApiKeyAuth::class,
            'device.token' => DeviceTokenAuth::class,
            'idempotency' => IdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Gateway clients are phones and server integrations, not browsers.
        // Without this a failed validation on /api/* answers with a redirect
        // to the marketing page whenever the caller omits
        // `Accept: application/json`, which curl and most SDKs do.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
