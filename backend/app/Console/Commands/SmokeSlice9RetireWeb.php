<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 9 slice 7: smoke Laravel-only edge flags after Express web retirement.
 * Phase 10 slice 3: dual-write may be off; do not require store mirrors.
 */
class SmokeSlice9RetireWeb extends Command
{
    protected $signature = 'rms:smoke-slice9-retire-web';

    protected $description = 'Smoke Laravel-only edge (Express web retired from default stack)';

    public function handle(): int
    {
        if (! (bool) config('rms.edge_ui', false)) {
            $this->error('rms.edge_ui is off');

            return self::FAILURE;
        }
        $this->info('edge_ui OK');

        if (! view()->exists('auth.login')) {
            $this->error('auth.login view missing');

            return self::FAILURE;
        }
        $this->info('auth.login view OK');

        $this->line('store mirrors org='.(config('rms.store_json_org_mirror') ? 'on' : 'off')
            .' tickets='.(config('rms.store_json_ticket_mirror') ? 'on' : 'off'));

        return self::SUCCESS;
    }
}
