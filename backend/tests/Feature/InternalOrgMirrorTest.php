<?php

namespace Tests\Feature;

use App\Services\StoreJsonOrgMirror;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalOrgMirrorTest extends TestCase
{
    use RefreshDatabase;

    private string $storePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storePath = storage_path('app/testing-internal-org.json');
        file_put_contents($this->storePath, json_encode([
            'departments' => [],
            'positions' => [],
            'users' => [],
            'systemSettings' => [],
        ], JSON_PRETTY_PRINT));
        config([
            'rms.store_json_path' => $this->storePath,
            'rms.internal_service_token' => 'test-internal-token',
            'rms.store_json_org_mirror' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->storePath)) {
            unlink($this->storePath);
        }
        parent::tearDown();
    }

    public function test_health_reports_phase_nine_slice_six(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 9)
            ->assertJsonPath('slice', 6);
    }

    public function test_guest_users_without_token_is_unauthorized(): void
    {
        $this->postJson('/internal/org/users', ['op' => 'upsert', 'user' => ['username' => 'x']])
            ->assertUnauthorized();
    }

    public function test_upsert_department_with_token_writes_store_json(): void
    {
        $this->withHeaders(['X-RMS-Service-Token' => 'test-internal-token'])
            ->postJson('/internal/org/departments', [
                'op' => 'upsert',
                'department' => [
                    'id' => 'dept-smoke',
                    'name' => 'Smoke Dept',
                    'code' => 'SMK',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $store = json_decode((string) file_get_contents($this->storePath), true);
        $this->assertSame('Smoke Dept', $store['departments'][0]['name'] ?? null);
        $this->assertSame('SMK', $store['departments'][0]['code'] ?? null);
    }

    public function test_mirror_user_round_trip(): void
    {
        $result = app(StoreJsonOrgMirror::class)->applyUser('upsert', [
            'username' => 'smokeuser',
            'displayName' => 'Smoke User',
            'role' => 'supervisor',
        ]);
        $this->assertEmpty($result['error'] ?? null);
        $store = json_decode((string) file_get_contents($this->storePath), true);
        $this->assertSame('smokeuser', $store['users'][0]['username'] ?? null);
    }
}
