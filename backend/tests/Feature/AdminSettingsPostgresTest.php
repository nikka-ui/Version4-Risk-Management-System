<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AdminSettingsService;
use App\Support\Roles;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsPostgresTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_ten_slice_two(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_get_uses_defaults_when_no_row(): void
    {
        $this->assertNull(SystemSetting::query()->first());

        $payload = app(AdminSettingsService::class)->get();

        $this->assertSame(SystemSettings::defaults()['organizationName'], $payload['organizationName']);
        $this->assertSame(SystemSettings::defaults()['landingTagline'], $payload['landingTagline']);
    }

    public function test_admin_settings_page_reads_postgres(): void
    {
        SystemSetting::query()->create([
            'payload' => SystemSettings::merge([
                'organizationName' => 'Postgres SoT Org',
                'landingTagline' => 'Postgres settings smoke',
            ]),
        ]);

        $admin = User::factory()->admin()->create(['role' => Roles::ADMIN]);
        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Postgres SoT Org', false)
            ->assertSee('Postgres settings smoke', false);
    }
}
