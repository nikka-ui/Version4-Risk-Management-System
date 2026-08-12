<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\DeptTicketDetailService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 24: smoke Department Head ticket detail Blade page.
 */
class SmokeSlice5DeptTicketDetail extends Command
{
    protected $signature = 'rms:smoke-slice5-dept-ticket-detail';

    protected $description = 'Smoke Laravel Department Head ticket detail Blade page';

    public function handle(DeptTicketDetailService $detail): int
    {
        $username = 'smoke_dtd_'.bin2hex(random_bytes(3));
        $password = 'SmokeDeptD1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Dept Detail',
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
            'title' => 'Smoke dept detail ticket',
            'description' => 'Detail smoke description',
            'status' => 'assigned',
            'department' => 'Information Technology',
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'category' => 'operational',
            'ownership' => ['state' => 'pending'],
            'five_w1h' => [
                'what' => 'Server outage',
                'why' => 'Power failure',
                'where' => 'DC',
                'when' => '2026-08-12',
                'who' => 'Ops',
                'how' => 'Alert',
            ],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $detail->forUser($user, $ticket->reference);
        if (! $payload || ($payload['ticket']['reference'] ?? '') !== $ticket->reference) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('dept detail service missing ticket');

            return self::FAILURE;
        }

        $html = view('dept.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['ticket']['reference'],
            'stats' => $payload['stats'],
            'ticket' => $payload['ticket'],
            'fiveW1H' => $payload['fiveW1H'],
            'attachments' => $payload['attachments'],
            'actionPlan' => $payload['actionPlan'],
            'accomplishment' => $payload['accomplishment'],
            'timeline' => $payload['timeline'],
            'reassignments' => $payload['reassignments'],
            'departments' => $payload['departments'],
            'capabilities' => $payload['capabilities'],
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, $ticket->reference) || ! str_contains($html, 'Accept ownership')) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('dept detail Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('dept detail Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
