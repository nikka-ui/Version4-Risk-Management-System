<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\PresidentDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 29: smoke President dashboard Blade.
 */
class SmokeSlice5PresidentDashboard extends Command
{
    protected $signature = 'rms:smoke-slice5-president-dashboard';

    protected $description = 'Smoke Laravel President dashboard Blade page';

    public function handle(PresidentDashboardService $dashboard): int
    {
        $username = 'smoke_pres_'.bin2hex(random_bytes(3));
        $password = 'SmokePres1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke President Dashboard',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
            'department' => 'IT',
            'position' => 'President',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$username,
            'reference' => 'RISK-SMOKE-'.strtoupper(substr($username, -6)),
            'title' => 'Smoke president dashboard ticket',
            'description' => 'President dashboard smoke detail',
            'status' => 'assigned',
            'department' => 'IT',
            'category' => 'operational',
            'likelihood' => 4,
            'impact' => 5,
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'ownership' => ['state' => 'pending'],
            'ai' => ['severity' => 5],
            'action_plan' => ['summary' => 'Sample action plan summary', 'description' => 'Sample description'],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);

        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $dashboard->data();

        $html = view('president.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'overview',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'org' => $payload['org'],
            'matrix' => $payload['matrix'],
            'flash' => null,
        ])->render();

        if (! str_contains($html, 'President dashboard') || ! str_contains($html, 'Pending decisions') || ! str_contains($html, 'Organization risk matrix')) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('president dashboard Blade missing expected content');
            return self::FAILURE;
        }

        $this->info('president dashboard Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}

