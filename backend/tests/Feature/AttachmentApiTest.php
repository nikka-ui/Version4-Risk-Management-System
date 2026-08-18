<?php

namespace Tests\Feature;

use App\Models\RiskAttachment;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $username, string $password): string
    {
        return $this->postJson('/v1/auth/token', compact('username', 'password'))->json('token');
    }

    public function test_health_slice_eight(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_register_list_get_and_sync_attachments(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
            'department' => 'Information Technology',
        ]);

        $token = $this->token('reporter', 'a3c2026');

        $reference = $this->withToken($token)
            ->postJson('/v1/tickets', [
                'title' => 'Attachment slice8',
                'what' => 'Issue',
                'why' => 'Cause',
                'where' => 'HQ',
                'when' => 'Today',
                'who' => 'Team',
                'how' => 'Process',
                'evidenceCount' => 1,
            ])
            ->assertCreated()
            ->json('ticket.reference');

        $this->withToken($token)
            ->postJson("/v1/tickets/{$reference}/attachments", [
                'id' => 'att-slice8-1',
                'originalName' => 'evidence.pdf',
                'mimeType' => 'application/pdf',
                'size' => 1024,
                'storageKey' => "{$reference}/att-slice8-1-evidence.pdf",
            ])
            ->assertCreated()
            ->assertJsonPath('attachment.id', 'att-slice8-1')
            ->assertJsonPath('evidenceCount', 1);

        $this->withToken($token)
            ->getJson("/v1/tickets/{$reference}/attachments")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('attachments.0.originalName', 'evidence.pdf');

        $this->withToken($token)
            ->getJson('/v1/attachments/att-slice8-1')
            ->assertOk()
            ->assertJsonPath('attachment.storageKey', "{$reference}/att-slice8-1-evidence.pdf");

        RiskAttachment::query()->create([
            'id' => 'att-slice8-2',
            'ticket_ref' => $reference,
            'original_name' => 'photo.png',
            'mime_type' => 'image/png',
            'size_bytes' => 2048,
            'storage_key' => "{$reference}/att-slice8-2-photo.png",
            'uploaded_by' => 'reporter',
            'legacy' => false,
            'uploaded_at' => now(),
        ]);

        $this->withToken($token)
            ->postJson("/v1/tickets/{$reference}/attachments/sync")
            ->assertOk()
            ->assertJsonPath('evidenceCount', 2);

        $this->withToken($token)
            ->deleteJson('/v1/attachments/att-slice8-1')
            ->assertOk();

        $this->withToken($token)
            ->getJson("/v1/tickets/{$reference}/attachments")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('evidenceCount', 1);
    }

    public function test_register_requires_original_name(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);
        $token = $this->token('reporter', 'a3c2026');

        $reference = $this->withToken($token)
            ->postJson('/v1/tickets', [
                'title' => 'Missing name',
                'what' => 'Issue',
                'why' => 'Cause',
                'where' => 'HQ',
                'when' => 'Today',
                'who' => 'Team',
                'how' => 'Process',
                'evidenceCount' => 1,
            ])
            ->json('ticket.reference');

        $this->withToken($token)
            ->postJson("/v1/tickets/{$reference}/attachments", [
                'storageKey' => 'x/y',
            ])
            ->assertStatus(422);
    }
}
