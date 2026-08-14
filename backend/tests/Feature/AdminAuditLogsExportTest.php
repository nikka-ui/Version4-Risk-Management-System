<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_eight_slice_seven(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 9)
            ->assertJsonPath('slice', 6);
    }

    public function test_guest_cannot_export_audit_csv(): void
    {
        $this->get('/admin/audit-logs/export')->assertRedirect();
    }

    public function test_admin_can_export_audit_csv(): void
    {
        $path = storage_path('app/testing-audit-export-store.json');
        file_put_contents($path, json_encode([
            'auditLogs' => [[
                'at' => '2026-08-14T01:00:00.000Z',
                'username' => 'admin',
                'roleLabel' => 'System Administrator',
                'action' => 'login',
                'module' => 'Auth',
                'description' => 'Signed in "ok"',
                'ip' => '127.0.0.1',
                'device' => 'PC',
                'browser' => 'Chrome',
            ]],
        ], JSON_UNESCAPED_SLASHES));
        config(['rms.store_json_path' => $path]);

        $admin = User::factory()->admin()->create([
            'role' => Roles::ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/admin/audit-logs/export');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $response->assertHeader('content-disposition', 'attachment; filename="audit-logs.csv"');
        $body = (string) $response->getContent();
        $this->assertStringStartsWith('Date,User,Role,Action,Module,Description,IP,Device,Browser', $body);
        $this->assertStringContainsString('"Signed in ""ok"""', $body);
        $this->assertStringContainsString('"admin"', $body);

        @unlink($path);
    }
}
