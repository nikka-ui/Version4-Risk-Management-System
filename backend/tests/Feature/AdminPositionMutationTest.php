<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPositionMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_seven_slice_two(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 9)
            ->assertJsonPath('slice', 6);
    }

    public function test_guest_cannot_create_position(): void
    {
        $this->post('/admin/positions', [
            'name' => 'Risk Analyst',
        ])->assertRedirect();
    }

    public function test_admin_can_create_update_and_delete_position(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/positions', [
                'name' => 'Risk Analyst',
            ])
            ->assertRedirect();

        $pos = Position::query()->where('name', 'Risk Analyst')->first();
        $this->assertNotNull($pos);
        $this->assertTrue($pos->active);

        $this->actingAs($admin)
            ->post('/admin/positions/'.$pos->external_id.'/edit', [
                'name' => 'Senior Risk Analyst',
            ])
            ->assertRedirect();

        $pos->refresh();
        $this->assertSame('Senior Risk Analyst', $pos->name);

        $this->actingAs($admin)
            ->post('/admin/positions/'.$pos->external_id.'/delete')
            ->assertRedirect();

        $pos->refresh();
        $this->assertFalse($pos->active);
    }

    public function test_non_admin_cannot_create_position(): void
    {
        $user = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
        ]);

        $this->actingAs($user)
            ->post('/admin/positions', [
                'name' => 'Clerk',
            ])
            ->assertRedirect();

        $this->assertNull(Position::query()->where('name', 'Clerk')->first());
    }
}
