<?php

namespace App\Http\Controllers;

use App\Services\LoginBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phase 5 slice 4–5: Blade login UI + Express bridge; also establishes Laravel web session.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly LoginBridgeService $bridge,
    ) {}

    public function show(Request $request): View
    {
        $queryErrors = [
            'invalid_username' => 'Invalid username.',
            'invalid_password' => 'Invalid password.',
            'inactive_account' => 'This account is inactive.',
            'auth_unavailable' => 'Authentication service is temporarily unavailable. Try again shortly.',
        ];
        $errorKey = (string) $request->query('error', '');

        return view('auth.login', [
            'error' => $request->session()->get('error')
                ?: ($queryErrors[$errorKey] ?? null),
            'next' => $request->query('next', ''),
            'username' => old('username', ''),
        ]);
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'next' => ['nullable', 'string'],
        ]);

        try {
            $user = $this->bridge->authenticate($credentials['username'], $credentials['password']);
        } catch (ValidationException $e) {
            return redirect()->away('/login')
                ->withInput($request->only('username', 'next'))
                ->with('error', collect($e->errors())->flatten()->first() ?: 'Invalid credentials.');
        }

        // Phase 5 slice 5: Laravel web session for Blade pages (Express still owns rms_session).
        Auth::login($user);
        $request->session()->regenerate();

        $code = $this->bridge->issueCode($user);
        $next = (string) ($credentials['next'] ?? '');
        if ($next !== '' && (! str_starts_with($next, '/') || str_starts_with($next, '//'))) {
            $next = '';
        }

        $target = '/auth/bridge?code='.urlencode($code);
        if ($next !== '') {
            $target .= '&next='.urlencode($next);
        }

        // Relative Location so the browser stays on the edge host:port (Express owns /auth/bridge).
        return redirect()->away($target);
    }

    /**
     * Clear Laravel web session, then hand off to Express /login (Express logout already cleared rms_session).
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away('/login');
    }

    /**
     * One-time exchange used by Express /auth/bridge (no Sanctum token).
     */
    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $payload = $this->bridge->consume($data['code']);
        if (! $payload) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired login bridge code.'],
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'user' => $payload['user'],
        ]);
    }
}
