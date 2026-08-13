<?php

namespace App\Console\Commands;

use App\Http\Controllers\PresidentTicketDetailController;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 9: smoke President Blade decision POSTs.
 */
class SmokeSlice7PresidentTicketMutations extends Command
{
    protected $signature = 'rms:smoke-slice7-president-ticket-mutations';

    protected $description = 'Smoke Laravel president action-plan approve and final close POSTs';

    public function handle(PresidentTicketDetailController $controller): int
    {
        $president = User::query()->create([
            'username' => 'smoke_pmut_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke President Mutations',
            'email' => 'smoke_pmut_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokePmut1!',
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
            'department' => 'Office of the President',
            'position' => 'President',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $refA = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $refB = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $ticketA = $this->criticalTicket($refA, 'pending_president', [
            'summary' => 'Smoke action plan',
            'steps' => ['One'],
            'submittedForReviewAt' => now()->toIso8601String(),
        ]);
        $ticketB = $this->criticalTicket($refB, 'pending_president_final');

        Auth::login($president);
        try {
            $approved = $controller->decide($this->postRequest('/president/tickets/'.$refA.'/decision', [
                'decision' => 'approve',
                'note' => 'Smoke approve',
            ]), $refA);
            $ticketA->refresh();
            if (
                $ticketA->status !== 'in_mitigation'
                || ($ticketA->president_plan_decision['decisionId'] ?? null) !== 'approve'
                || ! str_contains($approved->getTargetUrl(), 'flash=president_approve')
            ) {
                $this->error('president action-plan approve did not persist');

                return self::FAILURE;
            }
            $this->info('president action-plan approve OK');

            $closed = $controller->decide($this->postRequest('/president/tickets/'.$refB.'/decision', [
                'decision' => 'close',
                'note' => 'Smoke close',
            ]), $refB);
            $ticketB->refresh();
            if (
                $ticketB->status !== 'closed'
                || ($ticketB->president_final_decision['decisionId'] ?? null) !== 'close'
                || ! str_contains($closed->getTargetUrl(), 'flash=president_close')
            ) {
                $this->error('president final close did not persist');

                return self::FAILURE;
            }
            $this->info('president final close OK');
        } finally {
            Auth::logout();
            $ticketA->delete();
            $ticketB->delete();
            $president->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>|null  $actionPlan
     */
    private function criticalTicket(string $ref, string $status, ?array $actionPlan = null): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke president '.$ref,
            'status' => $status,
            'likelihood' => 5,
            'impact' => 5,
            'ai' => ['riskLevel' => ['id' => 'critical', 'label' => 'Critical']],
            'action_plan' => $actionPlan,
            'submitted_by' => 'reporter1',
            'submitted_by_name' => 'Reporter One',
            'deleted' => false,
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
