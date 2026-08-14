<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdminAuditLogsController;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 8 slice 7: smoke System Administrator audit CSV export.
 */
class SmokeSlice8AdminAuditExport extends Command
{
    protected $signature = 'rms:smoke-slice8-admin-audit-export';

    protected $description = 'Smoke Laravel admin audit logs CSV export';

    public function handle(AdminAuditLogsController $controller): int
    {
        $user = User::query()->create([
            'username' => 'smoke_adcsv_'.bin2hex(random_bytes(3)),
            'name' => 'Smoke Admin Audit CSV',
            'email' => 'smoke_adcsv_'.bin2hex(random_bytes(3)).'@rms.local',
            'password' => 'SmokeCsv1!',
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($user);
        try {
            $request = Request::create('/admin/audit-logs/export', 'GET');
            $request->setUserResolver(fn () => Auth::user());
            $response = $controller->export($request);
            $body = (string) $response->getContent();
            $disposition = (string) $response->headers->get('Content-Disposition');
            $type = (string) $response->headers->get('Content-Type');
            if (
                $response->getStatusCode() !== 200
                || ! str_contains($type, 'text/csv')
                || ! str_contains($disposition, 'audit-logs.csv')
                || ! str_starts_with($body, 'Date,User,Role,Action,Module,Description,IP,Device,Browser')
                || substr_count($body, "\n") < 1
            ) {
                $this->error('admin audit CSV export failed');

                return self::FAILURE;
            }
            $this->info('admin audit CSV export OK');
        } finally {
            Auth::logout();
            $user->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
