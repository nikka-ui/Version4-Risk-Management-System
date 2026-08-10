<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use App\Services\DeptTicketService;
use App\Services\DraftTicketService;
use App\Services\OfficerTicketService;
use App\Services\SubmitTicketService;
use App\Services\ThreadCommentService;
use App\Support\Roles;
use Illuminate\Console\Command;

/**
 * Smoke-test personnel, documents metadata, comments, and RMO reopen (Postgres only).
 */
class SmokeSlice7Workflow extends Command
{
    protected $signature = 'rms:smoke-slice7
                            {--reporter=admin}
                            {--dept-head= : Dept head username}
                            {--officer= : RMO username}';

    protected $description = 'Smoke personnel→documents→comment→reopen in Laravel (does not touch Express)';

    public function handle(
        DraftTicketService $drafts,
        SubmitTicketService $submitter,
        DeptTicketService $deptTickets,
        ThreadCommentService $comments,
        OfficerTicketService $officerTickets,
    ): int {
        Department::query()->firstOrCreate(
            ['external_id' => 'dept-it-smoke7'],
            ['name' => 'Information Technology', 'code' => 'IT', 'active' => true, 'status' => 'active'],
        );

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

        $officerName = $this->option('officer');
        $officer = $officerName
            ? User::query()->where('username', $officerName)->first()
            : User::query()->where('role', Roles::RM_OFFICER)->where('active', true)->first();
        if (! $officer) {
            $this->error('No rm_officer user found. Run rms:import-users first.');

            return self::FAILURE;
        }

        $ticket = $drafts->create($reporter, [
            'title' => 'Slice7 workflow smoke',
            'what' => 'Workflow test',
            'why' => 'Smoke',
            'where' => 'HQ',
            'when' => 'Now',
            'who' => 'Ops',
            'how' => 'Test',
            'evidenceCount' => 1,
            'reporterDepartment' => $deptHead->department,
        ]);
        $this->info("created {$ticket->reference}");

        $ticket = $submitter->submit($ticket, $reporter);
        $ticket->department = $deptHead->department;
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        $ownership['ownerDepartment'] = $deptHead->department;
        $ticket->ownership = $ownership;
        $ticket->save();

        $ticket = $deptTickets->accept($ticket->fresh(), $deptHead, ['comment' => 'smoke accept']);
        $this->info("accepted status={$ticket->status}");

        $ticket = $deptTickets->assignPersonnel($ticket, $deptHead, [
            'personName' => 'Smoke Engineer',
            'personRole' => 'Implementer',
        ]);
        $this->info('personnel assigned count='.count($ticket->personnel ?? []));

        $ticket = $deptTickets->recordDocuments($ticket, $deptHead, [
            'fileCount' => 1,
            'fileNames' => ['smoke-doc.pdf'],
        ]);
        $this->info('documents recorded');

        $ticket = $comments->add($ticket, $deptHead, ['comment' => 'Slice7 dept comment']);
        $this->info('comment count='.count($ticket->thread_comments ?? []));

        $ticket->status = 'closed';
        $ticket->closure = [
            'closedAt' => now()->toIso8601String(),
            'closedBy' => $deptHead->username,
            'closedByRole' => 'dept_head',
            'notes' => 'smoke close',
        ];
        $ticket->save();

        $ticket = $officerTickets->reopen($ticket->fresh(), $officer, [
            'reason' => 'Smoke reopen for verification',
            'department' => $deptHead->department,
        ]);
        $this->info("reopened status={$ticket->status} dept={$ticket->department}");

        $ref = $ticket->reference;
        $ticket->delete();
        $this->info("cleaned up {$ref}");
        $this->line('Express store.json was not modified. USE_LARAVEL_API defaults ON (Phase 5); Express UI remains browser entry.');

        return self::SUCCESS;
    }
}
