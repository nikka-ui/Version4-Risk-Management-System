<?php

namespace App\Http\Middleware;

use App\Support\Roles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RMO or Compliance Officer — validate/return accomplishments (not approve/close).
 */
class EnsureGovernanceRole
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user
            || ! in_array($user->role, [Roles::RM_OFFICER, Roles::COMPLIANCE_OFFICER], true)
            || ! $user->isActiveAccount()) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
