<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 5 slice 4: one-time codes so Laravel Blade login can mint an Express cookie session.
 */
class LoginBridgeService
{
    private const TTL_SECONDS = 120;

    /**
     * @throws ValidationException
     */
    public function authenticate(string $username, string $password): User
    {
        $username = strtolower(trim($username));
        $user = User::query()
            ->where('username', $username)
            ->where('deleted', false)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid username or password.'],
            ]);
        }

        if (! $user->isActiveAccount()) {
            throw ValidationException::withMessages([
                'username' => ['This account is inactive.'],
            ]);
        }

        return $user;
    }

    public function issueCode(User $user): string
    {
        $code = Str::random(48);
        Cache::put($this->cacheKey($code), [
            'username' => $user->username,
            'user' => $user->toIdentityArray(),
        ], self::TTL_SECONDS);

        return $code;
    }

    /**
     * @return array{username: string, user: array<string, mixed>}|null
     */
    public function consume(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $key = $this->cacheKey($code);
        $payload = Cache::pull($key);

        return is_array($payload) ? $payload : null;
    }

    private function cacheKey(string $code): string
    {
        return 'login_bridge:'.$code;
    }
}
