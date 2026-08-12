<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\OfficerTicketDetailService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 27: smoke RMO ticket detail Blade page.
 */
class SmokeSlice5OfficerTicketDetail extends Command
{
    protected $signature = 'rms:smoke-slice5-officer-ticket-detail';

    protected $description = 'Smoke Laravel Risk Management Officer ticket detail Blade page';

    public function handle(OfficerTicketDetailService $detail): int
    {
        $username = 'smoke_rod_'.bin2hex(random_bytes(3));
        $password = 'SmokeRod1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke RMO Detail',
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
            'title' => 'Smoke officer detail ticket',
            'description' => 'Detail smoke description',
            'status' => 'closed',
            'department' => 'Information Technology',
            'category' => 'operational',
            'likelihood' => 3,
            'impact' => 4,
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'ownership' => ['state' => 'accepted', 'ownerName' => 'Dept Owner'],
            'five_w1h' => [
                'what' => 'Server outage',
                'why' => 'Power failure',
                'where' => 'DC',
                'when' => '2026-08-12',
                'who' => 'Ops',
                'how' => 'Alert',
            ],
            'closure' => [
                'notes' => 'Closed after mitigation.',
                'closedByName' => 'Dept Owner',
                'closedAt' => now()->toIso8601String(),
            ],
            'thread_comments' => [
                [
                    'id' => 'thr-smoke-1',
                    'body' => 'Smoke governance note',
                    'authorName' => 'Smoke RMO',
                    'authorUsername' => $username,
                    'roleLabel' => 'Risk Management Officer',
                    'kind' => 'governance',
                    'parentId' => null,
                    'at' => now()->toIso8601String(),
                ],
            ],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $detail->forReference($ticket->reference);
        if (! $payload || ($payload['ticket']['reference'] ?? '') !== $ticket->reference) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('officer detail service missing ticket');

            return self::FAILURE;
        }

        if (empty($payload['capabilities']['canReopen'])) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('officer detail missing canReopen for closed ticket');

            return self::FAILURE;
        }

        $html = view('officer.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['ticket']['reference'],
            'stats' => $payload['stats'],
            'ticket' => $payload['ticket'],
            'fiveW1H' => $payload['fiveW1H'],
            'attachments' => $payload['attachments'],
            'actionPlan' => $payload['actionPlan'],
            'accomplishment' => $payload['accomplishment'],
            'closure' => $payload['closure'],
            'threadComments' => $payload['threadComments'],
            'departments' => $payload['departments'],
            'capabilities' => $payload['capabilities'],
            'flash' => null,
            'error' => null,
        ])->render();

        if (
            ! str_contains($html, $ticket->reference)
            || ! str_contains($html, 'Discussion thread')
            || ! str_contains($html, 'Reopen ticket')
            || ! str_contains($html, 'Ownership (read-only)')
        ) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('officer detail Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('officer detail Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
