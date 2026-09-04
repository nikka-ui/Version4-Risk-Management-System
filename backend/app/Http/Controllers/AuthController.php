<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum token + credential verify with rate limits and audit logging.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogs,
    ) {}

    public function token(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ]);

        $user = $this->authenticateUser($request, $credentials['username'], $credentials['password']);

        $deviceName = $credentials['device_name'] ?? 'api-token';
        $expiration = now()->addMinutes(max(60, (int) config('rms.sanctum_expiration_minutes', 720)));
        $token = $user->createToken($deviceName, ['*'], $expiration)->plainTextToken;

        $this->auditAuth($request, $user, 'login_success', 'API token issued');

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiration->toIso8601String(),
            'user' => $user->toIdentityArray(),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->authenticateUser($request, $credentials['username'], $credentials['password']);
        $this->auditAuth($request, $user, 'login_success', 'Credential verify succeeded');

        return response()->json([
            'status' => 'ok',
            'user' => $user->toIdentityArray(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $request->user()?->currentAccessToken()?->delete();
        if ($user) {
            $this->auditAuth($request, $user, 'logout', 'API token revoked');
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * @throws ValidationException
     */
    private function authenticateUser(Request $request, string $username, string $password): User
    {
        $username = strtolower(trim($username));
        $key = 'auth-token:'.sha1($request->ip().'|'.$username);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->auditFailed($request, $username, 'Rate limited login attempt');
            throw ValidationException::withMessages([
                'username' => ["Too many login attempts. Try again in {$seconds} seconds."],
            ]);
        }

        $user = User::query()
            ->where('username', $username)
            ->where('deleted', false)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 60);
            $this->auditFailed($request, $username, 'Invalid credentials');
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isActiveAccount()) {
            RateLimiter::hit($key, 60);
            $this->auditFailed($request, $username, 'Inactive account');
            throw ValidationException::withMessages([
                'username' => ['This account is inactive.'],
            ]);
        }

        RateLimiter::clear($key);

        return $user;
    }

    private function auditAuth(Request $request, User $user, string $action, string $description): void
    {
        try {
            $this->auditLogs->record([
                'username' => $user->username,
                'role' => $user->role,
                'roleLabel' => Roles::label($user->role),
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

    private function auditFailed(Request $request, string $username, string $description): void
    {
        try {
            $this->auditLogs->record([
                'username' => $username,
                'role' => null,
                'action' => 'login_failed',
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
