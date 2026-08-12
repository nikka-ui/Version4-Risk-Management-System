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
            ->assertJsonPath('phase', 5)
            ->assertJsonPath('slice', 31);
    }

    public function test_verify_credentials_without_token(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
        ]);

        $this->postJson('/v1/auth/verify', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('user.username', 'reporter')
            ->assertJsonMissingPath('token');

        $this->postJson('/v1/auth/verify', [
            'username' => 'reporter',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_admin_can_sync_user(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'password' => 'a3c1993',
            'role' => Roles::ADMIN,
            'can_manage_users' => true,
        ]);

        $token = $this->postJson('/v1/auth/token', [
            'username' => 'admin',
            'password' => 'a3c1993',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/v1/users/sync', [
                'username' => 'newhire',
                'password' => 'NewHire1!',
                'displayName' => 'New Hire',
                'role' => Roles::SUPERVISOR,
                'email' => 'newhire@rms.local',
            ])
            ->assertOk()
            ->assertJsonPath('user.username', 'newhire');

        $this->postJson('/v1/auth/verify', [
            'username' => 'newhire',
            'password' => 'NewHire1!',
        ])->assertOk();
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
