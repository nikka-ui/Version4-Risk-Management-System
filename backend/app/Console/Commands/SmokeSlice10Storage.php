<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AttachmentService;
use App\Services\DraftTicketService;
use App\Services\ObjectStorageService;
use Illuminate\Console\Command;

/**
 * Smoke-test attachment file-byte upload/download to the shared MinIO bucket.
 * Writes to object storage (NOT Express store.json); cleans up after itself.
 */
class SmokeSlice10Storage extends Command
{
    protected $signature = 'rms:smoke-slice10
                            {--reporter=admin}';

    protected $description = 'Smoke attachment upload/download bytes in Laravel (shared MinIO; Express stays live path)';

    public function handle(
        DraftTicketService $drafts,
        AttachmentService $attachments,
        ObjectStorageService $storage,
    ): int {
        $reporterName = (string) $this->option('reporter');
        $reporter = User::query()->where('username', $reporterName)->first();
        if (! $reporter) {
            $this->error("Reporter not found: {$reporterName}");

            return self::FAILURE;
        }

        $ticket = $drafts->create($reporter, [
            'title' => 'Slice10 storage smoke',
            'what' => 'Attachment bytes',
            'why' => 'Smoke',
            'where' => 'HQ',
            'when' => 'Now',
            'who' => 'Ops',
            'how' => 'Test',
            'evidenceCount' => 1,
        ]);
        $this->info("created {$ticket->reference}");

        // Minimal valid PDF payload.
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

        $att = $attachments->storeRawFile(
            $ticket->reference,
            'slice10-smoke.pdf',
            'application/pdf',
            $pdf,
            $reporter->username,
        );
        $this->info("stored {$att->id} key={$att->storage_key}");

        if (! $storage->exists($att->storage_key)) {
            $this->error('Object missing after upload.');

            return self::FAILURE;
        }

        $roundTrip = $storage->get($att->storage_key);
        if ($roundTrip !== $pdf) {
            $this->error('Downloaded bytes do not match uploaded bytes.');

            return self::FAILURE;
        }
        $this->info('download bytes match ('.strlen($roundTrip).' bytes)');

        $storage->delete($att->storage_key);
        $attachments->deleteMetadata($att->id);
        $ticket->delete();
        $this->info("cleaned up {$ticket->reference} + object");
        $this->line('Express store.json was not modified. USE_LARAVEL_API remains OFF by default.');

        return self::SUCCESS;
    }
}
