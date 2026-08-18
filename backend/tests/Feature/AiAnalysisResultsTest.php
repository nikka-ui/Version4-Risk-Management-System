<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Services\AiAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAnalysisResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_thirteen_slice_one(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_analyze_persists_remote_result(): void
    {
        Http::fake([
            '*/classify' => Http::response([
                'summary' => 'Persisted remote',
                'likelihood' => 3,
                'impact' => 4,
                'riskCategory' => 'technological',
                'severity' => 4,
                'riskLevel' => ['id' => 'high', 'label' => 'High'],
                'responsibleDepartment' => 'Information Technology',
                'priority' => 'high',
                'priorityLabel' => 'High',
                'suggestedMitigation' => 'Patch',
                'confidence' => 0.88,
                'manualReviewRequired' => false,
                'routingBasis' => 'title_and_incident_details',
                'routingFieldsUsed' => ['title'],
                'processedAt' => now()->toIso8601String(),
                'source' => 'ai-service',
            ], 200),
        ]);

        config(['rms.ai_service_url' => 'http://ai-service.test:5000']);

        app(AiAnalysisService::class)->analyze([
            'title' => 'Network outage',
            'fiveW1H' => ['what' => 'switch failed'],
            'evidenceCount' => 1,
        ], 'RISK-2026-00999');

        $this->assertDatabaseHas('ai_analysis_results', [
            'ticket_reference' => 'RISK-2026-00999',
            'source' => 'ai-service',
            'risk_category' => 'technological',
        ]);
    }

    public function test_analyze_persists_php_stub_fallback(): void
    {
        Http::fake([
            '*/classify' => Http::response(['status' => 'error'], 503),
        ]);

        config(['rms.ai_service_url' => 'http://ai-service.test:5000']);

        app(AiAnalysisService::class)->analyze([
            'title' => 'Budget fraud risk',
            'fiveW1H' => ['what' => 'finance fraud'],
            'evidenceCount' => 1,
        ], 'RISK-2026-00998');

        $row = AiAnalysisResult::query()->where('ticket_reference', 'RISK-2026-00998')->first();
        $this->assertNotNull($row);
        $this->assertSame('php-stub', $row->source);
        $this->assertNotEmpty($row->risk_category);
    }
}
