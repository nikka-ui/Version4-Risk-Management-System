<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DeptTicketService;
use App\Services\DraftTicketService;
use App\Services\SubmitTicketService;
use App\Support\Roles;
use Illuminate\Console\Command;

/**
 * Smoke-test reporter submit + dept accept + action plan (Postgres only).
 */
class SmokeDeptTicket extends Command
{
    protected $signature = 'rms:smoke-dept
                            {--reporter=admin}
                            {--dept-head= : Dept head username (default: first dept_head user)}';

    protected $description = 'Smoke draft→submit→accept→action-plan in Laravel (does not touch Express)';

    public function handle(
        DraftTicketService $drafts,
        SubmitTicketService $submitter,
        DeptTicketService $deptTickets,
    ): int {
        $reporterName = (string) $this->option('reporter');
        $reporter = User::query()->where('username', $reporterName)->first();
        if (! $reporter) {
            $this->error("Reporter not found: {$reporterName}");

            return self::FAILURE;
        }

        $deptHeadName = $this->option('dept-head');
        $deptHead = $deptHeadName
            ? User::query()->where('username', $deptHeadName)->first()
            : User::query()->where('role', Roles::DEPT_HEAD)->where('active', true)->first();

        if (! $deptHead) {
            $this->error('No dept_head user found. Run rms:import-users first.');

            return self::FAILURE;
        }

        // Align reporter department so AI routing / dept match is more likely.
        if ($deptHead->department && ! $reporter->department) {
            $reporter->department = $deptHead->department;
            $reporter->save();
        }

        $ticket = $drafts->create($reporter, [
            'title' => 'Slice5 dept smoke — IT systems risk',
            'what' => 'Systems outage risk',
            'why' => 'Weak controls',
            'where' => 'HQ',
            'when' => 'Now',
            'who' => 'Ops',
            'how' => 'Single path failure',
            'evidenceCount' => 1,
            'reporterDepartment' => $deptHead->department,
        ]);
        $this->info("created {$ticket->reference}");

        $ticket = $submitter->submit($ticket, $reporter);
        // Force department match for smoke reliability (AI routing can vary).
        $ticket->department = $deptHead->department;
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        $ownership['ownerDepartment'] = $deptHead->department;
        $ticket->ownership = $ownership;
        $ticket->save();
        $this->info("submitted status={$ticket->status} dept={$ticket->department}");

        $ticket = $deptTickets->accept($ticket->fresh(), $deptHead, ['comment' => 'smoke accept']);
        $this->info("accepted status={$ticket->status}");

        $ticket = $deptTickets->saveActionPlan($ticket, $deptHead, [
            'summary' => 'Smoke mitigation plan',
            'steps' => "Step one\nStep two",
            'targetDate' => now()->addDays(14)->toDateString(),
            'submitForReview' => true,
        ]);
        $this->info("action-plan status={$ticket->status}");

        $ref = $ticket->reference;
        $ticket->delete();
        $this->info("cleaned up {$ref}");
        $this->line('Express store.json was not modified. USE_LARAVEL_API remains OFF by default.');

        return self::SUCCESS;
    }
}
