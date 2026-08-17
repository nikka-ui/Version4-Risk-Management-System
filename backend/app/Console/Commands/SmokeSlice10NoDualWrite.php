<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ExpressOrgMirrorService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Phase 10 slice 3: smoke Postgres-only path with store.json dual-write off.
 */
class SmokeSlice10NoDualWrite extends Command
{
    protected $signature = 'rms:smoke-slice10-no-dual-write';

    protected $description = 'Smoke dual-write off: audits still land in Postgres; store.json untouched';

    public function handle(ExpressOrgMirrorService $mirror): int
    {
        if ((bool) config('rms.store_json_org_mirror', false)) {
            $this->error('store_json_org_mirror is still on');

            return self::FAILURE;
        }
        if ((bool) config('rms.store_json_ticket_mirror', false)) {
            $this->error('store_json_ticket_mirror is still on');

            return self::FAILURE;
        }
        $this->info('dual-write flags OFF');

        $path = (string) config('rms.store_json_path', '');
        $before = is_file($path) ? File::hash($path) : null;

        $username = 'smoke_ndw_'.bin2hex(random_bytes(3));
        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke No Dual Write',
            'email' => "{$username}@rms.local",
            'password' => 'SmokeNdw10!',
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $mirror->syncDepartment('upsert', [
            'id' => 'dept-smoke-ndw',
            'name' => 'Smoke NDW Dept',
            'code' => 'NDW',
        ], [
            'username' => $username,
            'role' => Roles::ADMIN,
            'roleLabel' => Roles::label(Roles::ADMIN),
            'action' => 'department_created',
            'module' => 'Department Management',
            'description' => 'Slice10 no dual-write smoke',
            'ip' => '127.0.0.1',
        ]);

        $found = AuditLog::query()
            ->where('username', $username)
            ->where('description', 'Slice10 no dual-write smoke')
            ->exists();
        if (! $found) {
            AuditLog::query()->where('username', $username)->delete();
            $user->delete();
            $this->error('audit was not written to Postgres');

            return self::FAILURE;
        }
        $this->info('audit Postgres OK');

        $after = is_file($path) ? File::hash($path) : null;
        if ($before !== $after) {
            AuditLog::query()->where('username', $username)->delete();
            $user->delete();
            $this->error('store.json was modified while dual-write is off');

            return self::FAILURE;
        }
        $this->info('store.json untouched OK');

        AuditLog::query()->where('username', $username)->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
