<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum token endpoints for future Express → Laravel service calls.
 * Does NOT replace Express browser login / cookie sessions.
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

        $username = strtolower(trim($credentials['username']));
        $user = User::query()
            ->where('username', $username)
            ->where('deleted', false)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isActiveAccount()) {
            throw ValidationException::withMessages([
                'username' => ['This account is inactive.'],
            ]);
        }

        $deviceName = $credentials['device_name'] ?? 'api-token';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
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
}
