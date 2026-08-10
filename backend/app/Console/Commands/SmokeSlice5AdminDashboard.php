<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 14: smoke System Administrator dashboard Blade + Postgres stats.
 */
class SmokeSlice5AdminDashboard extends Command
{
    protected $signature = 'rms:smoke-slice5-admin-dashboard';

    protected $description = 'Smoke Laravel admin dashboard Blade page';

    public function handle(AdminDashboardService $dashboard): int
    {
        $username = 'smoke_admin_'.bin2hex(random_bytes(3));
        $password = 'SmokeAdmin1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Admin Dashboard',
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

        $this->info("created {$username}");

        Auth::login($user);
        $payload = $dashboard->data();
        if (($payload['stats']['totalUsers'] ?? 0) < 1) {
            Auth::logout();
            $user->delete();
            $this->error('dashboard service missing expected stats');

            return self::FAILURE;
        }

        $html = view('admin.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'dashboard',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'recentUsers' => $payload['recentUsers'],
            'deletedTickets' => $payload['deletedTickets'],
            'auditLogs' => $payload['auditLogs'],
        ])->render();

        if (! str_contains($html, 'Dashboard') || ! str_contains($html, 'Total Users')) {
            Auth::logout();
            $user->delete();
            $this->error('admin dashboard Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('admin dashboard Blade OK');

        Auth::logout();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
