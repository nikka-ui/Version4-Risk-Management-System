<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAnalysisReclassifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_twelve_slice_one(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_admin_can_reclassify_ticket_via_api(): void
    {
        Http::fake([
            '*/classify' => Http::response([
                'summary' => 'Reclassified summary',
                'likelihood' => 4,
                'impact' => 4,
                'riskCategory' => 'financial',
                'severity' => 4,
                'riskLevel' => ['id' => 'high', 'label' => 'High'],
                'responsibleDepartment' => 'Finance',
                'priority' => 'high',
                'priorityLabel' => 'High',
                'suggestedMitigation' => 'Review controls',
                'confidence' => 0.88,
                'manualReviewRequired' => false,
                'routingBasis' => 'title_and_incident_details',
                'routingFieldsUsed' => ['title', 'what'],
                'processedAt' => now()->toIso8601String(),
                'source' => 'ai-service',
                'engine' => 'nlp-hybrid-v1',
                'mode' => 'nlp-hybrid',
            ], 200),
        ]);

        config(['rms.ai_service_url' => 'http://ai-service.test:5000']);

        $admin = User::factory()->admin()->create(['role' => Roles::ADMIN]);
        $token = $this->postJson('/v1/auth/token', [
            'username' => $admin->username,
            'password' => 'password',
        ])->json('token');

        RiskTicket::query()->create([
            'external_id' => 'tkt-recl-1',
            'reference' => 'RISK-2026-RECL-1',
            'title' => 'Budget overrun',
            'status' => 'assigned',
            'category' => 'operational',
            'department' => 'Operations',
            'priority' => 'medium',
            'submitted_by' => 'reporter',
            'submitted_by_name' => 'Reporter',
            'likelihood' => 2,
            'impact' => 2,
            'risk_score' => 4,
            'evidence_count' => 1,
            'five_w1h' => ['what' => 'Invoice fraud pattern'],
            'ai' => ['riskCategory' => 'operational', 'summary' => 'Old'],
            'audit_trail' => [],
            'deleted' => false,
            'source_created_at' => now(),
            'source_updated_at' => now(),
        ]);

        $this->withToken($token)
            ->postJson('/v1/tickets/RISK-2026-RECL-1/ai/reclassify')
            ->assertOk()
            ->assertJsonPath('ticketReference', 'RISK-2026-RECL-1')
            ->assertJsonPath('ai.riskCategory', 'financial');

        $ticket = RiskTicket::query()->where('reference', 'RISK-2026-RECL-1')->first();
        $this->assertSame('financial', $ticket->category);
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame('Operations', $ticket->department);
        $this->assertSame('financial', $ticket->ai['riskCategory'] ?? null);
        $this->assertDatabaseHas('ai_analysis_results', [
            'ticket_reference' => 'RISK-2026-RECL-1',
            'risk_category' => 'financial',
        ]);
    }

    public function test_non_admin_cannot_reclassify_via_api(): void
    {
        $user = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'password' => 'a3c2026',
        ]);
        $token = $this->postJson('/v1/auth/token', [
            'username' => $user->username,
            'password' => 'a3c2026',
        ])->json('token');

        RiskTicket::query()->create([
            'external_id' => 'tkt-recl-2',
            'reference' => 'RISK-2026-RECL-2',
            'title' => 'Test',
            'status' => 'draft',
            'submitted_by' => $user->username,
            'submitted_by_name' => $user->name,
            'deleted' => false,
            'source_created_at' => now(),
            'source_updated_at' => now(),
        ]);

        $this->withToken($token)
            ->postJson('/v1/tickets/RISK-2026-RECL-2/ai/reclassify')
            ->assertForbidden();
    }

    public function test_admin_can_reclassify_from_blade_post(): void
    {
        Http::fake([
            '*/classify' => Http::response([
                'summary' => 'Blade reclassify',
                'likelihood' => 3,
                'impact' => 3,
                'riskCategory' => 'compliance',
                'severity' => 3,
                'riskLevel' => ['id' => 'moderate', 'label' => 'Moderate'],
                'responsibleDepartment' => 'Internal Audit',
                'priority' => 'medium',
                'priorityLabel' => 'Medium',
                'suggestedMitigation' => 'Document gap',
                'confidence' => 0.8,
                'manualReviewRequired' => false,
                'source' => 'ai-service',
            ], 200),
        ]);
        config(['rms.ai_service_url' => 'http://ai-service.test:5000']);

        $admin = User::factory()->admin()->create(['role' => Roles::ADMIN]);
        RiskTicket::query()->create([
            'external_id' => 'tkt-recl-3',
            'reference' => 'RISK-2026-RECL-3',
            'title' => 'Policy breach',
            'status' => 'in_progress',
            'category' => 'operational',
            'submitted_by' => 'reporter',
            'submitted_by_name' => 'Reporter',
            'evidence_count' => 1,
            'five_w1h' => ['what' => 'Audit finding on controls'],
            'ai' => ['riskCategory' => 'operational'],
            'audit_trail' => [],
            'deleted' => false,
            'source_created_at' => now(),
            'source_updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/tickets/RISK-2026-RECL-3/reclassify')
            ->assertRedirect('/admin/tickets/RISK-2026-RECL-3?flash=reclassified');

        $this->assertSame('compliance', RiskTicket::query()->where('reference', 'RISK-2026-RECL-3')->value('category'));
        $this->assertGreaterThanOrEqual(1, AiAnalysisResult::query()->where('ticket_reference', 'RISK-2026-RECL-3')->count());
    }
}
