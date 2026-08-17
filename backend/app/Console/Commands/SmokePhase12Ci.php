<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Phase 12 slice 1: smoke CI health gate (stack reports phase 12 / slice 1).
 */
class SmokePhase12Ci extends Command
{
    protected $signature = 'rms:smoke-phase12-ci';

    protected $description = 'Smoke Phase 12 CI health gate';

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

        $phase = (int) ($health->json('phase') ?? 0);
        $slice = (int) ($health->json('slice') ?? 0);
        if ($phase !== 12 || $slice !== 1) {
            $this->error('Expected phase 12 slice 1, got phase '.$phase.' slice '.$slice);

            return self::FAILURE;
        }

        $this->info('API health OK (phase 12 / slice 1)');

        $base = rtrim((string) config('rms.ai_service_url', ''), '/');
        if ($base !== '') {
            try {
                $ai = Http::timeout(3)->get($base.'/health');
                if ($ai->successful()) {
                    $this->info('ai-service health OK ('.($ai->json('mode') ?? 'unknown').')');
                }
            } catch (\Throwable) {
                $this->warn('ai-service unreachable (optional in CI gate)');
            }
        }

        return self::SUCCESS;
    }
}
