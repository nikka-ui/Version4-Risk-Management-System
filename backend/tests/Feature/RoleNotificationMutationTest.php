<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleNotificationMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_eight_slice_six(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_guest_cannot_mark_other_role_notifications_read(): void
    {
        $user = User::factory()->create([
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Information Technology',
        ]);
        $note = Notification::query()->create([
            'id' => 'notif-test-guest',
            'recipient_username' => $user->username,
            'type' => 'ping',
            'title' => 'Unread',
            'message' => 'x',
            'created_at' => now(),
        ]);

        $this->post('/dept/notifications/read-all')->assertRedirect();
        $note->refresh();
        $this->assertNull($note->read_at);
    }

    public function test_each_console_role_can_mark_all_read(): void
    {
        $cases = [
            [Roles::DEPT_HEAD, 'Information Technology', '/dept/notifications/read-all'],
            [Roles::RM_OFFICER, null, '/officer/notifications/read-all'],
            [Roles::EXECUTIVE, null, '/executive/notifications/read-all'],
            [Roles::PRESIDENT, null, '/president/notifications/read-all'],
        ];

        foreach ($cases as [$role, $department, $uri]) {
            $user = User::factory()->create([
                'role' => $role,
                'role_label' => Roles::label($role),
                'department' => $department,
            ]);
            $note = Notification::query()->create([
                'id' => 'notif-test-'.$role,
                'recipient_username' => $user->username,
                'type' => 'ping',
                'title' => 'Unread',
                'message' => 'x',
                'created_at' => now(),
            ]);

            $this->actingAs($user)->post($uri)->assertRedirect();
            $note->refresh();
            $this->assertNotNull($note->read_at, $role);
        }
    }
}
