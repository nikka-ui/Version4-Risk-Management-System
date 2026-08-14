<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 9 slice 1: smoke Blade static assets in Laravel public/.
 */
class SmokeSlice9StaticAssets extends Command
{
    protected $signature = 'rms:smoke-slice9-static-assets';

    protected $description = 'Smoke Laravel public CSS (and images) for Blade consoles';

    public function handle(): int
    {
        $css = public_path('css/app.css');
        if (! is_file($css) || filesize($css) < 1000) {
            $this->error('public/css/app.css missing or too small');

            return self::FAILURE;
        }
        $this->info('public/css/app.css OK');

        $favicon = public_path('img/favicon.png');
        if (! is_file($favicon)) {
            $this->error('public/img/favicon.png missing');

            return self::FAILURE;
        }
        $this->info('public/img/favicon.png OK');

        return self::SUCCESS;
    }
}
