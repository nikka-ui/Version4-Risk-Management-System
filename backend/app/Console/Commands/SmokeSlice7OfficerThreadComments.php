<?php

namespace App\Console\Commands;

use App\Http\Controllers\OfficerTicketDetailController;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 11: smoke RMO Blade thread-comment POSTs.
 */
class SmokeSlice7OfficerThreadComments extends Command
{
    protected $signature = 'rms:smoke-slice7-officer-thread-comments';

    protected $description = 'Smoke Laravel officer thread comment POST';

    public function handle(OfficerTicketDetailController $controller): int
    {
        $officer = User::query()->create([
            'username' => 'smoke_othr_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Officer Thread',
            'email' => 'smoke_othr_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeOthr1!',
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
            'title' => 'Smoke officer thread '.$ref,
            'status' => 'submitted',
            'likelihood' => 3,
            'impact' => 3,
            'submitted_by' => 'reporter1',
            'submitted_by_name' => 'Reporter One',
            'thread_comments' => [],
            'deleted' => false,
        ]);

        Auth::login($officer);
        try {
            $posted = $controller->comment($this->postRequest('/officer/tickets/'.$ref.'/thread-comment', [
                'comment' => 'Smoke RMO governance note',
            ]), $ref);
            $ticket->refresh();
            $thread = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];
            if (
                ($thread[0]['body'] ?? null) !== 'Smoke RMO governance note'
                || ($thread[0]['kind'] ?? null) !== 'governance'
                || ($thread[0]['authorRole'] ?? null) !== Roles::RM_OFFICER
                || ! str_contains($posted->getTargetUrl(), 'flash=rmu_thread_comment')
            ) {
                $this->error('officer thread comment did not persist');

                return self::FAILURE;
            }
            $this->info('officer thread comment OK');

            $replied = $controller->comment($this->postRequest('/officer/tickets/'.$ref.'/thread-comment', [
                'comment' => 'Smoke RMO reply',
                'parentId' => $thread[0]['id'],
            ]), $ref);
            $ticket->refresh();
            $thread = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];
            if (
                count($thread) !== 2
                || ($thread[1]['parentId'] ?? null) !== ($thread[0]['id'] ?? null)
                || ($thread[1]['body'] ?? null) !== 'Smoke RMO reply'
                || ($thread[1]['kind'] ?? null) !== 'governance'
                || ! str_contains($replied->getTargetUrl(), 'flash=rmu_thread_comment')
            ) {
                $this->error('officer thread reply did not persist');

                return self::FAILURE;
            }
            $this->info('officer thread reply OK');
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
