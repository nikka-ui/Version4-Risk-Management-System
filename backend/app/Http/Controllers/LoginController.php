<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\LoginBridgeService;
use App\Services\LoginOtpService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phase 5 slice 4–5: Blade login UI + Express bridge; also establishes Laravel web session.
 * Default landing sign-in is passwordless (username → emailed OTP).
 * Admin path (`?as=admin`) uses username + password via LoginBridgeService.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly LoginBridgeService $bridge,
        private readonly LoginOtpService $loginOtps,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function show(Request $request): View
    {
        $queryErrors = [
            'invalid_username' => 'Invalid username.',
            'invalid_password' => 'Invalid password.',
            'invalid_otp' => 'Invalid or expired code. Request a new one.',
            'inactive_account' => 'This account is inactive.',
            'auth_unavailable' => 'Authentication service is temporarily unavailable. Try again shortly.',
        ];
        $errorKey = (string) $request->query('error', '');

        $success = $request->session()->get('status');
        if (! $success && (string) $request->query('flash') === 'password_reset') {
            $success = 'Password reset successfully. You can sign in with a one-time code emailed to your account.';
        }

        $as = strtolower(trim((string) $request->query('as', '')));
        $username = old('username');
        if ($username === null || $username === '') {
            $username = $as === 'admin' ? 'admin' : '';
        }

        return view('auth.login', [
            'error' => $request->session()->get('error')
                ?: ($queryErrors[$errorKey] ?? null),
            'success' => $success,
            'next' => $request->query('next', ''),
            'username' => $username,
            'as' => $as,
        ]);
    }

    /**
     * Default: accept username and email a login OTP.
     * Password mode (`mode=password`, used by `/login?as=admin`): authenticate and issue bridge code.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($this->isPasswordLogin($request)) {
            return $this->storePassword($request);
        }

        return $this->storeOtpRequest($request);
    }

    private function isPasswordLogin(Request $request): bool
    {
        return $request->input('mode') === 'password'
            || strtolower(trim((string) $request->input('as', ''))) === 'admin';
    }

    /**
     * Admin / password path: username + password → session + bridge code (no OTP).
     */
    private function storePassword(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
            'next' => ['nullable', 'string'],
            'mode' => ['nullable', 'string'],
            'as' => ['nullable', 'string'],
        ]);

        $username = strtolower(trim((string) $credentials['username']));
        $loginUrl = '/login?as=admin';
        $key = 'web-login:'.sha1($request->ip().'|'.$username);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->auditLogin($request, $username, null, 'login_failed', 'Rate limited web login');

            return redirect()->away($loginUrl)
                ->withInput($request->only('username', 'next'))
                ->with('error', "Too many login attempts. Try again in {$seconds} seconds.");
        }

        try {
            $user = $this->bridge->authenticate($credentials['username'], $credentials['password']);
        } catch (ValidationException $e) {
            RateLimiter::hit($key, 60);
            $this->auditLogin($request, $username, null, 'login_failed', 'Invalid web login credentials');

            return redirect()->away($loginUrl)
                ->withInput($request->only('username', 'next'))
                ->with('error', collect($e->errors())->flatten()->first() ?: 'Invalid credentials.');
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $this->auditLogin($request, $user->username, $user->role, 'login_success', 'Web session login');

        $code = $this->bridge->issueCode($user);
        $next = (string) ($credentials['next'] ?? '');
        if ($next !== '' && (! str_starts_with($next, '/') || str_starts_with($next, '//'))) {
            $next = '';
        }

        $target = '/auth/bridge?code='.urlencode($code);
        if ($next !== '') {
            $target .= '&next='.urlencode($next);
        }

        return redirect()->away($target);
    }

    /**
     * Step 1: accept username and email a login OTP (does not reveal whether the user exists).
     */
    private function storeOtpRequest(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'next' => ['nullable', 'string'],
        ]);

        $username = strtolower(trim((string) $credentials['username']));
        $key = 'web-login:'.sha1($request->ip().'|'.$username);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->auditLogin($request, $username, null, 'login_failed', 'Rate limited web login OTP request');

            return redirect()->away('/login')
                ->withInput($request->only('username', 'next'))
                ->with('error', "Too many login attempts. Try again in {$seconds} seconds.");
        }

        try {
            $this->loginOtps->requestCode($username, (string) $request->ip());
        } catch (ValidationException $e) {
            RateLimiter::hit($key, 60);
            $this->auditLogin($request, $username, null, 'login_failed', 'Rate limited login OTP service');

            return redirect()->away('/login')
                ->withInput($request->only('username', 'next'))
                ->with('error', collect($e->errors())->flatten()->first() ?: 'Unable to send sign-in code.');
        }

        RateLimiter::clear($key);
        $request->session()->put('login_otp_username', $username);
        $next = (string) ($credentials['next'] ?? '');
        if ($next !== '' && (! str_starts_with($next, '/') || str_starts_with($next, '//'))) {
            $next = '';
        }
        if ($next !== '') {
            $request->session()->put('login_otp_next', $next);
        } else {
            $request->session()->forget('login_otp_next');
        }

        $this->auditLogin($request, $username, null, 'login_otp_sent', 'Web login OTP requested');

        return redirect()->away('/login/otp');
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        $username = (string) $request->session()->get('login_otp_username', '');
        if ($username === '') {
            return redirect()->away('/login');
        }

        return view('auth.login-otp', [
            'error' => $request->session()->get('error'),
            'username' => $username,
            'maskedEmail' => $this->loginOtps->maskedEmailFor($username),
            'next' => (string) $request->session()->get('login_otp_next', ''),
        ]);
    }

    /**
     * Step 2: verify OTP, establish session, issue bridge code.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $username = (string) $request->session()->get('login_otp_username', '');
        if ($username === '') {
            return redirect()->away('/login');
        }

        $data = $request->validate([
            'otp' => ['required', 'string', 'max:12'],
            'next' => ['nullable', 'string'],
        ]);

        $key = 'web-login-otp:'.sha1($request->ip().'|'.$username);
        if (RateLimiter::tooManyAttempts($key, 8)) {
            $seconds = RateLimiter::availableIn($key);
            $this->auditLogin($request, $username, null, 'login_failed', 'Rate limited web login OTP verify');

            return redirect()->away('/login/otp')
                ->with('error', "Too many attempts. Try again in {$seconds} seconds.");
        }

        try {
            $user = $this->loginOtps->verifyCode($username, $data['otp']);
        } catch (ValidationException $e) {
            RateLimiter::hit($key, 60);
            $this->auditLogin($request, $username, null, 'login_failed', 'Invalid web login OTP');

            return redirect()->away('/login/otp')
                ->with('error', collect($e->errors())->flatten()->first() ?: 'Invalid or expired code.');
        }

        RateLimiter::clear($key);

        $next = (string) ($data['next'] ?? '');
        if ($next === '') {
            $next = (string) $request->session()->get('login_otp_next', '');
        }
        if ($next !== '' && (! str_starts_with($next, '/') || str_starts_with($next, '//'))) {
            $next = '';
        }
        $request->session()->forget(['login_otp_username', 'login_otp_next']);

        Auth::login($user);
        $request->session()->regenerate();
        $this->auditLogin($request, $user->username, $user->role, 'login_success', 'Web session login via OTP');

        $code = $this->bridge->issueCode($user);

        $target = '/auth/bridge?code='.urlencode($code);
        if ($next !== '') {
            $target .= '&next='.urlencode($next);
        }

        return redirect()->away($target);
    }

    /**
     * Resend login OTP for the username held in session.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $username = (string) $request->session()->get('login_otp_username', '');
        if ($username === '') {
            return redirect()->away('/login');
        }

        try {
            $this->loginOtps->requestCode($username, (string) $request->ip());
        } catch (ValidationException $e) {
            return redirect()->away('/login/otp')
                ->with('error', collect($e->errors())->flatten()->first());
        }

        $this->auditLogin($request, $username, null, 'login_otp_sent', 'Web login OTP resent');

        return redirect()->away('/login/otp')
            ->with('status', 'A new code was sent if the account can receive email.');
    }

    /**
     * Phase 9 slice 2: consume the one-time login code and send the browser to the role console.
     * Laravel web session is already set in verifyOtp(); this also logs in if the code is presented alone.
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
        /** @var User|null $user */
        $user = $request->user();
        if ($user) {
            $this->auditLogin($request, $user->username, $user->role, 'logout', 'Web session logout');
        }

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

    private function auditLogin(
        Request $request,
        string $username,
        ?string $role,
        string $action,
        string $description,
    ): void {
        try {
            $this->auditLogs->record([
                'username' => $username,
                'role' => $role,
                'roleLabel' => $role ? Roles::label($role) : null,
                'action' => $action,
                'module' => 'auth',
                'description' => $description,
                'ip' => $request->ip(),
                'device' => substr((string) $request->userAgent(), 0, 250),
            ]);
        } catch (\Throwable) {
            // best-effort
        }
    }
}
