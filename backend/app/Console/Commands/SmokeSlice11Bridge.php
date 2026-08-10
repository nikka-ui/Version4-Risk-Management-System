<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AttachmentService;
use App\Services\DraftTicketService;
use App\Services\ObjectStorageService;
use Illuminate\Console\Command;

/**
 * Smoke-test slice 11 readiness: Laravel upload/download still works end-to-end.
 * Express bridge is flag-gated (USE_LARAVEL_API); this command only hits Laravel.
 */
class SmokeSlice11Bridge extends Command
{
    protected $signature = 'rms:smoke-slice11
                            {--reporter=admin}';

    protected $description = 'Smoke Laravel upload/download used by the Express bridge (flag still OFF by default)';

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
            'title' => 'Slice11 bridge smoke',
            'what' => 'Bridge upload/download',
            'why' => 'Smoke',
            'where' => 'HQ',
            'when' => 'Now',
            'who' => 'Ops',
            'how' => 'Test',
            'evidenceCount' => 1,
        ]);
        $this->info("created {$ticket->reference}");

        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        $att = $attachments->storeRawFile(
            $ticket->reference,
            'slice11-bridge.pdf',
            'application/pdf',
            $pdf,
            $reporter->username,
        );
        $this->info("uploaded {$att->id}");

        $stream = $attachments->openReadStream($att);
        if ($stream === null) {
            $this->error('Could not open read stream (bridge download path would fail).');

            return self::FAILURE;
        }
        $bytes = stream_get_contents($stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if ($bytes !== $pdf) {
            $this->error('Download bytes mismatch.');

            return self::FAILURE;
        }
        $this->info('download bytes match ('.strlen($bytes).' bytes)');

        $storage->delete($att->storage_key);
        $attachments->deleteMetadata($att->id);
        $ticket->delete();
        $this->info("cleaned up {$ticket->reference}");
        $this->line('Express store.json was not modified. USE_LARAVEL_API defaults ON (Phase 5); Express UI remains browser entry.');
        $this->line('Enable with USE_LARAVEL_API=true to route live browser upload/download via Laravel.');

        return self::SUCCESS;
    }
}
