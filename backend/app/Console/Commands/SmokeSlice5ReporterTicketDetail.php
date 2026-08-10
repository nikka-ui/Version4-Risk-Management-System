<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\SupervisorTicketDetailService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 9: smoke Ticket Reporter ticket detail Blade page.
 */
class SmokeSlice5ReporterTicketDetail extends Command
{
    protected $signature = 'rms:smoke-slice5-reporter-ticket-detail';

    protected $description = 'Smoke Laravel Ticket Reporter ticket detail Blade page';

    public function handle(SupervisorTicketDetailService $detail): int
    {
        $username = 'smoke_det_'.bin2hex(random_bytes(3));
        $password = 'SmokeDet1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Detail Reporter',
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

        $assigned = RiskTicket::query()->create([
            'external_id' => 'ext-a-'.$username,
            'reference' => 'RISK-SMOKE-A-'.strtoupper(substr($username, -5)),
            'title' => 'Smoke assigned ticket',
            'description' => 'Detail smoke description',
            'status' => 'assigned',
            'category' => 'operational',
            'priority' => 'medium',
            'likelihood' => 3,
            'impact' => 3,
            'risk_score' => 9,
            'department' => 'Operations',
            'submitted_by' => $username,
            'submitted_by_name' => $user->name,
            'five_w1h' => [
                'what' => 'Smoke what',
                'why' => 'Smoke why',
                'where' => 'Smoke where',
                'when' => 'Smoke when',
                'who' => 'Smoke who',
                'how' => 'Smoke how',
            ],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
            'submitted_at' => now(),
        ]);

        $draft = RiskTicket::query()->create([
            'external_id' => 'ext-d-'.$username,
            'reference' => 'RISK-SMOKE-B-'.strtoupper(substr($username, -5)),
            'title' => 'Smoke draft ticket',
            'status' => 'draft',
            'submitted_by' => $username,
            'submitted_by_name' => $user->name,
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        $this->info("created {$username} + tickets");

        Auth::login($user);
        $payload = $detail->forUsername($username, $assigned->reference);
        if (! $payload || ! empty($payload['redirect_edit']) || ($payload['ticket']['reference'] ?? null) !== $assigned->reference) {
            Auth::logout();
            $assigned->delete();
            $draft->delete();
            $user->delete();
            $this->error('detail service failed for assigned ticket');

            return self::FAILURE;
        }

        $draftPayload = $detail->forUsername($username, $draft->reference);
        if (! $draftPayload || empty($draftPayload['redirect_edit'])) {
            Auth::logout();
            $assigned->delete();
            $draft->delete();
            $user->delete();
            $this->error('draft should redirect to Express edit');

            return self::FAILURE;
        }

        $html = view('supervisor.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'tickets',
            'title' => $payload['ticket']['reference'],
            'ticket' => $payload['ticket'],
            'attachments' => $payload['attachments'],
            'fiveW1H' => $payload['fiveW1H'],
            'timeline' => $payload['timeline'],
            'accomplishment' => $payload['accomplishment'],
            'error' => null,
            'flash' => null,
        ])->render();

        if (! str_contains($html, $assigned->reference) || ! str_contains($html, 'Smoke what')) {
            Auth::logout();
            $assigned->delete();
            $draft->delete();
            $user->delete();
            $this->error('detail Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('supervisor ticket detail Blade OK');

        Auth::logout();
        $assigned->delete();
        $draft->delete();
        $user->delete();
        $this->info('cleaned up');
        $this->line('Flag USE_LARAVEL_REPORTER_TICKET_DETAIL_UI: Express /supervisor/tickets/:ref → Blade');

        return self::SUCCESS;
    }
}
