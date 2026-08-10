<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AttachmentService;
use App\Services\DraftTicketService;
use Illuminate\Console\Command;

/**
 * Smoke-test attachment metadata register/list/sync (Postgres only; no MinIO).
 */
class SmokeSlice8Attachments extends Command
{
    protected $signature = 'rms:smoke-slice8
                            {--reporter=admin}';

    protected $description = 'Smoke attachment metadata APIs in Laravel (does not touch Express/MinIO uploads)';

    public function handle(DraftTicketService $drafts, AttachmentService $attachments): int
    {
        $reporterName = (string) $this->option('reporter');
        $reporter = User::query()->where('username', $reporterName)->first();
        if (! $reporter) {
            $this->error("Reporter not found: {$reporterName}");

            return self::FAILURE;
        }

        $ticket = $drafts->create($reporter, [
            'title' => 'Slice8 attachment smoke',
            'what' => 'Attachment metadata',
            'why' => 'Smoke',
            'where' => 'HQ',
            'when' => 'Now',
            'who' => 'Ops',
            'how' => 'Test',
            'evidenceCount' => 1,
        ]);
        $this->info("created {$ticket->reference}");

        $att = $attachments->register($ticket->reference, [
            'id' => 'att-smoke-slice8',
            'originalName' => 'smoke.pdf',
            'mimeType' => 'application/pdf',
            'size' => 512,
            'storageKey' => "{$ticket->reference}/att-smoke-slice8-smoke.pdf",
            'uploadedBy' => $reporter->username,
        ]);
        $this->info("registered {$att->id}");

        $list = $attachments->listForTicket($ticket->reference);
        $this->info('listed count='.count($list));

        $synced = $attachments->syncEvidenceCount($ticket->reference);
        $this->info("synced evidenceCount={$synced}");

        $attachments->deleteMetadata($att->id);
        $ticket->delete();
        $this->info("cleaned up {$ticket->reference}");
        $this->line('Express store.json / MinIO were not modified. USE_LARAVEL_API defaults ON (Phase 5); Express UI remains browser entry.');

        return self::SUCCESS;
    }
}
