<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function __construct(
        private readonly PasswordResetOtpService $otps,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function showRequest(Request $request): View
    {
        return view('auth.forgot-password', [
            'error' => $request->session()->get('error'),
            'username' => old('username', ''),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:64'],
        ]);
        $username = strtolower(trim($data['username']));

        try {
            $this->otps->requestCode($username, (string) $request->ip());
        } catch (ValidationException $e) {
            return redirect()->away('/forgot-password')
                ->withInput($request->only('username'))
                ->with('error', collect($e->errors())->flatten()->first());
        }

        $request->session()->put('password_reset_username', $username);

        return redirect()->away('/forgot-password/reset');
    }

    public function showReset(Request $request): View|RedirectResponse
    {
        $username = (string) $request->session()->get('password_reset_username', '');
        if ($username === '') {
            return redirect()->away('/forgot-password');
        }

        return view('auth.reset-password', [
            'error' => $request->session()->get('error'),
            'username' => $username,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $username = (string) $request->session()->get('password_reset_username', '');
        if ($username === '') {
            return redirect()->away('/forgot-password');
        }

        $data = $request->validate([
            'otp' => ['required', 'string', 'max:12'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        try {
            $user = $this->otps->resetPassword($username, $data['otp'], $data['password']);
        } catch (ValidationException $e) {
            return redirect()->away('/forgot-password/reset')
                ->with('error', collect($e->errors())->flatten()->first());
        }

        $request->session()->forget('password_reset_username');
        $this->auditLogs->record([
            'username' => $user->username,
            'role' => $user->role,
            'roleLabel' => $user->role_label,
            'action' => 'password_reset',
            'module' => 'Security',
            'description' => 'Password reset via emailed OTP',
            'ip' => $request->ip(),
            'targetUser' => $user->username,
        ]);

        return redirect()->away('/login?flash=password_reset');
    }
}
