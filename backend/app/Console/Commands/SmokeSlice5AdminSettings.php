<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminSettingsService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 21: smoke System Administrator settings Blade + Postgres defaults.
 */
class SmokeSlice5AdminSettings extends Command
{
    protected $signature = 'rms:smoke-slice5-admin-settings';

    protected $description = 'Smoke Laravel admin system settings Blade page';

    public function handle(AdminSettingsService $settings): int
    {
        $username = 'smoke_set_'.bin2hex(random_bytes(3));
        $password = 'SmokeSet1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Admin Settings',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $this->info("created {$username}");

        Auth::login($user);
        $payload = $settings->get();
        if (($payload['organizationName'] ?? '') === '') {
            Auth::logout();
            $user->delete();
            $this->error('settings service missing organizationName');

            return self::FAILURE;
        }

        $html = view('admin.settings', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'settings',
            'title' => 'System Settings',
            'settings' => $payload,
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'System Settings') || ! str_contains($html, 'Save Settings')) {
            Auth::logout();
            $user->delete();
            $this->error('admin settings Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('admin settings Blade OK');

        Auth::logout();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
