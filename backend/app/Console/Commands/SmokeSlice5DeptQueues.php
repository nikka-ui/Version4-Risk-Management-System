<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\DeptDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 23: smoke Department Head queue Blade pages.
 */
class SmokeSlice5DeptQueues extends Command
{
    protected $signature = 'rms:smoke-slice5-dept-queues';

    protected $description = 'Smoke Laravel Department Head queue Blade pages';

    public function handle(DeptDashboardService $queues): int
    {
        $username = 'smoke_dq_'.bin2hex(random_bytes(3));
        $password = 'SmokeDeptQ1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Dept Queues',
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
            'title' => 'Smoke dept queue ticket',
            'status' => 'assigned',
            'department' => 'Information Technology',
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'category' => 'operational',
            'ownership' => ['state' => 'pending'],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $queues->listForUser($user, 'inbox');
        if (($payload['stats']['inbox'] ?? 0) < 1 || count($payload['tickets']) < 1) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('dept queue service missing inbox tickets');

            return self::FAILURE;
        }

        $html = view('dept.queue', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['title'],
            'pageDesc' => $payload['desc'],
            'emptyMessage' => $payload['emptyMessage'],
            'stats' => $payload['stats'],
            'tickets' => $payload['tickets'],
            'showDueColumn' => false,
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'Ownership inbox') || ! str_contains($html, $ticket->reference)) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('dept queue Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('dept queue Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
