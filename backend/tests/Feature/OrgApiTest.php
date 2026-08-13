<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_two(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 8)
            ->assertJsonPath('slice', 3);
    }

    public function test_departments_and_positions_require_auth(): void
    {
        $this->getJson('/v1/departments')->assertUnauthorized();
        $this->getJson('/v1/positions')->assertUnauthorized();
    }

    public function test_list_departments_and_positions(): void
    {
        $user = User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        Department::query()->create([
            'external_id' => 'dept-1',
            'name' => 'Information Technology',
            'code' => 'IT',
            'description' => 'IT',
            'status' => 'active',
            'active' => true,
        ]);

        Position::query()->create([
            'external_id' => 'pos-1',
            'name' => 'Risk Reporter',
            'active' => true,
        ]);

        $token = $this->postJson('/v1/auth/token', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/v1/departments')
            ->assertOk()
            ->assertJsonCount(1, 'departments')
            ->assertJsonPath('departments.0.code', 'IT');

        $this->withToken($token)
            ->getJson('/v1/positions')
            ->assertOk()
            ->assertJsonCount(1, 'positions')
            ->assertJsonPath('positions.0.name', 'Risk Reporter');
    }

    public function test_admin_can_create_department_non_admin_cannot(): void
    {
        Department::query()->create([
            'external_id' => 'dept-seed',
            'name' => 'Administration',
            'code' => 'ADMIN',
            'status' => 'active',
            'active' => true,
        ]);

        User::factory()->create([
            'username' => 'admin',
            'password' => 'a3c1993',
            'role' => Roles::ADMIN,
        ]);

        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        $adminToken = $this->postJson('/v1/auth/token', [
            'username' => 'admin',
            'password' => 'a3c1993',
        ])->json('token');

        $reporterToken = $this->postJson('/v1/auth/token', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ])->json('token');

        $this->withToken($adminToken)
            ->postJson('/v1/departments', [
                'name' => 'Finance',
                'code' => 'FIN',
                'description' => 'Finance ops',
            ])
            ->assertCreated()
            ->assertJsonPath('department.code', 'FIN');

        $this->withToken($reporterToken)
            ->postJson('/v1/departments', [
                'name' => 'Operations',
                'code' => 'OPS',
            ])
            ->assertForbidden();
    }
}
