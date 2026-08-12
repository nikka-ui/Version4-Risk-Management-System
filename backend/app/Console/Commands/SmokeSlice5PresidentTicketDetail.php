<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\PresidentTicketDetailService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 31: smoke President ticket detail Blade page.
 */
class SmokeSlice5PresidentTicketDetail extends Command
{
    protected $signature = 'rms:smoke-slice5-president-ticket-detail';

    protected $description = 'Smoke Laravel President ticket detail Blade page';

    public function handle(PresidentTicketDetailService $detail): int
    {
        $username = 'smoke_prd_'.bin2hex(random_bytes(3));
        $password = 'SmokePrd1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke President Detail',
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
            'title' => 'Smoke president detail ticket',
            'description' => 'President detail smoke description',
            'status' => 'pending_president',
            'department' => 'IT',
            'category' => 'operational',
            'likelihood' => 4,
            'impact' => 5,
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'ownership' => ['state' => 'accepted'],
            'ai' => ['severity' => 5],
            'five_w1h' => [
                'what' => 'Critical outage',
                'why' => 'Hardware failure',
                'where' => 'HQ',
                'when' => '2026-08-12',
                'who' => 'Ops team',
                'how' => 'Monitoring alert',
            ],
            'action_plan' => [
                'summary' => 'Replace failed hardware and validate failover.',
                'steps' => ['Procure spare', 'Schedule maintenance window'],
                'targetDate' => now()->addDays(7)->toIso8601String(),
                'updatedByName' => 'Dept Head',
                'updatedAt' => now()->toIso8601String(),
            ],
            'thread_comments' => [
                [
                    'id' => 'thr-smoke-1',
                    'body' => 'Smoke presidential note',
                    'authorName' => 'Smoke President',
                    'authorUsername' => $username,
                    'roleLabel' => 'President',
                    'kind' => 'governance',
                    'parentId' => null,
                    'at' => now()->toIso8601String(),
                ],
            ],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
            'submitted_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $detail->forReference($ticket->reference);
        if (! $payload || ($payload['ticket']['reference'] ?? '') !== $ticket->reference) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('president detail service missing ticket');

            return self::FAILURE;
        }

        if (empty($payload['capabilities']['canApproveActionPlan'])) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('president detail missing canApproveActionPlan');

            return self::FAILURE;
        }

        $html = view('president.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['ticket']['reference'],
            'stats' => $payload['stats'],
            'ticket' => $payload['ticket'],
            'fiveW1H' => $payload['fiveW1H'],
            'attachments' => $payload['attachments'],
            'actionPlan' => $payload['actionPlan'],
            'finalResolution' => $payload['finalResolution'],
            'rmuRecommendations' => $payload['rmuRecommendations'],
            'compliance' => $payload['compliance'],
            'decisions' => $payload['decisions'],
            'threadComments' => $payload['threadComments'],
            'capabilities' => $payload['capabilities'],
            'flash' => null,
            'error' => null,
        ])->render();

        if (
            ! str_contains($html, $ticket->reference)
            || ! str_contains($html, 'Discussion thread')
            || ! str_contains($html, 'Approve action plan')
            || ! str_contains($html, 'Department action plan')
        ) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('president detail Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('president detail Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
