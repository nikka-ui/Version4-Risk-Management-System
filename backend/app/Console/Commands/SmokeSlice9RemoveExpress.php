<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 9 slice 8: smoke Laravel-only stack after Express source removal.
 */
class SmokeSlice9RemoveExpress extends Command
{
    protected $signature = 'rms:smoke-slice9-remove-express';

    protected $description = 'Smoke Laravel-only stack (Express web source removed)';

    public function handle(): int
    {
        if (! (bool) config('rms.edge_ui', false)) {
            $this->error('rms.edge_ui is off');

            return self::FAILURE;
        }
        $this->info('edge_ui OK');

        $path = (string) config('rms.store_json_path', '');
        if ($path === '') {
            $this->error('store_json_path is empty');

            return self::FAILURE;
        }
        $this->info('store_json_path OK');

        if (! view()->exists('auth.login')) {
            $this->error('auth.login view missing');

            return self::FAILURE;
        }
        $this->info('auth.login view OK');

        if (! class_exists(\App\Support\Roles::class)) {
            $this->error('Roles registry missing');

            return self::FAILURE;
        }
        $this->info('Roles registry OK');

        return self::SUCCESS;
    }
}
