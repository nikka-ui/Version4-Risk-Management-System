<?php

namespace App\Console\Commands;

use App\Http\Controllers\SupervisorTicketController;
use App\Models\Accomplishment;
use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 8 slice 2: smoke Ticket Reporter evidence + accomplishment multipart POSTs.
 */
class SmokeSlice8ReporterEvidence extends Command
{
    protected $signature = 'rms:smoke-slice8-reporter-evidence';

    protected $description = 'Smoke Laravel reporter evidence and accomplishment POSTs';

    public function handle(SupervisorTicketController $tickets): int
    {
        Storage::fake('evidence');

        $reporter = User::query()->create([
            'username' => 'smoke_revd_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Reporter Evidence',
            'email' => 'smoke_revd_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeRevd1!',
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
        $this->ticket($refA, $reporter->username, 'reopened');
        $this->ticket($refB, $reporter->username, 'in_mitigation', true);

        Auth::login($reporter);
        try {
            $added = $tickets->addEvidence($this->postRequest(
                '/supervisor/tickets/'.$refA.'/evidence',
                [],
                [UploadedFile::fake()->create('reopen.pdf', 8, 'application/pdf')],
            ), $refA);
            $ticketA = RiskTicket::query()->where('reference', $refA)->first();
            if (
                ! $ticketA
                || (int) $ticketA->evidence_count < 1
                || ! str_contains($added->getTargetUrl(), 'flash=evidence_added')
            ) {
                $this->error('reporter evidence upload did not persist');

                return self::FAILURE;
            }
            $this->info('reporter evidence upload OK');

            $submitted = $tickets->submitAccomplishment($this->postRequest(
                '/supervisor/tickets/'.$refB.'/accomplishment',
                [
                    'summary' => 'Smoke implementation summary',
                    'outcomes' => 'Smoke outcomes',
                ],
                [UploadedFile::fake()->create('proof.png', 6, 'image/png')],
            ), $refB);
            $ticketB = RiskTicket::query()->where('reference', $refB)->first();
            $acc = Accomplishment::query()->where('ticket_ref', $refB)->first();
            if (
                ! $ticketB
                || $ticketB->status !== 'pending_audit'
                || ! $acc
                || $acc->summary !== 'Smoke implementation summary'
                || ! str_contains($submitted->getTargetUrl(), 'flash=accomplishment_submitted')
            ) {
                $this->error('reporter accomplishment did not persist');

                return self::FAILURE;
            }
            $this->info('reporter accomplishment OK');
        } finally {
            Auth::logout();
            RiskAttachment::query()->whereIn('ticket_ref', [$refA, $refB])->delete();
            Accomplishment::query()->whereIn('ticket_ref', [$refA, $refB])->delete();
            RiskTicket::query()->whereIn('reference', [$refA, $refB])->delete();
            $reporter->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    private function ticket(string $ref, string $username, string $status, bool $withPlan = false): RiskTicket
    {
        $now = now();

        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke evidence '.$ref,
            'description' => 'Smoke',
            'location' => 'HQ',
            'status' => $status,
            'submitted_by' => $username,
            'submitted_by_name' => 'Smoke Reporter',
            'department' => 'Information Technology',
            'evidence_count' => 0,
            'deleted' => false,
            'mitigation_due_at' => $withPlan ? $now->copy()->addDays(14) : null,
            'ownership' => $withPlan ? [
                'state' => 'accepted',
                'ownerUsername' => 'dept.head',
                'ownerName' => 'Dept Head',
                'ownerDepartment' => 'Information Technology',
            ] : null,
            'action_plan' => $withPlan ? [
                'summary' => 'Publish the smoke plan',
                'publishedToReporterAt' => $now->toIso8601String(),
            ] : null,
            'payload' => $withPlan ? ['officerNotes' => 'Implement the smoke plan.'] : null,
            'five_w1h' => [
                'what' => 'what', 'why' => 'why', 'where' => 'where',
                'when' => 'when', 'who' => 'who', 'how' => 'how',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $files
     */
    private function postRequest(string $uri, array $input = [], array $files = []): Request
    {
        $request = Request::create($uri, 'POST', $input, [], $files !== [] ? ['attachments' => $files] : []);
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }
}
