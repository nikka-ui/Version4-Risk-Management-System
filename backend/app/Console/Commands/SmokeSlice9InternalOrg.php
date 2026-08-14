<?php

namespace App\Console\Commands;

use App\Services\StoreJsonOrgMirror;
use Illuminate\Console\Command;

/**
 * Phase 9 slice 6: smoke Laravel store.json org department/user/settings dual-write.
 */
class SmokeSlice9InternalOrg extends Command
{
    protected $signature = 'rms:smoke-slice9-internal-org';

    protected $description = 'Smoke Laravel /internal/org store.json dual-write';

    public function handle(StoreJsonOrgMirror $mirror): int
    {
        $path = storage_path('app/smoke-slice9-internal-org.json');
        file_put_contents($path, json_encode([
            'departments' => [],
            'positions' => [],
            'users' => [],
            'systemSettings' => [],
        ], JSON_PRETTY_PRINT));
        config(['rms.store_json_path' => $path]);

        $dept = $mirror->applyDepartment('upsert', [
            'id' => 'dept-smoke-9-6',
            'name' => 'Slice9 Org',
            'code' => 'S96',
        ]);
        if (! empty($dept['error'])) {
            @unlink($path);
            $this->error('department upsert failed: '.$dept['error']);

            return self::FAILURE;
        }
        $this->info('department upsert OK');

        $pos = $mirror->applyPosition('upsert', [
            'id' => 'pos-smoke-9-6',
            'name' => 'Smoke Position',
        ]);
        if (! empty($pos['error'])) {
            @unlink($path);
            $this->error('position upsert failed: '.$pos['error']);

            return self::FAILURE;
        }
        $this->info('position upsert OK');

        $user = $mirror->applyUser('upsert', [
            'username' => 'smoke96',
            'displayName' => 'Smoke 96',
            'role' => 'supervisor',
        ]);
        if (! empty($user['error'])) {
            @unlink($path);
            $this->error('user upsert failed: '.$user['error']);

            return self::FAILURE;
        }
        $this->info('user upsert OK');

        $settings = $mirror->applySettings(['landingTitle' => 'Slice9 org smoke']);
        if (! empty($settings['error'])) {
            @unlink($path);
            $this->error('settings apply failed');

            return self::FAILURE;
        }
        $store = json_decode((string) file_get_contents($path), true);
        if (($store['systemSettings']['landingTitle'] ?? '') !== 'Slice9 org smoke') {
            @unlink($path);
            $this->error('settings did not persist');

            return self::FAILURE;
        }
        $this->info('settings OK');

        @unlink($path);
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
