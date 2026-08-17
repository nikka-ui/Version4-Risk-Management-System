<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AdminSettingsService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 10 slice 2: smoke Postgres settings read/write (no store.json live read).
 */
class SmokeSlice10Settings extends Command
{
    protected $signature = 'rms:smoke-slice10-settings';

    protected $description = 'Smoke Postgres system_settings for admin settings UI';

    public function handle(AdminSettingsService $settings): int
    {
        $existing = SystemSetting::query()->first();
        $backup = $existing && is_array($existing->payload) ? $existing->payload : null;

        if ($existing) {
            $existing->delete();
        }

        $defaults = $settings->get();
        if (($defaults['organizationName'] ?? '') === '') {
            $this->restoreSettings($settings, $backup);
            $this->error('defaults missing organizationName');

            return self::FAILURE;
        }
        $this->info('defaults OK (no Postgres row)');

        $username = 'smoke_s10_'.bin2hex(random_bytes(3));
        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Settings 10',
            'email' => "{$username}@rms.local",
            'password' => 'SmokeSet10!',
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($user);
        $saved = $settings->save(array_merge($defaults, [
            'organizationName' => 'Slice10 Smoke Org',
            'landingTagline' => 'Slice10 settings smoke',
        ]));
        if (($saved['organizationName'] ?? '') !== 'Slice10 Smoke Org') {
            Auth::logout();
            $user->delete();
            $this->restoreSettings($settings, $backup);
            $this->error('save did not persist organizationName');

            return self::FAILURE;
        }
        $this->info('settings write OK');

        $again = $settings->get();
        if (($again['organizationName'] ?? '') !== 'Slice10 Smoke Org') {
            Auth::logout();
            $user->delete();
            $this->restoreSettings($settings, $backup);
            $this->error('get did not return Postgres row');

            return self::FAILURE;
        }
        $this->info('settings read OK');

        Auth::logout();
        $user->delete();
        $this->restoreSettings($settings, $backup);
        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>|null  $backup
     */
    private function restoreSettings(AdminSettingsService $settings, ?array $backup): void
    {
        SystemSetting::query()->delete();
        if ($backup !== null) {
            $settings->save($backup);
        }
    }
}
