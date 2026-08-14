<?php

namespace App\Console\Commands;

use App\Http\Controllers\DeptTicketDetailController;
use App\Http\Controllers\SupervisorTicketController;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 8 slice 5: smoke Department Head + Ticket Reporter comment edit/react POSTs.
 */
class SmokeSlice8CommentEdit extends Command
{
    protected $signature = 'rms:smoke-slice8-comment-edit';

    protected $description = 'Smoke Laravel comment edit and reaction POSTs';

    public function handle(
        DeptTicketDetailController $dept,
        SupervisorTicketController $reporter,
    ): int {
        $head = User::query()->create([
            'username' => 'smoke_cedt_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Comment Edit',
            'email' => 'smoke_cedt_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeCedt1!',
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Information Technology',
            'position' => 'Department Head',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);
        $reporterUser = User::query()->create([
            'username' => 'smoke_crep_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Reporter Comment',
            'email' => 'smoke_crep_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeCrep1!',
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Information Technology',
            'position' => 'Ticket Reporter',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $refDept = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $refRep = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $this->ticket($refDept, 'reporter.smoke', 'Information Technology', 'in_progress');
        $this->ticket($refRep, $reporterUser->username, 'Information Technology', 'assigned');

        Auth::login($head);
        try {
            $dept->comment($this->postRequest('/dept/tickets/'.$refDept.'/comment', [
                'comment' => 'Original dept note',
            ]), $refDept);
            $ticket = RiskTicket::query()->where('reference', $refDept)->first();
            $id = $ticket?->thread_comments[0]['id'] ?? '';
            $edited = $dept->editComment($this->postRequest('/dept/tickets/'.$refDept.'/comment/edit', [
                'commentId' => $id,
                'comment' => 'Edited dept note',
            ]), $refDept);
            $reacted = $dept->reactComment($this->postRequest('/dept/tickets/'.$refDept.'/comment/react', [
                'commentId' => $id,
                'reaction' => '👍',
            ]), $refDept);
            $ticket->refresh();
            $row = $ticket->thread_comments[0] ?? [];
            if (
                ($row['body'] ?? null) !== 'Edited dept note'
                || empty($row['editedAt'])
                || ! in_array($head->username, $row['reactions']['👍'] ?? [], true)
                || ! str_contains($edited->getTargetUrl(), 'flash=dept_comment_posted')
                || ! str_contains($reacted->getTargetUrl(), 'comment-')
            ) {
                $this->error('dept comment edit/react did not persist');

                return self::FAILURE;
            }
            $this->info('dept comment edit/react OK');
        } finally {
            Auth::logout();
        }

        Auth::login($reporterUser);
        try {
            $reporter->comment($this->postRequest('/supervisor/tickets/'.$refRep.'/comment', [
                'comment' => 'Original reporter note',
            ]), $refRep);
            $ticket = RiskTicket::query()->where('reference', $refRep)->first();
            $id = $ticket?->thread_comments[0]['id'] ?? '';
            $edited = $reporter->editComment($this->postRequest('/supervisor/tickets/'.$refRep.'/comment/edit', [
                'commentId' => $id,
                'comment' => 'Edited reporter note',
            ]), $refRep);
            $reacted = $reporter->reactComment($this->postRequest('/supervisor/tickets/'.$refRep.'/comment/react', [
                'commentId' => $id,
                'reaction' => '🎉',
            ]), $refRep);
            $ticket->refresh();
            $row = $ticket->thread_comments[0] ?? [];
            if (
                ($row['body'] ?? null) !== 'Edited reporter note'
                || empty($row['editedAt'])
                || ! in_array($reporterUser->username, $row['reactions']['🎉'] ?? [], true)
                || ! str_contains($edited->getTargetUrl(), 'flash=comment_posted')
                || ! str_contains($reacted->getTargetUrl(), 'comment-')
            ) {
                $this->error('reporter comment edit/react did not persist');

                return self::FAILURE;
            }
            $this->info('reporter comment edit/react OK');
        } finally {
            Auth::logout();
            RiskTicket::query()->whereIn('reference', [$refDept, $refRep])->delete();
            $head->delete();
            $reporterUser->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    private function ticket(string $ref, string $submittedBy, string $department, string $status): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke comment '.$ref,
            'description' => 'Smoke',
            'status' => $status,
            'submitted_by' => $submittedBy,
            'department' => $department,
            'deleted' => false,
            'thread_comments' => [],
            'ownership' => [
                'state' => 'accepted',
                'ownerDepartment' => $department,
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
