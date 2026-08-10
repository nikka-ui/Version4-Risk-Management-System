<?php

namespace App\Console\Commands;

use App\Models\Accomplishment;
use App\Models\RiskTicket;
use App\Models\User;
use App\Services\SupervisorAccomplishmentService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 11: smoke Ticket Reporter accomplishments Blade page.
 */
class SmokeSlice5ReporterAccomplishments extends Command
{
    protected $signature = 'rms:smoke-slice5-reporter-accomplishments';

    protected $description = 'Smoke Laravel Ticket Reporter accomplishments Blade page';

    public function handle(SupervisorAccomplishmentService $service): int
    {
        $username = 'smoke_acc_'.bin2hex(random_bytes(3));
        $password = 'SmokeAcc1!';
        $ref = 'RISK-SMOKE-C-'.strtoupper(substr($username, -5));
        $accId = 'acc-smoke-'.bin2hex(random_bytes(3));

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Accomplishments Reporter',
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

        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-acc-'.$username,
            'reference' => $ref,
            'title' => 'Smoke accomplishment ticket',
            'status' => 'pending_audit',
            'submitted_by' => $username,
            'submitted_by_name' => $user->name,
            'accomplishment_external_id' => $accId,
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
            'submitted_at' => now(),
        ]);

        Accomplishment::query()->create([
            'external_id' => $accId,
            'ticket_ref' => $ref,
            'ticket_title' => 'Smoke accomplishment ticket',
            'summary' => 'Smoke implementation summary completed',
            'outcomes' => 'Smoke outcomes',
            'submitted_by' => $username,
            'submitted_by_name' => $user->name,
            'submitted_at' => now(),
            'evidence' => [],
            'payload' => [],
        ]);
        $this->info("created {$username} + accomplishment");

        Auth::login($user);
        $rows = $service->listForUsername($username);
        if (! collect($rows)->contains(fn ($r) => ($r['ticketRef'] ?? null) === $ref)) {
            Auth::logout();
            Accomplishment::query()->where('external_id', $accId)->delete();
            $ticket->delete();
            $user->delete();
            $this->error('accomplishment list missing smoke row');

            return self::FAILURE;
        }

        $html = view('supervisor.accomplishments', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'accomplishments',
            'title' => 'Accomplishment reports',
            'accomplishments' => $rows,
            'flash' => null,
        ])->render();

        if (! str_contains($html, $ref) || ! str_contains($html, 'Smoke implementation summary')) {
            Auth::logout();
            Accomplishment::query()->where('external_id', $accId)->delete();
            $ticket->delete();
            $user->delete();
            $this->error('accomplishments Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('supervisor accomplishments Blade OK');

        Auth::logout();
        Accomplishment::query()->where('external_id', $accId)->delete();
        $ticket->delete();
        $user->delete();
        $this->info('cleaned up');
        $this->line('Flag USE_LARAVEL_REPORTER_ACCOMPLISHMENTS_UI: Express /supervisor/accomplishments → Blade');

        return self::SUCCESS;
    }
}
