<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LoginBridgeService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        $success = $request->session()->get('status');
        if (! $success && (string) $request->query('flash') === 'password_reset') {
            $success = 'Password reset successfully. You can sign in with your new password.';
        }

        return view('auth.login', [
            'error' => $request->session()->get('error')
                ?: ($queryErrors[$errorKey] ?? null),
            'success' => $success,
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

        // Relative Location so the browser stays on the edge host:port (Laravel owns /auth/bridge).
        return redirect()->away($target);
    }

    /**
     * Phase 9 slice 2: consume the one-time login code and send the browser to the role console.
     * Laravel web session is already set in store(); this also logs in if the code is presented alone.
     */
    public function bridge(Request $request): RedirectResponse
    {
        $code = trim((string) $request->query('code', ''));
        $next = (string) $request->query('next', '');
        if ($next !== '' && (! str_starts_with($next, '/') || str_starts_with($next, '//'))) {
            $next = '';
        }

        $user = $request->user();
        if ($code !== '') {
            $payload = $this->bridge->consume($code);
            if (is_array($payload)) {
                $fromCode = User::query()
                    ->where('username', $payload['username'] ?? '')
                    ->where('deleted', false)
                    ->first();
                if ($fromCode) {
                    Auth::login($fromCode);
                    if ($request->hasSession()) {
                        $request->session()->regenerate();
                    }
                    $user = $fromCode;
                }
            } elseif (! $user) {
                return redirect()->away('/login?error=auth_unavailable');
            }
        }

        if (! $user) {
            return redirect()->away('/login?error=auth_unavailable');
        }

        $dest = $next !== '' ? $next : Roles::consolePath($user->role);

        return redirect()->away($dest);
    }

    /**
     * Phase 9 slice 3: clear Laravel web session and send the browser to Blade /login.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

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
