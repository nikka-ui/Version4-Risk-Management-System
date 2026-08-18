<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPostgresTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_ten_slice_one(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_record_and_list_from_postgres(): void
    {
        app(AuditLogService::class)->record([
            'username' => 'admin',
            'action' => 'user_created',
            'module' => 'User Management',
            'description' => 'Added user: smoke',
            'roleLabel' => 'System Administrator',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'username' => 'admin',
            'action' => 'user_created',
            'description' => 'Added user: smoke',
        ]);

        $admin = User::factory()->admin()->create(['role' => Roles::ADMIN]);
        $this->actingAs($admin)
            ->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('Added user: smoke', false);
    }

    public function test_dashboard_recent_audit_uses_postgres(): void
    {
        AuditLog::query()->create([
            'id' => 'alog-dash-1',
            'occurred_at' => now(),
            'username' => 'admin',
            'action' => 'settings_updated',
            'module' => 'Settings',
            'description' => 'Dashboard smoke audit',
            'role_label' => 'System Administrator',
        ]);

        $admin = User::factory()->admin()->create(['role' => Roles::ADMIN]);
        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard smoke audit', false);
    }
}
