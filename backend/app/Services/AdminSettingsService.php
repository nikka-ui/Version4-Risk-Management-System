<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Support\SystemSettings;

/**
 * Phase 5 slice 21 + Phase 7 slice 4 + Phase 10 slice 2:
 * System Administrator settings (Blade GET + POST).
 * Postgres is the sole live read SoT; store.json dual-write is optional (off by default).
 */
class AdminSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $row = SystemSetting::query()->first();
        if ($row && is_array($row->payload)) {
            return SystemSettings::merge($row->payload);
        }

        return SystemSettings::defaults();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{settings: array<string, mixed>}
     */
    public function update(array $input): array
    {
        $merged = array_merge($this->get(), $this->normalizeForm($input));

        return ['settings' => $this->save($merged)];
    }

    /**
     * @return array{settings: array<string, mixed>}
     */
    public function resetLanding(): array
    {
        $defaults = SystemSettings::defaults();
        $merged = array_merge($this->get(), [
            'landingTagline' => $defaults['landingTagline'],
            'landingHeadline' => $defaults['landingHeadline'],
            'organizationName' => $defaults['organizationName'],
        ]);

        return ['settings' => $this->save($merged)];
    }

    /**
     * @return array{settings: array<string, mixed>}
     */
    public function resetAi(): array
    {
        $defaults = SystemSettings::defaults();
        $merged = array_merge($this->get(), [
            'defaultRiskLevels' => $defaults['defaultRiskLevels'],
        ]);

        return ['settings' => $this->save($merged)];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function save(array $settings): array
    {
        $merged = SystemSettings::merge($settings);
        $row = SystemSetting::query()->first();
        if ($row) {
            $row->payload = $merged;
            $row->save();
        } else {
            SystemSetting::query()->create(['payload' => $merged]);
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeForm(array $input): array
    {
        $riskLevels = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($input['defaultRiskLevels'] ?? '')),
        ), fn (string $v) => $v !== ''));
        $fileTypes = array_values(array_filter(array_map(
            fn (string $s) => strtolower(trim($s)),
            explode(',', (string) ($input['allowedFileTypes'] ?? '')),
        ), fn (string $v) => $v !== ''));
        $freq = (string) ($input['backupFrequency'] ?? 'daily');
        if (! in_array($freq, ['daily', 'weekly'], true)) {
            $freq = 'daily';
        }
        $passwordMin = (int) ($input['passwordMinLength'] ?? 0);
        $sessionTimeout = (int) ($input['sessionTimeoutMinutes'] ?? 0);
        $maxUpload = (int) ($input['maxUploadSizeMb'] ?? 0);

        return [
            'landingTagline' => mb_substr(trim((string) ($input['landingTagline'] ?? '')), 0, 120),
            'landingHeadline' => mb_substr(trim(str_replace("\r\n", "\n", (string) ($input['landingHeadline'] ?? ''))), 0, 200),
            'organizationName' => mb_substr(trim((string) ($input['organizationName'] ?? '')), 0, 80),
            'defaultRiskLevels' => $riskLevels,
            'emailNotifications' => ($input['emailNotifications'] ?? '') === '1',
            'passwordMinLength' => $passwordMin > 0 ? $passwordMin : 8,
            'sessionTimeoutMinutes' => $sessionTimeout > 0 ? $sessionTimeout : 480,
            'mfaEnabled' => ($input['mfaEnabled'] ?? '') === '1',
            'maxUploadSizeMb' => $maxUpload > 0 ? $maxUpload : 25,
            'allowedFileTypes' => $fileTypes,
            'maintenanceMode' => ($input['maintenanceMode'] ?? '') === '1',
            'backupEnabled' => ($input['backupEnabled'] ?? '') === '1',
            'backupFrequency' => $freq,
        ];
    }
}
