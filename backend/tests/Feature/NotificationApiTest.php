<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $username, string $password): string
    {
        return $this->postJson('/v1/auth/token', compact('username', 'password'))->json('token');
    }

    public function test_health_slice_nine(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 5)
            ->assertJsonPath('slice', 14);
    }

    public function test_create_list_unread_and_mark_read(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
            'department' => 'Information Technology',
        ]);
        User::factory()->create([
            'username' => 'rmo',
            'password' => 'rmo2026',
            'role' => Roles::RM_OFFICER,
        ]);

        $reporterToken = $this->token('reporter', 'a3c2026');
        $officerToken = $this->token('rmo', 'rmo2026');

        $this->withToken($reporterToken)
            ->postJson('/v1/notifications', [
                'recipientUsername' => 'reporter',
                'type' => 'ticket_update',
                'title' => 'Your ticket moved',
                'message' => 'Now in progress',
                'ticketRef' => 'RISK-2026-00001',
            ])
            ->assertCreated()
            ->assertJsonPath('notification.recipientUsername', 'reporter')
            ->assertJsonPath('notification.read', false);

        $this->withToken($reporterToken)
            ->postJson('/v1/notifications', [
                'recipientRole' => 'rm_officer',
                'type' => 'ticket_submitted',
                'title' => 'New ticket for RMO',
                'message' => 'Please review',
            ])
            ->assertCreated();

        $this->withToken($reporterToken)
            ->getJson('/v1/notifications')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('notifications.0.title', 'Your ticket moved');

        $this->withToken($officerToken)
            ->getJson('/v1/notifications')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('notifications.0.title', 'New ticket for RMO');

        $id = Notification::query()->where('recipient_username', 'reporter')->value('id');

        $this->withToken($reporterToken)
            ->postJson("/v1/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('notification.read', true);

        $this->withToken($reporterToken)
            ->getJson('/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread', 0);
    }

    public function test_mark_all_read_and_report_log(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);
        $token = $this->token('reporter', 'a3c2026');

        foreach (['A', 'B', 'C'] as $n) {
            $this->withToken($token)->postJson('/v1/notifications', [
                'recipientUsername' => 'reporter',
                'type' => 'ping',
                'title' => "Note {$n}",
                'message' => 'x',
            ])->assertCreated();
        }

        $this->withToken($token)
            ->postJson('/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('updated', 3);

        $this->withToken($token)
            ->getJson('/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread', 0);

        $this->withToken($token)
            ->postJson('/v1/report-logs', [
                'ticketRef' => 'RISK-2026-00001',
                'title' => 'Ticket closed',
                'submittedBy' => 'reporter',
                'submitterRole' => 'supervisor',
                'status' => 'Closed',
                'action' => 'ticket_closed',
            ])
            ->assertCreated()
            ->assertJsonPath('reportLog.action', 'ticket_closed');

        $this->withToken($token)
            ->getJson('/v1/report-logs')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_create_requires_recipient(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);
        $token = $this->token('reporter', 'a3c2026');

        $this->withToken($token)
            ->postJson('/v1/notifications', [
                'type' => 'x',
                'title' => 'No recipient',
            ])
            ->assertStatus(422);
    }
}
