<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('phase', 3);
    }

    public function test_token_and_me(): void
    {
        $user = User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
        ]);

        $tokenResponse = $this->postJson('/v1/auth/token', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ]);

        $tokenResponse
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['username', 'role']]);

        $token = $tokenResponse->json('token');

        $this->withToken($token)
            ->getJson('/v1/users/me')
            ->assertOk()
            ->assertJsonPath('user.username', 'reporter')
            ->assertJsonPath('user.role', Roles::SUPERVISOR);

        $this->getJson('/v1/users/me')->assertUnauthorized();
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/v1/users/me')->assertUnauthorized();
    }

    public function test_inactive_user_cannot_get_token(): void
    {
        User::factory()->create([
            'username' => 'inactive',
            'password' => 'secret12',
            'active' => false,
            'status' => 'inactive',
        ]);

        $this->postJson('/v1/auth/token', [
            'username' => 'inactive',
            'password' => 'secret12',
        ])->assertStatus(422);
    }
}
