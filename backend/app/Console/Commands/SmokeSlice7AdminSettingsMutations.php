<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdminSettingsController;
use App\Models\User;
use App\Services\AdminSettingsService;
use App\Services\ExpressOrgMirrorService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 4: smoke admin settings Blade mutations.
 */
class SmokeSlice7AdminSettingsMutations extends Command
{
    protected $signature = 'rms:smoke-slice7-admin-settings-mutations';

    protected $description = 'Smoke Laravel admin settings save/reset POSTs';

    public function handle(
        AdminSettingsController $controller,
        AdminSettingsService $settings,
        ExpressOrgMirrorService $mirror,
    ): int {
        $snapshot = $settings->get();
        $admin = User::query()->create([
            'username' => 'smoke_sset_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Settings Mutations',
            'email' => 'smoke_sset_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeSett1!',
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'IT',
            'position' => 'System Administrator',
            'can_manage_users' => true,
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($admin);
        try {
            $updateRequest = Request::create('/admin/settings', 'POST', [
                'landingTagline' => 'Smoke tagline',
                'landingHeadline' => "Smoke Risk\nManagement",
                'organizationName' => 'Smoke Org',
                'defaultRiskLevels' => 'low, high',
                'emailNotifications' => '1',
                'passwordMinLength' => 10,
                'sessionTimeoutMinutes' => 90,
                'mfaEnabled' => '1',
                'maxUploadSizeMb' => 12,
                'allowedFileTypes' => 'pdf, PNG',
                'maintenanceMode' => '1',
                'backupEnabled' => '1',
                'backupFrequency' => 'weekly',
            ]);
            $updateRequest->setUserResolver(fn () => Auth::user());
            $updated = $controller->update($updateRequest);
            $afterUpdate = $settings->get();
            if (
                $afterUpdate['organizationName'] !== 'Smoke Org'
                || $afterUpdate['landingTagline'] !== 'Smoke tagline'
                || $afterUpdate['defaultRiskLevels'] !== ['low', 'high']
                || $afterUpdate['allowedFileTypes'] !== ['pdf', 'png']
                || $afterUpdate['backupFrequency'] !== 'weekly'
                || ! str_contains($updated->getTargetUrl(), 'flash=settings_saved')
            ) {
                $this->error('settings update did not persist');

                return self::FAILURE;
            }
            $this->info('settings update OK');

            $landingRequest = Request::create('/admin/settings/reset-landing', 'POST');
            $landingRequest->setUserResolver(fn () => Auth::user());
            $landing = $controller->resetLanding($landingRequest);
            $afterLanding = $settings->get();
            if (
                $afterLanding['organizationName'] !== 'ACCC'
                || $afterLanding['landingTagline'] !== 'Identify. Assess. Mitigate.'
                || $afterLanding['mfaEnabled'] !== true
                || ! str_contains($landing->getTargetUrl(), 'flash=landing_reset')
            ) {
                $this->error('settings reset-landing did not persist');

                return self::FAILURE;
            }
            $this->info('settings reset-landing OK');

            $aiRequest = Request::create('/admin/settings/reset-ai', 'POST');
            $aiRequest->setUserResolver(fn () => Auth::user());
            $ai = $controller->resetAi($aiRequest);
            $afterAi = $settings->get();
            if (
                $afterAi['defaultRiskLevels'] !== ['low', 'moderate', 'high', 'critical']
                || $afterAi['backupFrequency'] !== 'weekly'
                || ! str_contains($ai->getTargetUrl(), 'flash=ai_reset')
            ) {
                $this->error('settings reset-ai did not persist');

                return self::FAILURE;
            }
            $this->info('settings reset-ai OK');
        } finally {
            Auth::logout();
            $settings->save($snapshot);
            $mirror->syncSettings($snapshot);
            $admin->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
