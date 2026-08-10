<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\SupervisorDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 12: smoke Ticket Reporter actions Blade page.
 */
class SmokeSlice5ReporterActions extends Command
{
    protected $signature = 'rms:smoke-slice5-reporter-actions';

    protected $description = 'Smoke Laravel Ticket Reporter actions Blade page';

    public function handle(SupervisorDashboardService $dashboard): int
    {
        $username = 'smoke_act_'.bin2hex(random_bytes(3));
        $password = 'SmokeAct1!';
        $ref = 'RISK-SMOKE-A-'.strtoupper(substr($username, -5));

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Actions Reporter',
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
            'external_id' => 'ext-act-'.$username,
            'reference' => $ref,
            'title' => 'Smoke action ticket',
            'status' => 'in_mitigation',
            'category' => 'operational',
            'likelihood' => 3,
            'impact' => 4,
            'risk_score' => 12,
            'submitted_by' => $username,
            'submitted_by_name' => $user->name,
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
            'submitted_at' => now(),
        ]);
        $this->info("created {$username} + ticket {$ref}");

        Auth::login($user);
        $rows = $dashboard->actionsForUsername($username);
        if (! collect($rows)->contains(fn ($r) => ($r['reference'] ?? null) === $ref)) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('actions list missing smoke ticket');

            return self::FAILURE;
        }

        $html = view('supervisor.actions', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'actions',
            'title' => 'Action required',
            'tickets' => $rows,
            'flash' => null,
        ])->render();

        if (! str_contains($html, $ref) || ! str_contains($html, 'In mitigation')) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('actions Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('supervisor actions Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleaned up');
        $this->line('Flag USE_LARAVEL_REPORTER_ACTIONS_UI: Express /supervisor/actions → Blade');

        return self::SUCCESS;
    }
}
