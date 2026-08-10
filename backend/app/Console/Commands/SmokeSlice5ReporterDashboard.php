<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\SupervisorDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 7: smoke Ticket Reporter dashboard Blade + Postgres stats.
 */
class SmokeSlice5ReporterDashboard extends Command
{
    protected $signature = 'rms:smoke-slice5-reporter-dashboard';

    protected $description = 'Smoke Laravel Ticket Reporter dashboard Blade page';

    public function handle(SupervisorDashboardService $dashboard): int
    {
        $username = 'smoke_dash_'.bin2hex(random_bytes(3));
        $password = 'SmokeDash1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Dashboard Reporter',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Operations',
            'position' => 'Risk Reporter',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$username,
            'reference' => 'RISK-SMOKE-'.strtoupper(substr($username, -6)),
            'title' => 'Smoke dashboard ticket',
            'status' => 'draft',
            'submitted_by' => $username,
            'submitted_by_name' => $user->name,
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $dashboard->forUsername($username);
        if (($payload['stats']['drafts'] ?? 0) < 1 || count($payload['recent']) < 1) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('dashboard service missing draft/recent');

            return self::FAILURE;
        }

        $html = view('supervisor.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'overview',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'recent' => $payload['recent'],
        ])->render();

        if (! str_contains($html, 'Dashboard') || ! str_contains($html, $ticket->reference)) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('dashboard Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('supervisor dashboard Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleaned up');
        $this->line('Flag USE_LARAVEL_REPORTER_DASHBOARD_UI: Express /supervisor → /laravel/supervisor');

        return self::SUCCESS;
    }
}
