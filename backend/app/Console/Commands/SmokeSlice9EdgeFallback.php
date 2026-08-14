<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 9 slice 4: smoke Laravel public fallback files for unmatched edge paths.
 */
class SmokeSlice9EdgeFallback extends Command
{
    protected $signature = 'rms:smoke-slice9-edge-fallback';

    protected $description = 'Smoke Laravel public favicon/robots for unmatched edge fallback';

    public function handle(): int
    {
        $favicon = public_path('favicon.ico');
        if (! is_file($favicon) || filesize($favicon) < 100) {
            $this->error('public/favicon.ico missing or too small');

            return self::FAILURE;
        }
        $this->info('public/favicon.ico OK');

        $robots = public_path('robots.txt');
        if (! is_file($robots)) {
            $this->error('public/robots.txt missing');

            return self::FAILURE;
        }
        $this->info('public/robots.txt OK');

        return self::SUCCESS;
    }
}
