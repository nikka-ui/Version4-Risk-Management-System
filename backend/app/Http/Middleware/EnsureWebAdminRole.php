<?php

namespace App\Http\Middleware;

use App\Support\Roles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web (Blade) admin gate — HTML-friendly unlike API rms.admin JSON 403.
 */
class EnsureWebAdminRole
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== Roles::ADMIN || ! $user->isActiveAccount()) {
            return redirect()->away('/login');
        }

        return $next($request);
    }
}
