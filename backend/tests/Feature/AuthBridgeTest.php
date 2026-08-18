<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoginBridgeService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_nine_slice_two(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_guest_without_code_is_sent_to_login(): void
    {
        $this->get('/auth/bridge')
            ->assertRedirect('/login?error=auth_unavailable');
    }

    public function test_valid_code_logs_in_and_redirects(): void
    {
        $user = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
        ]);
        $code = app(LoginBridgeService::class)->issueCode($user);

        $this->get('/auth/bridge?code='.$code.'&next=/supervisor/tickets')
            ->assertRedirect('/supervisor/tickets');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_code_without_session_is_sent_to_login(): void
    {
        $this->get('/auth/bridge?code=not-a-real-code')
            ->assertRedirect('/login?error=auth_unavailable');
    }
}
