<?php

namespace App\Console\Commands;

use App\Http\Controllers\DeptTicketDetailController;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 8 slice 4: smoke Department Head personnel + resolution POSTs.
 */
class SmokeSlice8DeptPersonnel extends Command
{
    protected $signature = 'rms:smoke-slice8-dept-personnel';

    protected $description = 'Smoke Laravel dept personnel and resolution POSTs';

    public function handle(DeptTicketDetailController $controller): int
    {
        $head = User::query()->create([
            'username' => 'smoke_dper_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Dept Personnel',
            'email' => 'smoke_dper_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeDper1!',
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Information Technology',
            'position' => 'Department Head',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $refA = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $refB = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $this->inProgressTicket($refA, $head->username);
        $this->pendingAuditTicket($refB, $head->username);

        Auth::login($head);
        try {
            $assigned = $controller->assignPersonnel($this->postRequest('/dept/tickets/'.$refA.'/personnel', [
                'personName' => 'Alex Tech',
                'personRole' => 'Implementer',
            ]), $refA);
            $ticketA = RiskTicket::query()->where('reference', $refA)->first();
            $names = array_column(is_array($ticketA?->personnel) ? $ticketA->personnel : [], 'name');
            if (! in_array('Alex Tech', $names, true) || ! str_contains($assigned->getTargetUrl(), 'flash=personnel_assigned')) {
                $this->error('dept personnel did not persist');

                return self::FAILURE;
            }
            $this->info('dept personnel OK');

            $closed = $controller->resolution($this->postRequest('/dept/tickets/'.$refB.'/resolution', [
                'closingNotes' => 'Smoke resolution notes',
            ]), $refB);
            $ticketB = RiskTicket::query()->where('reference', $refB)->first();
            if (
                $ticketB?->status !== 'closed'
                || ($ticketB->closure['notes'] ?? null) !== 'Smoke resolution notes'
                || ! str_contains($closed->getTargetUrl(), 'flash=resolution_submitted')
            ) {
                $this->error('dept resolution did not persist');

                return self::FAILURE;
            }
            $this->info('dept resolution OK');
        } finally {
            Auth::logout();
            RiskTicket::query()->whereIn('reference', [$refA, $refB])->delete();
            $head->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    private function inProgressTicket(string $ref, string $owner): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke personnel '.$ref,
            'description' => 'Smoke',
            'status' => 'in_progress',
            'submitted_by' => 'reporter.smoke',
            'department' => 'Information Technology',
            'deleted' => false,
            'personnel' => [],
            'ownership' => [
                'state' => 'accepted',
                'ownerUsername' => $owner,
                'ownerName' => 'Smoke Dept Personnel',
                'ownerDepartment' => 'Information Technology',
            ],
        ]);
    }

    private function pendingAuditTicket(string $ref, string $owner): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke resolution '.$ref,
            'description' => 'Smoke',
            'status' => 'pending_audit',
            'submitted_by' => 'reporter.smoke',
            'department' => 'Information Technology',
            'accomplishment_external_id' => 'acc-'.$ref,
            'deleted' => false,
            'ownership' => [
                'state' => 'accepted',
                'ownerUsername' => $owner,
                'ownerName' => 'Smoke Dept Personnel',
                'ownerDepartment' => 'Information Technology',
            ],
        ]);
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
