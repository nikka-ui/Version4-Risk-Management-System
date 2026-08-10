<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AttachmentService;
use App\Services\DraftTicketService;
use App\Services\ObjectStorageService;
use App\Services\ReportLogService;
use App\Services\ThreadCommentService;
use App\Support\Roles;
use Illuminate\Console\Command;

/**
 * Phase 4 soak smoke: end-to-end Laravel APIs the Express bridge relies on.
 * Safe to run anytime; does not flip USE_LARAVEL_API and cleans up after itself.
 */
class SmokeSoak extends Command
{
    protected $signature = 'rms:smoke-soak
                            {--reporter=admin}';

    protected $description = 'Phase 4 soak smoke: draft + attachment upload/download + report log (Laravel only)';

    public function handle(
        DraftTicketService $drafts,
        AttachmentService $attachments,
        ObjectStorageService $storage,
        ReportLogService $reportLogs,
        ThreadCommentService $comments,
    ): int {
        $reporterName = (string) $this->option('reporter');
        $reporter = User::query()->where('username', $reporterName)->first();
        if (! $reporter) {
            $this->error("Reporter not found: {$reporterName}. Run rms:soak-prepare first.");

            return self::FAILURE;
        }

        $ticket = $drafts->create($reporter, [
            'title' => 'Phase4 soak smoke',
            'what' => 'Soak check',
            'why' => 'Parity',
            'where' => 'HQ',
            'when' => 'Now',
            'who' => 'Ops',
            'how' => 'Test',
            'evidenceCount' => 1,
        ]);
        $this->info("draft {$ticket->reference}");

        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        $att = $attachments->storeRawFile(
            $ticket->reference,
            'phase4-soak.pdf',
            'application/pdf',
            $pdf,
            $reporter->username,
        );
        $this->info("attachment {$att->id}");

        $roundTrip = $storage->get($att->storage_key);
        if ($roundTrip !== $pdf) {
            $this->error('Attachment round-trip failed.');

            return self::FAILURE;
        }
        $this->info('attachment bytes OK');

        $ticket = $comments->add($ticket, $reporter, [
            'body' => 'Phase 4 soak thread comment',
        ]);
        $this->info('thread comment OK');

        $log = $reportLogs->append([
            'ticketRef' => $ticket->reference,
            'title' => $ticket->title,
            'submittedBy' => $reporter->username,
            'submitterRole' => $reporter->role ?: Roles::SUPERVISOR,
            'status' => $ticket->status,
            'action' => 'soak_smoke',
            'detail' => 'Phase 4 soak smoke',
        ]);
        $this->info("report log {$log->id}");

        $storage->delete($att->storage_key);
        $attachments->deleteMetadata($att->id);
        \App\Models\ReportLog::query()->where('id', $log->id)->delete();
        $ticket->delete();
        $this->info('cleaned up soak smoke rows');
        $this->line('Express store.json was not modified. Dual-write is ON by default; use compose.soak.yml to opt out.');

        return self::SUCCESS;
    }
}
