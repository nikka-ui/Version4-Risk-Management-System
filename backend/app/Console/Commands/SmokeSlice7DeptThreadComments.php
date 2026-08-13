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
 * Phase 7 slice 13: smoke Department Head Blade comment POSTs.
 */
class SmokeSlice7DeptThreadComments extends Command
{
    protected $signature = 'rms:smoke-slice7-dept-thread-comments';

    protected $description = 'Smoke Laravel dept head thread comment POST';

    public function handle(DeptTicketDetailController $controller): int
    {
        $head = User::query()->create([
            'username' => 'smoke_dthr_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Dept Thread',
            'email' => 'smoke_dthr_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeDthr1!',
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Information Technology',
            'position' => 'Department Head',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ref = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke dept thread '.$ref,
            'status' => 'in_progress',
            'department' => 'Information Technology',
            'likelihood' => 3,
            'impact' => 3,
            'submitted_by' => 'reporter1',
            'submitted_by_name' => 'Reporter One',
            'thread_comments' => [],
            'ownership' => [
                'state' => 'accepted',
                'ownerDepartment' => 'Information Technology',
                'ownerUsername' => $head->username,
            ],
            'deleted' => false,
        ]);

        Auth::login($head);
        try {
            $posted = $controller->comment($this->postRequest('/dept/tickets/'.$ref.'/comment', [
                'comment' => 'Smoke dept head note',
            ]), $ref);
            $ticket->refresh();
            $thread = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];
            if (
                ($thread[0]['body'] ?? null) !== 'Smoke dept head note'
                || ($thread[0]['authorRole'] ?? null) !== Roles::DEPT_HEAD
                || ! str_contains($posted->getTargetUrl(), 'flash=dept_comment_posted')
            ) {
                $this->error('dept thread comment did not persist');

                return self::FAILURE;
            }
            $this->info('dept thread comment OK');

            $replied = $controller->comment($this->postRequest('/dept/tickets/'.$ref.'/comment', [
                'comment' => 'Smoke dept reply',
                'parentId' => $thread[0]['id'],
            ]), $ref);
            $ticket->refresh();
            $thread = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];
            if (
                count($thread) !== 2
                || ($thread[1]['parentId'] ?? null) !== ($thread[0]['id'] ?? null)
                || ($thread[1]['body'] ?? null) !== 'Smoke dept reply'
                || ! str_contains($replied->getTargetUrl(), 'flash=dept_comment_posted')
            ) {
                $this->error('dept thread reply did not persist');

                return self::FAILURE;
            }
            $this->info('dept thread reply OK');
        } finally {
            Auth::logout();
            $ticket->delete();
            $head->delete();
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
