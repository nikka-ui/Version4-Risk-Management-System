<?php

namespace App\Services;

use App\Models\ReportLog;
use Illuminate\Support\Carbon;

/**
 * Phase 3 slice 9: report-log mirror of Express store.reportLogs (append-only).
 */
class ReportLogService
{
    public function append(array $input): ReportLog
    {
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') {
            $id = 'rpt-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3));
        }

        $createdAt = now();
        if (! empty($input['at'])) {
            try {
                $createdAt = Carbon::parse((string) $input['at']);
            } catch (\Throwable) {
                $createdAt = now();
            }
        }

        return ReportLog::query()->updateOrCreate(
            ['id' => $id],
            [
                'ticket_ref' => trim((string) ($input['ticketRef'] ?? '')) ?: null,
                'title' => trim((string) ($input['title'] ?? '')) ?: null,
                'submitted_by' => trim((string) ($input['submittedBy'] ?? '')) ?: null,
                'submitter_role' => trim((string) ($input['submitterRole'] ?? '')) ?: null,
                'status' => trim((string) ($input['status'] ?? '')) ?: null,
                'action' => trim((string) ($input['action'] ?? '')) ?: null,
                'detail' => trim((string) ($input['detail'] ?? '')) ?: null,
                'created_at' => $createdAt,
            ],
        )->fresh();
    }

    /**
     * @return list<ReportLog>
     */
    public function list(int $limit = 200): array
    {
        $limit = max(1, min($limit, 500));

        return ReportLog::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
