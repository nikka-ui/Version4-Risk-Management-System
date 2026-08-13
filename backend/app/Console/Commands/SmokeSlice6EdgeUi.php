<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 6 slice 2: smoke unprefixed Blade login page.
 */
class SmokeSlice6EdgeUi extends Command
{
    protected $signature = 'rms:smoke-slice6-edge-ui';

    protected $description = 'Smoke unprefixed Blade login UI for Phase 6 slice 2';

    public function handle(): int
    {
        $html = view('auth.login', [
            'error' => null,
            'next' => '',
            'username' => '',
        ])->render();

        if (! str_contains($html, 'Sign In') || ! str_contains($html, 'action="/login"')) {
            $this->error('login Blade missing Sign In or unprefixed /login form action');

            return self::FAILURE;
        }
        $this->info('unprefixed /login Blade OK');

        $adminNav = view('layouts.admin', [
            'user' => [
                'username' => 'smoke_admin',
                'displayName' => 'Smoke Admin',
                'roleLabel' => 'System Administrator',
                'position' => 'System Administrator',
            ],
            'title' => 'Dashboard',
            'activeNav' => 'dashboard',
            'stats' => [],
        ])->render();

        if (! str_contains($adminNav, 'href="/admin"') || str_contains($adminNav, '/laravel/admin')) {
            $this->error('admin layout still uses /laravel prefix');

            return self::FAILURE;
        }
        $this->info('unprefixed /admin nav OK');

        return self::SUCCESS;
    }
}
