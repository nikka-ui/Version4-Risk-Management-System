<?php

namespace Tests\Feature;

use App\Models\Accomplishment;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_three(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 9)
            ->assertJsonPath('slice', 6);
    }

    public function test_tickets_require_auth(): void
    {
        $this->getJson('/v1/tickets')->assertUnauthorized();
        $this->getJson('/v1/tickets/RISK-2026-00001')->assertUnauthorized();
    }

    public function test_list_and_show_ticket(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-1',
            'reference' => 'RISK-2026-00001',
            'title' => 'Network outage',
            'status' => 'draft',
            'submitted_by' => 'reporter',
            'submitted_by_name' => 'Reporter',
            'deleted' => false,
            'ownership' => ['state' => 'pending'],
            'ai' => ['riskLevel' => ['id' => 'moderate', 'label' => 'Moderate']],
            'source_created_at' => now(),
            'source_updated_at' => now(),
        ]);

        $token = $this->postJson('/v1/auth/token', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.reference', 'RISK-2026-00001');

        $this->withToken($token)
            ->getJson('/v1/tickets/RISK-2026-00001')
            ->assertOk()
            ->assertJsonPath('ticket.reference', 'RISK-2026-00001')
            ->assertJsonPath('ticket.title', 'Network outage')
            ->assertJsonPath('ticket.ownership.state', 'pending');
    }

    public function test_deleted_tickets_hidden_by_default(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-del',
            'reference' => 'RISK-2026-00099',
            'title' => 'Deleted',
            'status' => 'closed',
            'deleted' => true,
            'source_updated_at' => now(),
        ]);

        $token = $this->postJson('/v1/auth/token', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/v1/tickets')
            ->assertOk()
            ->assertJsonCount(0, 'tickets');

        $this->withToken($token)
            ->getJson('/v1/tickets/RISK-2026-00099')
            ->assertNotFound();
    }

    public function test_accomplishment_endpoint(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-2',
            'reference' => 'RISK-2026-00002',
            'title' => 'Access control',
            'status' => 'pending_audit',
            'accomplishment_external_id' => 'acc-1',
            'deleted' => false,
            'source_updated_at' => now(),
        ]);

        Accomplishment::query()->create([
            'external_id' => 'acc-1',
            'ticket_ref' => 'RISK-2026-00002',
            'ticket_title' => 'Access control',
            'summary' => 'Completed training',
            'outcomes' => 'Policy updated',
            'submitted_by' => 'reporter',
            'evidence' => [],
        ]);

        $token = $this->postJson('/v1/auth/token', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/v1/tickets/RISK-2026-00002/accomplishment')
            ->assertOk()
            ->assertJsonPath('accomplishment.id', 'acc-1')
            ->assertJsonPath('accomplishment.summary', 'Completed training');
    }
}
