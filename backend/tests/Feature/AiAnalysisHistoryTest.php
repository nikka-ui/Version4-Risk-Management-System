<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAnalysisHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_twelve_slice_one(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_guest_cannot_view_admin_ai_history(): void
    {
        $this->get('/admin/ai-analysis')->assertRedirect();
    }

    public function test_admin_can_view_ai_history_page(): void
    {
        AiAnalysisResult::query()->create([
            'ticket_reference' => 'RISK-2026-00997',
            'source' => 'ai-service',
            'risk_category' => 'technological',
            'likelihood' => 4,
            'impact' => 4,
            'severity' => 4,
            'confidence' => 0.9,
            'responsible_department' => 'Information Technology',
            'priority' => 'high',
            'input' => ['title' => 'History page smoke'],
            'result' => ['summary' => 'History page smoke summary', 'source' => 'ai-service'],
        ]);

        $admin = User::factory()->admin()->create(['role' => Roles::ADMIN]);
        $this->actingAs($admin)
            ->get('/admin/ai-analysis')
            ->assertOk()
            ->assertSee('AI Analysis History', false)
            ->assertSee('History page smoke summary', false)
            ->assertSee('RISK-2026-00997', false);
    }

    public function test_ticket_ai_history_api_requires_auth(): void
    {
        $this->getJson('/v1/tickets/RISK-2026-00996/ai-analysis')->assertUnauthorized();
    }

    public function test_ticket_ai_history_api_lists_runs(): void
    {
        $user = User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-aih-1',
            'reference' => 'RISK-2026-00996',
            'title' => 'AI history ticket',
            'status' => 'draft',
            'submitted_by' => 'reporter',
            'submitted_by_name' => 'Reporter',
            'deleted' => false,
            'source_created_at' => now(),
            'source_updated_at' => now(),
        ]);

        AiAnalysisResult::query()->create([
            'ticket_reference' => 'RISK-2026-00996',
            'source' => 'php-stub',
            'risk_category' => 'operational',
            'likelihood' => 2,
            'impact' => 3,
            'severity' => 3,
            'confidence' => 0.8,
            'responsible_department' => 'Operations',
            'priority' => 'medium',
            'input' => ['title' => 'AI history ticket'],
            'result' => ['summary' => 'API history summary', 'source' => 'php-stub'],
        ]);

        $token = $this->postJson('/v1/auth/token', [
            'username' => $user->username,
            'password' => 'a3c2026',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/v1/tickets/RISK-2026-00996/ai-analysis')
            ->assertOk()
            ->assertJsonPath('ticketReference', 'RISK-2026-00996')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('runs.0.summary', 'API history summary')
            ->assertJsonPath('runs.0.source', 'php-stub');
    }

    public function test_admin_ticket_detail_shows_ai_history(): void
    {
        $admin = User::factory()->admin()->create(['role' => Roles::ADMIN]);

        RiskTicket::query()->create([
            'external_id' => 'tkt-aih-detail',
            'reference' => 'RISK-2026-00995',
            'title' => 'Detail AI history',
            'status' => 'draft',
            'submitted_by' => 'reporter',
            'submitted_by_name' => 'Reporter',
            'deleted' => false,
            'source_created_at' => now(),
            'source_updated_at' => now(),
        ]);

        AiAnalysisResult::query()->create([
            'ticket_reference' => 'RISK-2026-00995',
            'source' => 'ai-service',
            'risk_category' => 'financial',
            'likelihood' => 3,
            'impact' => 3,
            'severity' => 3,
            'confidence' => 0.77,
            'responsible_department' => 'Finance',
            'priority' => 'medium',
            'input' => ['title' => 'Detail AI history'],
            'result' => ['summary' => 'Detail strip run', 'source' => 'ai-service'],
        ]);

        $this->actingAs($admin)
            ->get('/admin/tickets/RISK-2026-00995')
            ->assertOk()
            ->assertSee('AI classify history', false)
            ->assertSee('ai-service', false)
            ->assertSee('financial', false);
    }
}
