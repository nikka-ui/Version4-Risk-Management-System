<?php

namespace Tests\Feature;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftTicketApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function draftPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Network outage risk',
            'what' => 'Core switch failed during peak hours',
            'why' => 'Aging hardware without redundancy',
            'where' => 'Data center rack A',
            'when' => '2026-08-07 morning',
            'who' => 'IT operations staff',
            'how' => 'Single point of failure caused outage',
            'location' => 'Head office',
            'mitigationApproach' => 'Add redundant switch',
            'evidenceCount' => 1,
        ], $overrides);
    }

    private function tokenFor(string $username, string $password): string
    {
        return $this->postJson('/v1/auth/token', [
            'username' => $username,
            'password' => $password,
        ])->json('token');
    }

    public function test_health_reports_slice_two(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 5)
            ->assertJsonPath('slice', 14);
    }

    public function test_create_update_delete_draft(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
            'department' => 'Information Technology',
            'name' => 'Risk Reporter',
        ]);

        $token = $this->tokenFor('reporter', 'a3c2026');

        $create = $this->withToken($token)
            ->postJson('/v1/tickets', $this->draftPayload())
            ->assertCreated()
            ->assertJsonPath('ticket.status', 'draft')
            ->assertJsonPath('ticket.submittedBy', 'reporter');

        $reference = $create->json('ticket.reference');
        $this->assertNotEmpty($reference);
        $this->assertStringStartsWith('RISK-'.date('Y').'-', $reference);

        $this->withToken($token)
            ->patchJson('/v1/tickets/'.$reference, $this->draftPayload([
                'title' => 'Updated network outage risk',
                'evidenceCount' => 2,
            ]))
            ->assertOk()
            ->assertJsonPath('ticket.title', 'Updated network outage risk')
            ->assertJsonPath('ticket.evidenceCount', 2);

        $this->withToken($token)
            ->deleteJson('/v1/tickets/'.$reference)
            ->assertOk()
            ->assertJsonPath('reference', $reference);

        $this->assertDatabaseMissing('risk_tickets', ['reference' => $reference]);
    }

    public function test_create_requires_evidence_and_five_w1h(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        $token = $this->tokenFor('reporter', 'a3c2026');

        $this->withToken($token)
            ->postJson('/v1/tickets', $this->draftPayload(['evidenceCount' => 0]))
            ->assertStatus(422);

        $this->withToken($token)
            ->postJson('/v1/tickets', $this->draftPayload(['what' => '']))
            ->assertStatus(422);
    }

    public function test_cannot_edit_another_users_or_non_draft(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);
        User::factory()->create([
            'username' => 'other',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-other',
            'reference' => 'RISK-2026-00999',
            'title' => 'Other draft',
            'status' => 'draft',
            'submitted_by' => 'other',
            'evidence_count' => 1,
            'deleted' => false,
            'five_w1h' => [
                'what' => 'a', 'why' => 'b', 'where' => 'c', 'when' => 'd', 'who' => 'e', 'how' => 'f',
            ],
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-assigned',
            'reference' => 'RISK-2026-00998',
            'title' => 'Assigned ticket',
            'status' => 'assigned',
            'submitted_by' => 'reporter',
            'evidence_count' => 1,
            'deleted' => false,
        ]);

        $token = $this->tokenFor('reporter', 'a3c2026');

        $this->withToken($token)
            ->patchJson('/v1/tickets/RISK-2026-00999', $this->draftPayload())
            ->assertNotFound();

        $this->withToken($token)
            ->patchJson('/v1/tickets/RISK-2026-00998', $this->draftPayload())
            ->assertStatus(422);
    }
}
