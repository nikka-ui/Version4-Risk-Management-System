<?php

namespace App\Http\Middleware;

use App\Support\Roles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web (Blade) President gate.
 */
class EnsureWebPresidentRole
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== Roles::PRESIDENT || ! $user->isActiveAccount()) {
            return redirect()->away('/laravel/login');
        }

        return $next($request);
    }
}

