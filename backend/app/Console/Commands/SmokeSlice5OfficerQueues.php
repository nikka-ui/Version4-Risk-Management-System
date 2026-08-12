<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\OfficerQueueService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 26: smoke RMO queue Blade pages.
 */
class SmokeSlice5OfficerQueues extends Command
{
    protected $signature = 'rms:smoke-slice5-officer-queues';

    protected $description = 'Smoke Laravel Risk Management Officer queue Blade pages';

    public function handle(OfficerQueueService $queues): int
    {
        $username = 'smoke_roq_'.bin2hex(random_bytes(3));
        $password = 'SmokeRoq1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke RMO Queues',
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
            'title' => 'Smoke officer queue ticket',
            'status' => 'assigned',
            'department' => 'Information Technology',
            'category' => 'operational',
            'likelihood' => 3,
            'impact' => 4,
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'ownership' => ['state' => 'pending'],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $queues->listForFilter('tickets');
        if (($payload['stats']['total'] ?? 0) < 1 || count($payload['tickets']) < 1) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('officer queue service missing register tickets');

            return self::FAILURE;
        }

        $html = view('officer.queue', [
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

        if (! str_contains($html, 'Organization risk register') || ! str_contains($html, $ticket->reference)) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('officer queue Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('officer queue Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
