<?php

namespace App\Console\Commands;

use App\Services\AdminSettingsService;
use Illuminate\Console\Command;

/**
 * Phase 10 slice 2: one-shot import of store.json systemSettings into Postgres.
 */
class ImportSettingsFromStore extends Command
{
    protected $signature = 'rms:import-settings {--path= : Override STORE_JSON_PATH}';

    protected $description = 'Import systemSettings from store.json into system_settings table';

    public function handle(AdminSettingsService $settings): int
    {
        $path = $this->option('path') ?: config('rms.store_json_path');
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            $this->error('store.json not found at '.$path);

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        $data = $raw ? json_decode($raw, true) : [];
        $stored = is_array($data['systemSettings'] ?? null) ? $data['systemSettings'] : [];
        if ($stored === []) {
            $this->warn('No systemSettings in store.json; writing defaults to Postgres.');
        }

        $settings->save($stored);
        $this->info('Imported system settings into Postgres from store.json');

        return self::SUCCESS;
    }
}
