<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\OfficerDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 25: smoke RMO dashboard Blade + Postgres stats.
 */
class SmokeSlice5OfficerDashboard extends Command
{
    protected $signature = 'rms:smoke-slice5-officer-dashboard';

    protected $description = 'Smoke Laravel Risk Management Officer dashboard Blade page';

    public function handle(OfficerDashboardService $dashboard): int
    {
        $username = 'smoke_rmo_'.bin2hex(random_bytes(3));
        $password = 'SmokeRmo1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke RMO Dashboard',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::RM_OFFICER,
            'role_label' => Roles::label(Roles::RM_OFFICER),
            'department' => 'RMO',
            'position' => 'Risk Management Officer',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$username,
            'reference' => 'RISK-SMOKE-'.strtoupper(substr($username, -6)),
            'title' => 'Smoke officer dashboard ticket',
            'status' => 'assigned',
            'department' => 'Information Technology',
            'category' => 'operational',
            'likelihood' => 3,
            'impact' => 4,
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'ownership' => ['state' => 'pending'],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $dashboard->data();
        if (($payload['stats']['total'] ?? 0) < 1 || count($payload['departments']) < 1) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('officer dashboard service missing stats/departments');

            return self::FAILURE;
        }

        $html = view('officer.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'dashboard',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'departments' => $payload['departments'],
            'matrix' => $payload['matrix'],
            'flash' => null,
        ])->render();

        if (! str_contains($html, 'Dashboard') || ! str_contains($html, 'Risk register')) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('officer dashboard Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('officer dashboard Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
