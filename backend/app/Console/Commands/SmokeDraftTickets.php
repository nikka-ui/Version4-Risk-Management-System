<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DraftTicketService;
use Illuminate\Console\Command;

/**
 * Smoke-test draft CRUD against Postgres (does not touch Express store.json).
 */
class SmokeDraftTickets extends Command
{
    protected $signature = 'rms:smoke-draft {--username=admin}';

    protected $description = 'Create/update/delete a temporary draft ticket in Laravel (API smoke test)';

    public function handle(DraftTicketService $drafts): int
    {
        $username = (string) $this->option('username');
        $user = User::query()->where('username', $username)->first();
        if (! $user) {
            $this->error("User not found: {$username}. Run rms:import-users first.");

            return self::FAILURE;
        }

        $payload = [
            'title' => 'Slice2 smoke draft',
            'what' => 'Smoke what',
            'why' => 'Smoke why',
            'where' => 'Smoke where',
            'when' => 'Smoke when',
            'who' => 'Smoke who',
            'how' => 'Smoke how',
            'evidenceCount' => 1,
        ];

        $ticket = $drafts->create($user, $payload);
        $this->info('created '.$ticket->reference.' status='.$ticket->status);

        $payload['title'] = 'Slice2 smoke updated';
        $payload['evidenceCount'] = 2;
        $ticket = $drafts->updateDraft($ticket, $user, $payload);
        $this->info('updated title='.$ticket->title.' evidence='.$ticket->evidence_count);

        $ref = $drafts->deleteDraft($ticket, $user);
        $this->info('deleted '.$ref);
        $this->line('Express store.json was not modified. USE_LARAVEL_API defaults ON (Phase 5); Express UI remains browser entry.');

        return self::SUCCESS;
    }
}
