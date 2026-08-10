<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\SupervisorDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 8: smoke Ticket Reporter ticket list Blade pages.
 */
class SmokeSlice5ReporterTickets extends Command
{
    protected $signature = 'rms:smoke-slice5-reporter-tickets';

    protected $description = 'Smoke Laravel Ticket Reporter ticket list Blade pages';

    public function handle(SupervisorDashboardService $service): int
    {
        $username = 'smoke_tix_'.bin2hex(random_bytes(3));
        $password = 'SmokeTix1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Tickets Reporter',
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

        $draft = RiskTicket::query()->create([
            'external_id' => 'ext-d-'.$username,
            'reference' => 'RISK-SMOKE-D-'.strtoupper(substr($username, -5)),
            'title' => 'Smoke draft ticket',
            'status' => 'draft',
            'category' => 'operational',
            'submitted_by' => $username,
            'submitted_by_name' => $user->name,
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $submitted = RiskTicket::query()->create([
            'external_id' => 'ext-s-'.$username,
            'reference' => 'RISK-SMOKE-S-'.strtoupper(substr($username, -5)),
            'title' => 'Smoke submitted ticket',
            'status' => 'assigned',
            'category' => 'financial',
            'submitted_by' => $username,
            'submitted_by_name' => $user->name,
            'evidence_count' => 1,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + 2 tickets");

        Auth::login($user);
        $all = $service->listForUsername($username);
        $drafts = $service->listForUsername($username, 'draft');
        if (($all['counts']['all'] ?? 0) < 2 || count($drafts['tickets']) !== 1) {
            Auth::logout();
            $draft->delete();
            $submitted->delete();
            $user->delete();
            $this->error('list service filter counts wrong');

            return self::FAILURE;
        }

        $html = view('supervisor.tickets', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $all['activeNav'],
            'title' => $all['title'],
            'pageDesc' => $all['desc'],
            'filter' => $all['filter'],
            'counts' => $all['counts'],
            'tickets' => $all['tickets'],
            'showDueColumn' => $all['showDueColumn'],
            'error' => null,
        ])->render();

        if (! str_contains($html, $draft->reference) || ! str_contains($html, $submitted->reference)) {
            Auth::logout();
            $draft->delete();
            $submitted->delete();
            $user->delete();
            $this->error('tickets Blade missing references');

            return self::FAILURE;
        }
        $this->info('supervisor tickets Blade OK');

        Auth::logout();
        $draft->delete();
        $submitted->delete();
        $user->delete();
        $this->info('cleaned up');
        $this->line('Flag USE_LARAVEL_REPORTER_TICKETS_UI: Express lists → /laravel/supervisor/tickets|drafts|…');

        return self::SUCCESS;
    }
}
