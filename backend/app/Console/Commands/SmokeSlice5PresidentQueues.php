<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\PresidentQueueService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 30: smoke President queue Blade pages.
 */
class SmokeSlice5PresidentQueues extends Command
{
    protected $signature = 'rms:smoke-slice5-president-queues';

    protected $description = 'Smoke Laravel President queue Blade pages';

    public function handle(PresidentQueueService $queues): int
    {
        $username = 'smoke_prq_'.bin2hex(random_bytes(3));
        $password = 'SmokePrq1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke President Queues',
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
            'title' => 'Smoke president queue ticket',
            'description' => 'President queue smoke detail',
            'status' => 'pending_president',
            'department' => 'IT',
            'category' => 'operational',
            'likelihood' => 4,
            'impact' => 5,
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'ownership' => ['state' => 'accepted'],
            'ai' => ['severity' => 5],
            'action_plan' => ['summary' => 'Sample action plan summary'],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
            'submitted_at' => now(),
        ]);

        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $queues->listForFilter('pending');

        if (($payload['stats']['pendingCount'] ?? 0) < 1 || count($payload['tickets']) < 1) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('president queue service missing pending tickets');

            return self::FAILURE;
        }

        $html = view('president.queue', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['title'],
            'pageDesc' => $payload['desc'],
            'emptyMessage' => $payload['emptyMessage'],
            'stats' => $payload['stats'],
            'tickets' => $payload['tickets'],
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'Pending decisions') || ! str_contains($html, $ticket->reference)) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('president queue Blade missing expected content');

            return self::FAILURE;
        }

        $trendsPayload = $queues->trendsData();
        $trendsHtml = view('president.trends', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'trends',
            'title' => 'Trends',
            'stats' => $trendsPayload['stats'],
            'trends' => $trendsPayload['trends'],
            'flash' => null,
        ])->render();

        if (! str_contains($trendsHtml, 'Report volume trend')) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('president trends Blade missing expected content');

            return self::FAILURE;
        }

        $this->info('president queue + trends Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
