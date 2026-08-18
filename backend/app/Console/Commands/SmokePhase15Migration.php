<?php

namespace App\Console\Commands;

/**
 * Phase 15 slice 1: migration closure smoke (supersedes phase 14 frontend smoke).
 */
class SmokePhase15Migration extends SmokePhase14Frontend
{
    protected $signature = 'rms:smoke-phase15-migration';

    protected $aliases = ['rms:smoke-phase14-frontend'];

    protected $description = 'Smoke Phase 16 admin + workflow mutations + Next.js /app UI';
}
