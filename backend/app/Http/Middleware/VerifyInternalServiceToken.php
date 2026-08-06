<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional internal service-token gate for future Express → Laravel calls.
 * Not used by browser login. Configure RMS_INTERNAL_SERVICE_TOKEN and send
 * header X-RMS-Service-Token. Leave empty to keep this path disabled.
 */
class VerifyInternalServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('rms.internal_service_token', '');
        if ($expected === '') {
            return response()->json([
                'message' => 'Internal service token is not configured.',
            ], 503);
        }

        $provided = (string) $request->header('X-RMS-Service-Token', '');
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Invalid or missing service token.',
            ], 401);
        }

        return $next($request);
    }
}
