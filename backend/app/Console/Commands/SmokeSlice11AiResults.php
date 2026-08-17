<?php

namespace App\Console\Commands;

use App\Models\AiAnalysisResult;
use App\Services\AiAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Phase 11 slice 2: smoke AI classify persistence into ai_analysis_results.
 */
class SmokeSlice11AiResults extends Command
{
    protected $signature = 'rms:smoke-slice11-ai-results';

    protected $description = 'Smoke ai_analysis_results persistence for classify runs';

    public function handle(AiAnalysisService $ai): int
    {
        $ref = 'RISK-SMOKE-AI-'.bin2hex(random_bytes(2));
        $payload = [
            'title' => 'Data breach risk',
            'location' => 'HQ',
            'fiveW1H' => [
                'what' => 'Possible data leak from shared drive',
                'why' => 'Weak access controls',
                'where' => 'File server',
                'when' => 'This week',
                'who' => 'Staff',
                'how' => 'Open share without MFA',
            ],
            'evidenceCount' => 1,
        ];

        $base = rtrim((string) config('rms.ai_service_url', ''), '/');
        if ($base !== '') {
            try {
                $health = Http::timeout(3)->get($base.'/health');
                if ($health->successful()) {
                    $this->info('ai-service reachable');
                }
            } catch (\Throwable) {
                $this->warn('ai-service unreachable; PHP stub path will be used');
            }
        }

        $result = $ai->analyze($payload, $ref);
        if (($result['riskCategory'] ?? '') === '') {
            $this->error('analyze missing riskCategory');

            return self::FAILURE;
        }
        $this->info('analyze OK ('.($result['source'] ?? 'unknown').')');

        $row = AiAnalysisResult::query()
            ->where('ticket_reference', $ref)
            ->orderByDesc('id')
            ->first();
        if (! $row) {
            $this->error('ai_analysis_results row missing');

            return self::FAILURE;
        }
        if ($row->risk_category !== ($result['riskCategory'] ?? null)) {
            $this->error('persisted risk_category mismatch');

            return self::FAILURE;
        }
        $this->info('persist OK id='.$row->id);

        $listed = $ai->listForTicket($ref);
        if (count($listed) < 1) {
            AiAnalysisResult::query()->where('ticket_reference', $ref)->delete();
            $this->error('listForTicket empty');

            return self::FAILURE;
        }
        $this->info('listForTicket OK');

        AiAnalysisResult::query()->where('ticket_reference', $ref)->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
