<?php

namespace App\Console\Commands;

use App\Http\Controllers\OfficerTicketDetailController;
use App\Models\Department;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 8: smoke RMO Blade reopen POST.
 */
class SmokeSlice7OfficerTicketMutations extends Command
{
    protected $signature = 'rms:smoke-slice7-officer-ticket-mutations';

    protected $description = 'Smoke Laravel officer reopen POST';

    public function handle(OfficerTicketDetailController $controller): int
    {
        $it = Department::query()->firstOrCreate(
            ['code' => 'IT'],
            ['external_id' => 'dept-smoke-officer-it', 'name' => 'Information Technology', 'active' => true, 'status' => 'active'],
        );
        $fin = Department::query()->firstOrCreate(
            ['code' => 'FIN'],
            ['external_id' => 'dept-smoke-officer-fin', 'name' => 'Finance', 'active' => true, 'status' => 'active'],
        );

        $officer = User::query()->create([
            'username' => 'smoke_omut_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Officer Mutations',
            'email' => 'smoke_omut_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeOmut1!',
            'role' => Roles::RM_OFFICER,
            'role_label' => Roles::label(Roles::RM_OFFICER),
            'department' => 'Risk Management Unit',
            'position' => 'Risk Management Officer',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ref = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke officer '.$ref,
            'status' => 'closed',
            'department' => $it->name,
            'submitted_by' => 'reporter1',
            'submitted_by_name' => 'Reporter One',
            'deleted' => false,
            'closure' => [
                'notes' => 'Smoke closed',
                'closedByName' => 'Dept Head',
            ],
        ]);

        Auth::login($officer);
        try {
            $reopened = $controller->reopen($this->postRequest('/officer/tickets/'.$ref.'/reopen', [
                'reason' => 'Smoke reopen',
                'department' => $fin->name,
            ]), $ref);
            $ticket->refresh();
            if (
                $ticket->status !== 'assigned'
                || $ticket->department !== $fin->name
                || ! str_contains($reopened->getTargetUrl(), 'flash=ticket_reopened')
            ) {
                $this->error('officer reopen did not persist');

                return self::FAILURE;
            }
            $this->info('officer reopen OK');
        } finally {
            Auth::logout();
            $ticket->delete();
            $officer->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function postRequest(string $uri, array $input = []): Request
    {
        $request = Request::create($uri, 'POST', $input);
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }
}
