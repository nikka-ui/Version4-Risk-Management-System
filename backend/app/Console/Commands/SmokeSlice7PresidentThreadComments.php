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
 * Phase 7 slice 12: smoke President Blade comment POSTs.
 */
class SmokeSlice7PresidentThreadComments extends Command
{
    protected $signature = 'rms:smoke-slice7-president-thread-comments';

    protected $description = 'Smoke Laravel president thread comment POST';

    public function handle(PresidentTicketDetailController $controller): int
    {
        $president = User::query()->create([
            'username' => 'smoke_pthr_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke President Thread',
            'email' => 'smoke_pthr_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokePthr1!',
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
            'department' => 'Office of the President',
            'position' => 'President',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ref = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke president thread '.$ref,
            'status' => 'pending_president',
            'likelihood' => 5,
            'impact' => 5,
            'ai' => ['riskLevel' => ['id' => 'critical', 'label' => 'Critical']],
            'submitted_by' => 'reporter1',
            'submitted_by_name' => 'Reporter One',
            'thread_comments' => [],
            'executive_comments' => [],
            'deleted' => false,
        ]);

        Auth::login($president);
        try {
            $posted = $controller->comment($this->postRequest('/president/tickets/'.$ref.'/comment', [
                'comment' => 'Smoke president oversight note',
            ]), $ref);
            $ticket->refresh();
            $thread = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];
            $feed = is_array($ticket->executive_comments) ? $ticket->executive_comments : [];
            if (
                ($thread[0]['body'] ?? null) !== 'Smoke president oversight note'
                || ($thread[0]['authorRole'] ?? null) !== Roles::PRESIDENT
                || ($feed[0]['body'] ?? null) !== 'Smoke president oversight note'
                || ! str_contains($posted->getTargetUrl(), 'flash=president_comment')
            ) {
                $this->error('president thread comment did not persist');

                return self::FAILURE;
            }
            $this->info('president thread comment OK');
        } finally {
            Auth::logout();
            $ticket->delete();
            $president->delete();
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
