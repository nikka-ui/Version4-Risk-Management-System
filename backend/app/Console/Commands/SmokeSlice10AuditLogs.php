<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AdminAuditLogsService;
use App\Services\AuditLogService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 10 slice 1: smoke Postgres audit write + Blade list/export path.
 */
class SmokeSlice10AuditLogs extends Command
{
    protected $signature = 'rms:smoke-slice10-audit-logs';

    protected $description = 'Smoke Postgres audit_logs write/read for admin UI';

    public function handle(AuditLogService $audits, AdminAuditLogsService $service): int
    {
        $username = 'smoke_a10_'.bin2hex(random_bytes(3));
        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Audit 10',
            'email' => "{$username}@rms.local",
            'password' => 'SmokeAd10!',
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $audits->record([
            'username' => $username,
            'role' => Roles::ADMIN,
            'roleLabel' => Roles::label(Roles::ADMIN),
            'action' => 'department_created',
            'module' => 'Department Management',
            'description' => 'Slice10 smoke audit',
            'ip' => '127.0.0.1',
        ]);
        $this->info('audit write OK');

        Auth::login($user);
        $payload = $service->list(null, null, $username, null, null);
        $found = collect($payload['logs'] ?? [])->first(
            fn ($row) => is_array($row) && ($row['description'] ?? '') === 'Slice10 smoke audit'
        );
        if (! is_array($found)) {
            Auth::logout();
            AuditLog::query()->where('username', $username)->delete();
            $user->delete();
            $this->error('list did not return smoke audit row');

            return self::FAILURE;
        }
        $this->info('audit list OK');

        $csv = $service->toCsv([$found]);
        if (! str_contains($csv, 'Slice10 smoke audit')) {
            Auth::logout();
            AuditLog::query()->where('username', $username)->delete();
            $user->delete();
            $this->error('csv missing description');

            return self::FAILURE;
        }
        $this->info('audit csv OK');

        Auth::logout();
        AuditLog::query()->where('username', $username)->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
