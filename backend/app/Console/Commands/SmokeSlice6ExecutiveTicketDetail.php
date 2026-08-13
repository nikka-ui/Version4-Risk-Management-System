<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\ExecutiveTicketDetailService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 6 slice 4: smoke Executive ticket detail Blade page.
 */
class SmokeSlice6ExecutiveTicketDetail extends Command
{
    protected $signature = 'rms:smoke-slice6-executive-ticket-detail';

    protected $description = 'Smoke Laravel Executive ticket detail Blade page';

    public function handle(ExecutiveTicketDetailService $detail): int
    {
        $username = 'smoke_exd_'.bin2hex(random_bytes(3));
        $password = 'SmokeExd1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Executive Detail',
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
            'title' => 'Smoke executive detail ticket',
            'description' => 'Executive detail smoke description',
            'status' => 'assigned',
            'department' => 'IT',
            'category' => 'operational',
            'likelihood' => 4,
            'impact' => 5,
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'ownership' => ['state' => 'accepted'],
            'ai' => ['severity' => 5, 'summary' => 'Smoke AI summary'],
            'five_w1h' => [
                'what' => 'Critical outage',
                'why' => 'Hardware failure',
                'where' => 'HQ',
                'when' => '2026-08-13',
                'who' => 'Ops team',
                'how' => 'Monitoring alert',
            ],
            'thread_comments' => [
                [
                    'id' => 'thr-exec-smoke-1',
                    'body' => 'Smoke executive oversight note',
                    'authorName' => 'Smoke Executive',
                    'authorUsername' => $username,
                    'roleLabel' => 'Executive Committee',
                    'kind' => 'comment',
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
            $this->error('executive detail service missing ticket');

            return self::FAILURE;
        }

        if (empty($payload['capabilities']['canPostComment'])) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('executive detail missing canPostComment');

            return self::FAILURE;
        }

        $html = view('executive.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['ticket']['reference'],
            'stats' => $payload['stats'],
            'ticket' => $payload['ticket'],
            'fiveW1H' => $payload['fiveW1H'],
            'attachments' => $payload['attachments'],
            'threadComments' => $payload['threadComments'],
            'capabilities' => $payload['capabilities'],
            'flash' => null,
            'error' => null,
        ])->render();

        if (
            ! str_contains($html, $ticket->reference)
            || ! str_contains($html, 'Discussion thread')
            || ! str_contains($html, 'Smoke executive oversight note')
            || ! str_contains($html, 'action="/executive/tickets/')
            || ! str_contains($html, 'View only for decisions')
            || str_contains($html, '/laravel/executive')
        ) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('executive detail Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('executive detail Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
