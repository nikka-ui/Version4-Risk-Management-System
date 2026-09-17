<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginOtpService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const FROM_ADDRESS = 'itdepartment.accc@gmail.ph';

    public function requestCode(string $username, string $ip): void
    {
        $this->hitRateLimit('login-otp-ip:'.$ip, 5);
        $username = strtolower(trim($username));
        if ($username === '') {
            return;
        }

        $this->hitRateLimit('login-otp-user:'.$username, 3);

        $user = $this->findActiveUser($username);
        if (! $user || ! filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        cache()->put($this->cacheKey($user->username), [
            'hash' => Hash::make($otp),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        Mail::to($user->email)->send(new LoginOtpMail(
            name: $user->name ?: $user->username,
            otp: $otp,
            minutes: self::TTL_MINUTES,
        ));
    }

    public function verifyCode(string $username, string $otp): User
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

        cache()->forget($this->cacheKey($user->username));

        return $user;
    }

    public function maskedEmailFor(string $username): ?string
    {
        $user = $this->findActiveUser(strtolower(trim($username)));
        if (! $user || ! filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $this->maskEmail((string) $user->email);
    }

    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        [$local, $domain] = $parts;
        $localLen = strlen($local);
        if ($localLen <= 1) {
            $maskedLocal = '*';
        } elseif ($localLen === 2) {
            $maskedLocal = $local[0].'*';
        } else {
            $maskedLocal = $local[0].str_repeat('*', min($localLen - 1, 3));
        }

        return $maskedLocal.'@'.$domain;
    }

    private function findActiveUser(string $username): ?User
    {
        $user = User::query()
            ->where('username', $username)
            ->where('deleted', false)
            ->first();

        if (! $user || ! $user->isActiveAccount()) {
            return null;
        }

        return $user;
    }

    private function cacheKey(string $username): string
    {
        return 'login_otp:'.$username;
    }

    private function hitRateLimit(string $key, int $max): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'username' => ['Too many login code requests. Please wait a few minutes and try again.'],
            ]);
        }

        RateLimiter::hit($key, 15 * 60);
    }
}
