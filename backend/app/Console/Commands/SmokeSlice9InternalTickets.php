<?php

namespace App\Console\Commands;

use App\Services\StoreJsonTicketMirror;
use Illuminate\Console\Command;

/**
 * Phase 9 slice 5: smoke Laravel store.json ticket upsert/soft-delete/draft-delete.
 */
class SmokeSlice9InternalTickets extends Command
{
    protected $signature = 'rms:smoke-slice9-internal-tickets';

    protected $description = 'Smoke Laravel /internal/tickets store.json dual-write';

    public function handle(StoreJsonTicketMirror $mirror): int
    {
        $path = storage_path('app/smoke-slice9-internal-tickets.json');
        file_put_contents($path, json_encode(['riskTickets' => []], JSON_PRETTY_PRINT));
        config(['rms.store_json_path' => $path]);

        $ref = 'RISK-SMOKE-9-5';
        $up = $mirror->upsert([
            'reference' => $ref,
            'title' => 'Slice9 internal ticket',
            'status' => 'draft',
        ]);
        if (($up['ticket']['title'] ?? '') !== 'Slice9 internal ticket') {
            @unlink($path);
            $this->error('upsert did not persist title');

            return self::FAILURE;
        }
        $this->info('upsert OK');

        $del = $mirror->deleteDraft($ref);
        if (($del['reference'] ?? '') !== $ref) {
            @unlink($path);
            $this->error('delete-draft did not remove ticket');

            return self::FAILURE;
        }
        $this->info('delete-draft OK');

        $mirror->upsert(['reference' => $ref, 'title' => 'Keep', 'status' => 'Submitted']);
        $soft = $mirror->softDelete([
            'reference' => $ref,
            'deletionReason' => 'smoke',
            'deletedBy' => 'smoke',
        ]);
        if (empty($soft['ticket']['deleted'])) {
            @unlink($path);
            $this->error('soft-delete did not mark deleted');

            return self::FAILURE;
        }
        $this->info('soft-delete OK');

        @unlink($path);
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
