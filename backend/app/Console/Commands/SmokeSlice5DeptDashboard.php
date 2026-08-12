<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\DeptDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 22: smoke Department Head dashboard Blade + Postgres stats.
 */
class SmokeSlice5DeptDashboard extends Command
{
    protected $signature = 'rms:smoke-slice5-dept-dashboard';

    protected $description = 'Smoke Laravel Department Head dashboard Blade page';

    public function handle(DeptDashboardService $dashboard): int
    {
        $username = 'smoke_dept_'.bin2hex(random_bytes(3));
        $password = 'SmokeDept1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Dept Head',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Information Technology',
            'position' => 'Department Head / Vice President',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$username,
            'reference' => 'RISK-SMOKE-'.strtoupper(substr($username, -6)),
            'title' => 'Smoke dept dashboard ticket',
            'status' => 'assigned',
            'department' => 'Information Technology',
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'category' => 'operational',
            'ownership' => ['state' => 'pending'],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $dashboard->forUser($user);
        if (($payload['stats']['inbox'] ?? 0) < 1 || count($payload['recent']) < 1) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('dept dashboard service missing inbox/recent');

            return self::FAILURE;
        }

        $html = view('dept.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'dashboard',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'recent' => $payload['recent'],
            'flash' => null,
        ])->render();

        if (! str_contains($html, 'Dashboard') || ! str_contains($html, $ticket->reference)) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('dept dashboard Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('dept dashboard Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
