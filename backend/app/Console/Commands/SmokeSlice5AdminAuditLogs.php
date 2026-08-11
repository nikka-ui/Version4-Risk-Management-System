<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminAuditLogsService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 20: smoke System Administrator audit logs Blade + store.json read.
 */
class SmokeSlice5AdminAuditLogs extends Command
{
    protected $signature = 'rms:smoke-slice5-admin-audit-logs';

    protected $description = 'Smoke Laravel admin audit logs Blade page';

    public function handle(AdminAuditLogsService $service): int
    {
        $username = 'smoke_adlog_'.bin2hex(random_bytes(3));
        $password = 'SmokeAd1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Admin Audit Logs',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($user);

        $payload = $service->list(null, null, null, null, null);
        $logs = $payload['logs'] ?? [];
        if (! is_array($logs) || count($logs) < 1) {
            Auth::logout();
            $user->delete();
            $this->error('audit logs service returned 0 logs');

            return self::FAILURE;
        }

        $html = view('admin.audit-logs', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'audit',
            'title' => 'Audit Logs',
            'logs' => $payload['logs'],
            'options' => $payload['options'],
            'filters' => $payload['filters'],
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'Audit Logs') || ! str_contains($html, 'Export CSV')) {
            Auth::logout();
            $user->delete();
            $this->error('admin audit logs Blade missing expected content');

            return self::FAILURE;
        }

        $this->info('admin audit logs Blade OK');

        Auth::logout();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}

