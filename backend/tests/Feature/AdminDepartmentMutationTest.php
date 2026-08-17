<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDepartmentMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_seven_slice_one(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_guest_cannot_create_department(): void
    {
        $this->post('/admin/departments', [
            'name' => 'Finance',
            'code' => 'FIN',
        ])->assertRedirect();
    }

    public function test_admin_can_create_update_and_delete_department(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/departments', [
                'name' => 'Finance',
                'code' => 'fin',
                'description' => 'Finance ops',
                'head' => '',
                'status' => 'active',
            ])
            ->assertRedirect();

        $dept = Department::query()->where('code', 'FIN')->first();
        $this->assertNotNull($dept);
        $this->assertSame('Finance', $dept->name);

        $this->actingAs($admin)
            ->post('/admin/departments/'.$dept->external_id.'/edit', [
                'name' => 'Finance Division',
                'code' => 'FIN',
                'description' => 'Updated',
                'head' => 'CFO',
                'status' => 'active',
            ])
            ->assertRedirect();

        $dept->refresh();
        $this->assertSame('Finance Division', $dept->name);
        $this->assertSame('CFO', $dept->head);

        $this->actingAs($admin)
            ->post('/admin/departments/'.$dept->external_id.'/delete')
            ->assertRedirect();

        $dept->refresh();
        $this->assertFalse($dept->active);
        $this->assertSame('inactive', $dept->status);
    }

    public function test_non_admin_cannot_create_department(): void
    {
        $user = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
        ]);

        $this->actingAs($user)
            ->post('/admin/departments', [
                'name' => 'Operations',
                'code' => 'OPS',
            ])
            ->assertRedirect();

        $this->assertNull(Department::query()->where('code', 'OPS')->first());
    }
}
