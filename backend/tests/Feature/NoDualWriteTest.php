<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ExpressOrgMirrorService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NoDualWriteTest extends TestCase
{
    use RefreshDatabase;

    private string $storePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storePath = storage_path('app/testing-no-dual-write.json');
        file_put_contents($this->storePath, json_encode([
            'departments' => [],
            'auditLogs' => [],
        ], JSON_PRETTY_PRINT));
        config([
            'rms.store_json_path' => $this->storePath,
            'rms.store_json_org_mirror' => false,
            'rms.store_json_ticket_mirror' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->storePath)) {
            unlink($this->storePath);
        }
        parent::tearDown();
    }

    public function test_health_reports_phase_ten_slice_three(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_org_sync_writes_audit_without_touching_store(): void
    {
        $before = File::hash($this->storePath);

        app(ExpressOrgMirrorService::class)->syncDepartment('upsert', [
            'id' => 'dept-ndw',
            'name' => 'No Dual Write',
            'code' => 'NDW',
        ], [
            'username' => 'admin',
            'action' => 'department_created',
            'module' => 'Department Management',
            'description' => 'No dual-write audit',
            'roleLabel' => 'System Administrator',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'description' => 'No dual-write audit',
            'action' => 'department_created',
        ]);
        $this->assertSame($before, File::hash($this->storePath));
    }

    public function test_admin_department_create_audits_with_dual_write_off(): void
    {
        $admin = User::factory()->admin()->create(['role' => Roles::ADMIN]);
        $before = File::hash($this->storePath);

        $this->actingAs($admin)
            ->post('/admin/departments', [
                'name' => 'Audit Only Dept',
                'code' => 'AOD',
                'description' => 'Postgres only',
                'head' => '',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertTrue(
            AuditLog::query()->where('description', 'like', 'Added department: Audit Only Dept%')->exists()
            || AuditLog::query()->where('action', 'department_created')->exists()
        );
        $this->assertSame($before, File::hash($this->storePath));
    }
}
