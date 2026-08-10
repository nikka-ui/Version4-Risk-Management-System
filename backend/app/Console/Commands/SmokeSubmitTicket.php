<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DraftTicketService;
use App\Services\SubmitTicketService;
use Illuminate\Console\Command;

/**
 * Smoke-test draft create + submit against Postgres (does not touch Express store.json).
 */
class SmokeSubmitTicket extends Command
{
    protected $signature = 'rms:smoke-submit {--username=admin}';

    protected $description = 'Create a draft and submit it in Laravel (API smoke test)';

    public function handle(DraftTicketService $drafts, SubmitTicketService $submitter): int
    {
        $username = (string) $this->option('username');
        $user = User::query()->where('username', $username)->first();
        if (! $user) {
            $this->error("User not found: {$username}. Run rms:import-users first.");

            return self::FAILURE;
        }

        $ticket = $drafts->create($user, [
            'title' => 'Slice4 smoke submit',
            'what' => 'Smoke what',
            'why' => 'Smoke why',
            'where' => 'Smoke where',
            'when' => 'Smoke when',
            'who' => 'Smoke who',
            'how' => 'Smoke how',
            'evidenceCount' => 1,
        ]);
        $this->info('created '.$ticket->reference.' status='.$ticket->status);

        $ticket = $submitter->submit($ticket, $user);
        $this->info('submitted '.$ticket->reference.' status='.$ticket->status.' dept='.$ticket->department);

        // Clean up so smoke runs stay idempotent for list noise.
        $ticket->delete();
        $this->info('cleaned up '.$ticket->reference);
        $this->line('Express store.json was not modified. USE_LARAVEL_API defaults ON (Phase 5); Express UI remains browser entry.');

        return self::SUCCESS;
    }
}
