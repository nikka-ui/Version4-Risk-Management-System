<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum token + Phase 5 slice 2 credential verify for Express browser login bridge.
 * Express still owns the cookie session (rms_session) when USE_LARAVEL_AUTH is on.
 */
class AuthController extends Controller
{
    /**
     * Issue a personal access token using the same username/password as Express store.
     * Passwords in Postgres are bcrypt; store.json remains plaintext for Express.
     */
    public function token(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ]);

        $user = $this->authenticateUser($credentials['username'], $credentials['password']);

        $deviceName = $credentials['device_name'] ?? 'api-token';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->toIdentityArray(),
        ]);
    }

    /**
     * Phase 5 slice 2: verify credentials without minting a Sanctum token.
     * Used by Express POST /login when USE_LARAVEL_AUTH=true.
     */
    public function verify(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->authenticateUser($credentials['username'], $credentials['password']);

        return response()->json([
            'status' => 'ok',
            'user' => $user->toIdentityArray(),
        ]);
    }

    /**
     * Revoke the current bearer token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * @throws ValidationException
     */
    private function authenticateUser(string $username, string $password): User
    {
        $username = strtolower(trim($username));
        $user = User::query()
            ->where('username', $username)
            ->where('deleted', false)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isActiveAccount()) {
            throw ValidationException::withMessages([
                'username' => ['This account is inactive.'],
            ]);
        }

        return $user;
    }
}
