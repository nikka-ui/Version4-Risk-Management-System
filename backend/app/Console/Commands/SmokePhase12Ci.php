<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Phase 13 slice 1: smoke CI health gate (stack reports phase 13 / slice 1).
 */
class SmokePhase12Ci extends Command
{
    protected $signature = 'rms:smoke-phase13-ai';

    protected $description = 'Smoke Phase 13 transformer-hybrid health gate';

    public function handle(): int
    {
        $apiBase = rtrim((string) env('RMS_SMOKE_API_URL', 'http://127.0.0.1:8080'), '/');
        try {
            $health = Http::timeout(3)->get($apiBase.'/v1/health');
        } catch (\Throwable $e) {
            $this->error('API health unreachable: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $health->successful()) {
            $this->error('API health HTTP '.$health->status());

            return self::FAILURE;
        }

        $this->info('API health OK (phase '.($health->json('phase') ?? '?').' / slice '.($health->json('slice') ?? '?').')');

        $base = rtrim((string) config('rms.ai_service_url', ''), '/');
        if ($base !== '') {
            try {
                $ai = Http::timeout(3)->get($base.'/health');
                if ($ai->successful()) {
                    $mode = (string) ($ai->json('mode') ?? 'unknown');
                    if ($mode !== 'transformer-hybrid') {
                        $this->error('ai-service mode expected transformer-hybrid, got '.$mode);

                        return self::FAILURE;
                    }
                    $this->info('ai-service health OK ('.$mode.', '.($ai->json('device') ?? 'cpu').')');
                }
            } catch (\Throwable) {
                $this->warn('ai-service unreachable (optional in CI gate)');
            }
        }

        return self::SUCCESS;
    }
}
