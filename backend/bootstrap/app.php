<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Edge nginx terminates TLS / sets X-Forwarded-*; trust all private proxies.
        $middleware->trustProxies(at: '*');

        // API-only auth: never redirect guests to a web login route (Express owns /login).
        $middleware->redirectGuestsTo(fn () => null);

        // Optional future Express→Laravel service calls (not used by browser login).
        $middleware->alias([
            'rms.service_token' => \App\Http\Middleware\VerifyInternalServiceToken::class,
            'rms.admin' => \App\Http\Middleware\EnsureAdminRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always JSON for /v1 so missing Accept headers still get 401, not HTML.
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('v1/*') || $request->is('v1') || $request->expectsJson();
        });
    })->create();
