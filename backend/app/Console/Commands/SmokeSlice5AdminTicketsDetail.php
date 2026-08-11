<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\AdminTicketDetailService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 19: smoke System Administrator ticket detail Blade + Postgres fetch.
 */
class SmokeSlice5AdminTicketsDetail extends Command
{
    protected $signature = 'rms:smoke-slice5-admin-tickets-detail';

    protected $description = 'Smoke Laravel admin ticket detail Blade page';

    public function handle(AdminTicketDetailService $service): int
    {
        $username = 'smoke_tixdt_'.bin2hex(random_bytes(3));
        $password = 'SmokeTix1!';
        $ref = 'RISK-SMOKE-DT-'.strtoupper(substr($username, -6));

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Admin Ticket Detail',
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

        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$username,
            'reference' => $ref,
            'title' => 'Smoke admin ticket detail '.$ref,
            'status' => 'submitted',
            'category' => 'operational',
            'department' => 'Administration',
            'submitted_by' => 'reporter1',
            'submitted_by_name' => 'Reporter One',
            'likelihood' => 2,
            'impact' => 3,
            'risk_score' => 6,
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
            'ai' => [
                'riskLevel' => ['id' => 'moderate', 'label' => 'Moderate'],
                'severity' => 3,
            ],
        ]);

        $this->info("created {$username} + {$ref}");
        Auth::login($user);

        $found = $service->findByReference($ref);
        if (! $found) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('detail service missing created ticket');

            return self::FAILURE;
        }

        $html = view('admin.ticket-detail', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'tickets',
            'title' => 'Ticket Details',
            'ticket' => $found,
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'Ticket Details') || ! str_contains($html, $ref)) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('admin ticket detail Blade missing expected content');

            return self::FAILURE;
        }

        $this->info('admin ticket detail Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}

