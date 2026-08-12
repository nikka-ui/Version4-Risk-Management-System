<?php

namespace App\Services;

use App\Support\SystemSettings;

/**
 * Phase 5 slice 21: System Administrator settings from Express store.json.
 * Mutations remain on Express POSTs.
 */
class AdminSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $storePath = (string) config('rms.store_json_path');
        $raw = @file_get_contents($storePath);
        $data = $raw ? json_decode($raw, true) : [];
        $stored = is_array($data['systemSettings'] ?? null) ? $data['systemSettings'] : [];

        return SystemSettings::merge($stored);
    }
}
