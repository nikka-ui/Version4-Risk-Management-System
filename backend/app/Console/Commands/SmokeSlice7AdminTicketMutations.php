<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdminTicketController;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 5: smoke admin ticket Blade soft-delete.
 */
class SmokeSlice7AdminTicketMutations extends Command
{
    protected $signature = 'rms:smoke-slice7-admin-ticket-mutations';

    protected $description = 'Smoke Laravel admin ticket soft-delete POST';

    public function handle(AdminTicketController $tickets): int
    {
        $admin = User::query()->create([
            'username' => 'smoke_tmut_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Ticket Mutations',
            'email' => 'smoke_tmut_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeTmut1!',
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'can_manage_users' => true,
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);
        $ref = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$admin->username,
            'reference' => $ref,
            'title' => 'Smoke admin delete '.$ref,
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

        Auth::login($admin);
        try {
            $deleteRequest = Request::create('/admin/tickets/'.$ref.'/delete', 'POST', [
                'reason' => 'Smoke slice 5 delete',
            ]);
            $deleteRequest->setUserResolver(fn () => Auth::user());
            $deleted = $tickets->destroy($deleteRequest, $ref);
            $ticket->refresh();
            if (
                ! $ticket->deleted
                || $ticket->deletion_reason !== 'Smoke slice 5 delete'
                || $ticket->deleted_by !== $admin->username
                || ! str_contains($deleted->getTargetUrl(), 'flash=ticket_deleted')
            ) {
                $this->error('ticket soft-delete did not persist');

                return self::FAILURE;
            }
            $this->info('ticket soft-delete OK');
        } finally {
            Auth::logout();
            $ticket->delete();
            $admin->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
