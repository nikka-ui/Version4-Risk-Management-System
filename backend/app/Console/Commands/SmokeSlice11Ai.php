<?php

namespace App\Console\Commands;

use App\Services\AiAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Phase 11 slice 5: smoke NLP-hybrid ai-service /classify + taxonomy PHP stub fallback.
 */
class SmokeSlice11Ai extends Command
{
    protected $signature = 'rms:smoke-slice11-ai';

    protected $description = 'Smoke ai-service classify and Laravel AiAnalysisService';

    public function handle(AiAnalysisService $ai): int
    {
        $base = rtrim((string) config('rms.ai_service_url', ''), '/');
        if ($base === '') {
            $this->error('AI_SERVICE_URL / rms.ai_service_url is empty');

            return self::FAILURE;
        }

        try {
            $health = Http::timeout(3)->get($base.'/health');
        } catch (\Throwable $e) {
            $this->error('ai-service health unreachable: '.$e->getMessage());

            return self::FAILURE;
        }
        if (! $health->successful() || ($health->json('mode') ?? '') !== 'nlp-hybrid') {
            $this->error('ai-service health unexpected: '.$health->body());

            return self::FAILURE;
        }
        $this->info('ai-service health OK (nlp-hybrid)');

        $payload = [
            'title' => 'Network outage risk',
            'location' => 'Data center',
            'fiveW1H' => [
                'what' => 'Core switch failed during peak hours',
                'why' => 'Aging hardware without redundancy',
                'where' => 'Rack A',
                'when' => 'Morning',
                'who' => 'IT ops',
                'how' => 'Single point of failure caused outage',
            ],
            'evidenceCount' => 1,
        ];

        $remote = $ai->classifyRemote($payload);
        if (! is_array($remote) || ($remote['source'] ?? '') !== 'ai-service') {
            $this->error('classifyRemote failed');

            return self::FAILURE;
        }
        if (($remote['riskCategory'] ?? '') !== 'operational') {
            $this->error('classify expected operational, got '.($remote['riskCategory'] ?? 'none'));

            return self::FAILURE;
        }
        $dept = (string) ($remote['responsibleDepartment'] ?? '');
        if ($dept !== 'Information Technology' && $dept !== 'IT') {
            $this->error('classify expected IT routing, got '.$dept);

            return self::FAILURE;
        }
        if (trim((string) ($remote['suggestedMitigation'] ?? '')) === '') {
            $this->error('classify missing suggestedMitigation');

            return self::FAILURE;
        }
        if (($remote['engine'] ?? '') !== 'nlp-hybrid-v1') {
            $this->error('classify expected engine nlp-hybrid-v1');

            return self::FAILURE;
        }
        if (! is_array($remote['nlpScores'] ?? null)) {
            $this->error('classify missing nlpScores');

            return self::FAILURE;
        }
        $this->info('classifyRemote OK ('.$remote['riskCategory'].' → '.$dept.')');

        $viaService = $ai->analyze($payload);
        if (($viaService['source'] ?? '') !== 'ai-service') {
            $this->error('analyze did not use ai-service (got '.($viaService['source'] ?? 'none').')');

            return self::FAILURE;
        }
        $this->info('analyze OK via ai-service');

        config(['rms.ai_service_url' => 'http://127.0.0.1:9']);
        $fallback = $ai->analyze($payload);
        if (($fallback['source'] ?? '') !== 'php-stub') {
            $this->error('fallback expected php-stub, got '.($fallback['source'] ?? 'none'));

            return self::FAILURE;
        }
        if (($fallback['riskCategory'] ?? '') !== 'operational') {
            $this->error('fallback expected operational category, got '.($fallback['riskCategory'] ?? 'none'));

            return self::FAILURE;
        }
        if (($fallback['engine'] ?? '') !== 'taxonomy-v1') {
            $this->error('fallback expected taxonomy-v1 engine');

            return self::FAILURE;
        }
        $this->info('PHP stub fallback OK (taxonomy operational)');

        return self::SUCCESS;
    }
}
