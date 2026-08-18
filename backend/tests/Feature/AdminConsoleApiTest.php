<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsoleApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->admin()->create([
            'username' => 'admin',
            'password' => 'a3c2026',
        ]);

        return $this->postJson('/v1/auth/token', [
            'username' => $admin->username,
            'password' => 'a3c2026',
        ])->json('token');
    }

    public function test_admin_can_manage_users_settings_and_read_audit_logs(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)
            ->getJson('/v1/users')
            ->assertOk()
            ->assertJsonStructure(['users', 'roles', 'departments']);

        $this->withToken($token)
            ->postJson('/v1/users', [
                'username' => 'analyst1',
                'displayName' => 'Analyst One',
                'email' => 'analyst1@rms.local',
                'department' => 'IT',
                'position' => 'Analyst',
                'role' => Roles::SUPERVISOR,
                'password' => 'Secret1!',
                'confirmPassword' => 'Secret1!',
            ])
            ->assertCreated()
            ->assertJsonPath('user.username', 'analyst1');

        $this->withToken($token)
            ->patchJson('/v1/users/analyst1', [
                'displayName' => 'Analyst One Updated',
                'position' => 'Senior Analyst',
                'role' => Roles::SUPERVISOR,
            ])
            ->assertOk()
            ->assertJsonPath('user.displayName', 'Analyst One Updated');

        $this->withToken($token)
            ->postJson('/v1/users/analyst1/deactivate')
            ->assertOk()
            ->assertJsonPath('user.active', false);

        $this->withToken($token)
            ->patchJson('/v1/settings', [
                'organizationName' => 'Smoke Org',
                'landingTagline' => 'Smoke tagline',
                'landingHeadline' => 'Headline',
                'defaultRiskLevels' => ['low', 'high'],
                'emailNotifications' => true,
                'passwordMinLength' => 10,
                'sessionTimeoutMinutes' => 60,
                'mfaEnabled' => true,
                'maxUploadSizeMb' => 15,
                'allowedFileTypes' => ['pdf', 'png'],
                'maintenanceMode' => false,
                'backupEnabled' => true,
                'backupFrequency' => 'weekly',
            ])
            ->assertOk()
            ->assertJsonPath('settings.organizationName', 'Smoke Org')
            ->assertJsonPath('settings.mfaEnabled', true);

        $this->withToken($token)
            ->getJson('/v1/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['logs', 'options', 'filters']);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        $token = $this->postJson('/v1/auth/token', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ])->json('token');

        $this->withToken($token)->getJson('/v1/users')->assertForbidden();
        $this->withToken($token)->getJson('/v1/settings')->assertForbidden();
        $this->withToken($token)->getJson('/v1/audit-logs')->assertForbidden();
    }
}
