<?php

namespace App\Console\Commands;

use App\Http\Controllers\ExecutiveTicketDetailController;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 10: smoke Executive Blade comment POSTs.
 */
class SmokeSlice7ExecutiveTicketMutations extends Command
{
    protected $signature = 'rms:smoke-slice7-executive-ticket-mutations';

    protected $description = 'Smoke Laravel executive thread comment POST';

    public function handle(ExecutiveTicketDetailController $controller): int
    {
        $executive = User::query()->create([
            'username' => 'smoke_exmut_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Executive Mutations',
            'email' => 'smoke_exmut_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeExmut1!',
            'role' => Roles::EXECUTIVE,
            'role_label' => Roles::label(Roles::EXECUTIVE),
            'department' => 'Executive Committee',
            'position' => 'Executive Committee',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ref = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke executive '.$ref,
            'status' => 'submitted',
            'likelihood' => 3,
            'impact' => 3,
            'submitted_by' => 'reporter1',
            'submitted_by_name' => 'Reporter One',
            'thread_comments' => [],
            'executive_comments' => [],
            'deleted' => false,
        ]);

        Auth::login($executive);
        try {
            $posted = $controller->comment($this->postRequest('/executive/tickets/'.$ref.'/comment', [
                'comment' => 'Smoke executive oversight note',
            ]), $ref);
            $ticket->refresh();
            $thread = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];
            $feed = is_array($ticket->executive_comments) ? $ticket->executive_comments : [];
            $threadBody = (string) ($thread[0]['body'] ?? '');
            $feedBody = (string) ($feed[0]['body'] ?? '');
            if (
                $threadBody !== 'Smoke executive oversight note'
                || $feedBody !== 'Smoke executive oversight note'
                || ($thread[0]['authorRole'] ?? null) !== Roles::EXECUTIVE
                || ! str_contains($posted->getTargetUrl(), 'flash=executive_comment_added')
            ) {
                $this->error('executive comment did not persist');

                return self::FAILURE;
            }
            $this->info('executive comment OK');

            $replied = $controller->comment($this->postRequest('/executive/tickets/'.$ref.'/comment', [
                'comment' => 'Smoke executive reply',
                'parentId' => $thread[0]['id'],
            ]), $ref);
            $ticket->refresh();
            $thread = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];
            if (
                count($thread) !== 2
                || ($thread[1]['parentId'] ?? null) !== ($thread[0]['id'] ?? null)
                || ($thread[1]['body'] ?? null) !== 'Smoke executive reply'
                || ! str_contains($replied->getTargetUrl(), 'flash=executive_comment_added')
            ) {
                $this->error('executive reply did not persist');

                return self::FAILURE;
            }
            $this->info('executive reply OK');
        } finally {
            Auth::logout();
            $ticket->delete();
            $executive->delete();
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
