<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\ExecutiveDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 28: smoke Executive Committee dashboard Blade.
 */
class SmokeSlice5ExecutiveDashboard extends Command
{
    protected $signature = 'rms:smoke-slice5-executive-dashboard';

    protected $description = 'Smoke Laravel Executive Committee dashboard Blade page';

    public function handle(ExecutiveDashboardService $dashboard): int
    {
        $username = 'smoke_exec_'.bin2hex(random_bytes(3));
        $password = 'SmokeExec1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Executive Dashboard',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::EXECUTIVE,
            'role_label' => Roles::label(Roles::EXECUTIVE),
            'department' => 'IT',
            'position' => 'Executive Committee',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$username,
            'reference' => 'RISK-SMOKE-'.strtoupper(substr($username, -6)),
            'title' => 'Smoke executive dashboard ticket',
            'description' => 'Executive dashboard smoke detail',
            'status' => 'assigned',
            'department' => 'IT',
            'category' => 'operational',
            'likelihood' => 3,
            'impact' => 4,
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'ownership' => ['state' => 'pending'],
            'ai' => ['severity' => 5],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $dashboard->data();

        $html = view('executive.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'overview',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'departments' => $payload['departments'],
            'matrix' => $payload['matrix'],
            'flash' => null,
        ])->render();

        if (! str_contains($html, 'Dashboard') || ! str_contains($html, 'Organization risk matrix')) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('executive dashboard Blade missing expected content');

            return self::FAILURE;
        }

        $this->info('executive dashboard Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}

