<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_nine_slice_three(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_guest_get_logout_redirects_to_login(): void
    {
        $this->get('/logout')
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_authenticated_get_logout_clears_session(): void
    {
        $user = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
        ]);

        $this->actingAs($user)
            ->get('/logout')
            ->assertRedirect('/login');
        $this->assertGuest();
    }
}
