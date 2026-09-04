<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHardeningApiTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user, string $password = 'a3c2026'): string
    {
        return $this->postJson('/v1/auth/token', [
            'username' => $user->username,
            'password' => $password,
        ])->json('token');
    }

    public function test_ticket_list_is_scoped_by_role_idor(): void
    {
        $reporterA = User::factory()->create([
            'username' => 'rep-a',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);
        $reporterB = User::factory()->create([
            'username' => 'rep-b',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-a',
            'reference' => 'RISK-HARD-A',
            'title' => 'A',
            'status' => 'assigned',
            'submitted_by' => 'rep-a',
            'department' => 'Information Technology',
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        RiskTicket::query()->create([
            'external_id' => 'tkt-b',
            'reference' => 'RISK-HARD-B',
            'title' => 'B',
            'status' => 'assigned',
            'submitted_by' => 'rep-b',
            'department' => 'Finance',
            'deleted' => false,
            'source_updated_at' => now(),
        ]);

        $tokenA = $this->tokenFor($reporterA);
        $this->withToken($tokenA)
            ->getJson('/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.reference', 'RISK-HARD-A');

        $this->withToken($tokenA)
            ->getJson('/v1/tickets/RISK-HARD-B')
            ->assertNotFound();
    }

    public function test_submit_requires_real_attachment_and_can_pending_ai_review(): void
    {
        config(['rms.ai_auto_route' => false]);

        $reporter = User::factory()->create([
            'username' => 'rep-sub',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);
        $token = $this->tokenFor($reporter);

        $reference = $this->withToken($token)
            ->postJson('/v1/tickets', [
                'title' => 'IT network failure risk',
                'what' => 'Core switch failed',
                'why' => 'No redundancy',
                'where' => 'Data center',
                'when' => 'Morning',
                'who' => 'IT staff',
                'how' => 'Single point of failure',
                'location' => 'HQ',
                'evidenceCount' => 99,
            ])
            ->assertCreated()
            ->assertJsonPath('ticket.evidenceCount', 0)
            ->json('ticket.reference');

        $this->withToken($token)
            ->postJson("/v1/tickets/{$reference}/submit")
            ->assertStatus(422);

        RiskAttachment::query()->create([
            'id' => 'att-hard-1',
            'ticket_ref' => $reference,
            'original_name' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'storage_key' => "{$reference}/evidence.pdf",
            'uploaded_by' => 'rep-sub',
            'legacy' => false,
            'uploaded_at' => now(),
        ]);

        $this->withToken($token)
            ->postJson("/v1/tickets/{$reference}/submit")
            ->assertOk()
            ->assertJsonPath('ticket.status', 'pending_ai_review');
    }

    public function test_high_critical_dept_close_blocked(): void
    {
        $dept = User::factory()->create([
            'username' => 'it-head',
            'password' => 'a3c2026',
            'role' => Roles::DEPT_HEAD,
            'department' => 'Information Technology',
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-hc',
            'reference' => 'RISK-HARD-HC',
            'title' => 'Critical outage',
            'status' => 'pending_audit',
            'submitted_by' => 'rep-a',
            'department' => 'Information Technology',
            'accomplishment_external_id' => 'acc-hc',
            'likelihood' => 5,
            'impact' => 5,
            'ai' => ['riskLevel' => ['id' => 'critical', 'label' => 'Critical']],
            'ownership' => [
                'state' => 'accepted',
                'ownerUsername' => 'it-head',
                'ownerDepartment' => 'Information Technology',
            ],
            'deleted' => false,
            'source_updated_at' => now(),
        ]);

        $this->withToken($this->tokenFor($dept))
            ->postJson('/v1/tickets/RISK-HARD-HC/close', ['closingNotes' => 'done'])
            ->assertStatus(422);
    }

    public function test_attachments_require_ticket_access(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);
        $other = User::factory()->create([
            'username' => 'other',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-att',
            'reference' => 'RISK-HARD-ATT',
            'title' => 'Owned',
            'status' => 'draft',
            'submitted_by' => 'owner',
            'deleted' => false,
            'source_updated_at' => now(),
        ]);
        RiskAttachment::query()->create([
            'id' => 'att-secret',
            'ticket_ref' => 'RISK-HARD-ATT',
            'original_name' => 'secret.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'storage_key' => 'RISK-HARD-ATT/secret.pdf',
            'uploaded_by' => 'owner',
            'legacy' => false,
            'uploaded_at' => now(),
        ]);

        $this->withToken($this->tokenFor($other))
            ->getJson('/v1/tickets/RISK-HARD-ATT/attachments')
            ->assertNotFound();

        $this->withToken($this->tokenFor($other))
            ->getJson('/v1/attachments/att-secret')
            ->assertNotFound();
    }

    public function test_rmo_can_approve_ai_route_when_department_exists(): void
    {
        Department::query()->create([
            'external_id' => 'dept-it',
            'name' => 'Information Technology',
            'code' => 'IT',
            'active' => true,
            'status' => 'active',
        ]);

        $rmo = User::factory()->create([
            'username' => 'rmo-hard',
            'password' => 'a3c2026',
            'role' => Roles::RM_OFFICER,
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-ai',
            'reference' => 'RISK-HARD-AI',
            'title' => 'Pending AI',
            'status' => 'pending_ai_review',
            'submitted_by' => 'rep-a',
            'ai' => [
                'responsibleDepartment' => 'Information Technology',
                'matchedDepartment' => 'Information Technology',
                'priority' => 'medium',
                'confidence' => 0.5,
                'manualReviewRequired' => true,
            ],
            'ownership' => [
                'state' => 'pending_ai_review',
                'recommendedDepartment' => 'Information Technology',
            ],
            'deleted' => false,
            'source_updated_at' => now(),
        ]);

        $this->withToken($this->tokenFor($rmo))
            ->postJson('/v1/tickets/RISK-HARD-AI/ai-route/approve', [
                'department' => 'Information Technology',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'assigned')
            ->assertJsonPath('ticket.department', 'Information Technology');
    }
}
