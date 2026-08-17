<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use Illuminate\Console\Command;

/**
 * Phase 10 slice 1: one-shot import of store.json auditLogs into Postgres.
 */
class ImportAuditLogsFromStore extends Command
{
    protected $signature = 'rms:import-audit-logs {--path= : Override STORE_JSON_PATH}';

    protected $description = 'Import auditLogs from store.json into audit_logs table';

    public function handle(AuditLogService $audits): int
    {
        $path = $this->option('path') ?: config('rms.store_json_path');
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            $this->error('store.json not found at '.$path);

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        $data = $raw ? json_decode($raw, true) : [];
        $rows = is_array($data['auditLogs'] ?? null) ? $data['auditLogs'] : [];
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $audits->record($row);
            $count++;
        }

        $this->info("Imported {$count} audit log(s) from store.json");

        return self::SUCCESS;
    }
}
