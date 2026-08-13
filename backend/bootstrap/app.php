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

        // Browser guests → Blade login; API stays JSON (null redirect + shouldRenderJsonWhen).
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('v1') || $request->is('v1/*') || $request->expectsJson()) {
                return null;
            }

            return config('rms.edge_ui', true) ? '/login' : '/laravel/login';
        });

        // Optional future Express→Laravel service calls (not used by browser login).
        $middleware->alias([
            'rms.service_token' => \App\Http\Middleware\VerifyInternalServiceToken::class,
            'rms.admin' => \App\Http\Middleware\EnsureAdminRole::class,
            'rms.web_admin' => \App\Http\Middleware\EnsureWebAdminRole::class,
            'rms.web_supervisor' => \App\Http\Middleware\EnsureWebSupervisorRole::class,
            'rms.web_dept_head' => \App\Http\Middleware\EnsureWebDeptHeadRole::class,
            'rms.web_officer' => \App\Http\Middleware\EnsureWebRmOfficerRole::class,
            'rms.web_executive' => \App\Http\Middleware\EnsureWebExecutiveRole::class,
            'rms.web_president' => \App\Http\Middleware\EnsureWebPresidentRole::class,
            'rms.dept_head' => \App\Http\Middleware\EnsureDeptHeadRole::class,
            'rms.president' => \App\Http\Middleware\EnsurePresidentRole::class,
            'rms.officer' => \App\Http\Middleware\EnsureRmOfficerRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always JSON for /v1 so missing Accept headers still get 401, not HTML.
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('v1/*') || $request->is('v1') || $request->expectsJson();
        });
    })->create();
