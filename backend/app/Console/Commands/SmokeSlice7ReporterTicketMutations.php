<?php

namespace App\Console\Commands;

use App\Http\Controllers\SupervisorTicketController;
use App\Http\Controllers\SupervisorTicketFormController;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 7: smoke Ticket Reporter preview save/submit + draft delete POSTs.
 */
class SmokeSlice7ReporterTicketMutations extends Command
{
    protected $signature = 'rms:smoke-slice7-reporter-ticket-mutations';

    protected $description = 'Smoke Laravel reporter preview save/submit and draft delete POSTs';

    public function handle(
        SupervisorTicketFormController $forms,
        SupervisorTicketController $tickets,
    ): int {
        $reporter = User::query()->create([
            'username' => 'smoke_rmut_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Reporter Mutations',
            'email' => 'smoke_rmut_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeRmut1!',
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Information Technology',
            'position' => 'Ticket Reporter',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $refA = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $refB = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $ticketA = $this->draftTicket($refA, $reporter->username);
        $ticketB = $this->draftTicket($refB, $reporter->username);

        Auth::login($reporter);
        try {
            $saved = $forms->saveDraft($this->postRequest('/supervisor/tickets/new/preview/'.$refA.'/save'), $refA);
            $ticketA->refresh();
            if ($ticketA->status !== 'draft' || ! str_contains($saved->getTargetUrl(), 'flash=draft_saved')) {
                $this->error('reporter preview save did not keep draft');

                return self::FAILURE;
            }
            $this->info('reporter preview save OK');

            $submitted = $forms->submit($this->postRequest('/supervisor/tickets/new/preview/'.$refA.'/submit', [
                'confirmBox' => '1',
            ]), $refA);
            $ticketA->refresh();
            if ($ticketA->status !== 'assigned' || ! str_contains($submitted->getTargetUrl(), 'flash=submitted')) {
                $this->error('reporter preview submit did not persist');

                return self::FAILURE;
            }
            $this->info('reporter preview submit OK');

            $deleted = $tickets->destroy($this->postRequest('/supervisor/tickets/'.$refB.'/delete'), $refB);
            if (
                RiskTicket::query()->where('reference', $refB)->exists()
                || ! str_contains($deleted->getTargetUrl(), 'flash=draft_deleted')
            ) {
                $this->error('reporter draft delete did not persist');

                return self::FAILURE;
            }
            $this->info('reporter draft delete OK');
        } finally {
            Auth::logout();
            $ticketA->delete();
            RiskTicket::query()->where('reference', $refB)->delete();
            $reporter->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    private function draftTicket(string $ref, string $username): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke reporter '.$ref,
            'description' => 'Smoke draft',
            'location' => 'HQ',
            'status' => 'draft',
            'submitted_by' => $username,
            'submitted_by_name' => 'Smoke Reporter',
            'evidence_count' => 1,
            'deleted' => false,
            'five_w1h' => [
                'what' => 'Smoke what',
                'why' => 'Smoke why',
                'where' => 'Smoke where',
                'when' => 'Smoke when',
                'who' => 'Smoke who',
                'how' => 'Smoke how',
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
