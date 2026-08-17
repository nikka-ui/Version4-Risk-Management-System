<?php

namespace Tests\Feature;

use App\Services\AiAnalysisService;
use App\Support\DraftAiAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_twelve_slice_one(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_analyze_uses_remote_classify_when_available(): void
    {
        Http::fake([
            '*/classify' => Http::response([
                'summary' => 'Remote summary',
                'likelihood' => 4,
                'impact' => 4,
                'riskCategory' => 'operational',
                'severity' => 4,
                'riskLevel' => ['id' => 'high', 'label' => 'High'],
                'responsibleDepartment' => 'Information Technology',
                'priority' => 'high',
                'priorityLabel' => 'High',
                'suggestedMitigation' => 'Patch',
                'confidence' => 0.9,
                'manualReviewRequired' => false,
                'routingBasis' => 'title_and_incident_details',
                'routingFieldsUsed' => ['title'],
                'processedAt' => now()->toIso8601String(),
                'source' => 'ai-service',
                'engine' => 'nlp-hybrid-v1',
                'mode' => 'nlp-hybrid',
            ], 200),
        ]);

        config(['rms.ai_service_url' => 'http://ai-service.test:5000']);

        $result = app(AiAnalysisService::class)->analyze([
            'title' => 'Network outage',
            'fiveW1H' => ['what' => 'switch failed'],
            'evidenceCount' => 1,
        ]);

        $this->assertSame('ai-service', $result['source']);
        $this->assertSame('operational', $result['riskCategory']);
        $this->assertSame('Remote summary', $result['summary']);
        $this->assertDatabaseHas('ai_analysis_results', [
            'source' => 'ai-service',
            'risk_category' => 'operational',
        ]);
    }

    public function test_analyze_falls_back_to_taxonomy_php_stub_on_failure(): void
    {
        Http::fake([
            '*/classify' => Http::response(['status' => 'error'], 503),
        ]);

        config(['rms.ai_service_url' => 'http://ai-service.test:5000']);

        $result = app(AiAnalysisService::class)->analyze([
            'title' => 'Budget fraud risk',
            'fiveW1H' => ['what' => 'finance fraud pattern'],
            'evidenceCount' => 1,
        ]);

        $this->assertSame('php-stub', $result['source']);
        $this->assertSame('financial', $result['riskCategory']);
        $this->assertSame('taxonomy-v1', $result['engine'] ?? null);
        $this->assertSame(
            DraftAiAnalysis::analyze([
                'title' => 'Budget fraud risk',
                'fiveW1H' => ['what' => 'finance fraud pattern'],
                'evidenceCount' => 1,
            ])['riskCategory'],
            $result['riskCategory'],
        );
        $this->assertDatabaseHas('ai_analysis_results', [
            'source' => 'php-stub',
        ]);
    }
}
