<?php

namespace App\Services;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PasswordResetOtpService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function requestCode(string $username, string $ip): void
    {
        $this->hitRateLimit('forgot-ip:'.$ip, 5);
        $username = strtolower(trim($username));
        if ($username === '') {
            return;
        }

        $this->hitRateLimit('forgot-user:'.$username, 3);

        $user = $this->findActiveUser($username);
        if (! $user || ! filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        cache()->put($this->cacheKey($user->username), [
            'hash' => Hash::make($otp),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        Mail::to($user->email)->send(new PasswordResetOtpMail(
            name: $user->name ?: $user->username,
            otp: $otp,
            minutes: self::TTL_MINUTES,
        ));
    }

    public function resetPassword(string $username, string $otp, string $password): User
    {
        $username = strtolower(trim($username));
        $otp = preg_replace('/\D+/', '', $otp) ?? '';
        $user = $this->findActiveUser($username);
        if (! $user) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired code.'],
            ]);
        }

        $payload = cache()->get($this->cacheKey($user->username));
        if (! is_array($payload) || empty($payload['hash'])) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired code. Request a new one.'],
            ]);
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            cache()->forget($this->cacheKey($user->username));
            throw ValidationException::withMessages([
                'otp' => ['Too many incorrect codes. Request a new one.'],
            ]);
        }

        if ($otp === '' || ! Hash::check($otp, (string) $payload['hash'])) {
            $payload['attempts'] = $attempts + 1;
            cache()->put($this->cacheKey($user->username), $payload, now()->addMinutes(self::TTL_MINUTES));
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired code.'],
            ]);
        }

        $user->password = $password;
        $user->save();
        cache()->forget($this->cacheKey($user->username));

        return $user->fresh();
    }

    private function findActiveUser(string $username): ?User
    {
        return User::query()
            ->where('username', $username)
            ->where('deleted', false)
            ->first();
    }

    private function cacheKey(string $username): string
    {
        return 'password_reset_otp:'.$username;
    }

    private function hitRateLimit(string $key, int $max): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'username' => ['Too many reset requests. Please wait a few minutes and try again.'],
            ]);
        }

        RateLimiter::hit($key, 15 * 60);
    }
}
