<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\AdminTicketService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 18: smoke System Administrator tickets Blade + Postgres list.
 */
class SmokeSlice5AdminTickets extends Command
{
    protected $signature = 'rms:smoke-slice5-admin-tickets';

    protected $description = 'Smoke Laravel admin ticket management Blade page';

    public function handle(AdminTicketService $tickets): int
    {
        $username = 'smoke_tix_'.bin2hex(random_bytes(3));
        $password = 'SmokeTix1!';
        $ref = 'RISK-SMOKE-'.strtoupper(substr($username, -6));

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Admin Tickets',
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
            'title' => 'Smoke admin ticket '.$ref,
            'status' => 'assigned',
            'category' => 'operational',
            'department' => 'Administration',
            'submitted_by' => 'reporter1',
            'submitted_by_name' => 'Reporter One',
            'likelihood' => 3,
            'impact' => 4,
            'risk_score' => 12,
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
            'ai' => [
                'riskLevel' => ['id' => 'high', 'label' => 'High'],
                'severity' => 4,
            ],
        ]);

        $this->info("created {$username} + {$ref}");

        Auth::login($user);
        $payload = $tickets->list(null, null, null, null, false);
        $found = collect($payload['tickets'])->contains(fn ($t) => ($t['reference'] ?? '') === $ref);
        if (! $found) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('ticket service missing created ticket');

            return self::FAILURE;
        }

        $html = view('admin.tickets', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'tickets',
            'title' => 'Ticket Management',
            'tickets' => $payload['tickets'],
            'departments' => $payload['departments'],
            'statusOptions' => $payload['statusOptions'],
            'filters' => $payload['filters'],
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'Ticket Management') || ! str_contains($html, $ref)) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('admin tickets Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('admin tickets Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
