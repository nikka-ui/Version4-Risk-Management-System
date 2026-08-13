<?php

namespace App\Console\Commands;

use App\Http\Controllers\OfficerQueueController;
use Illuminate\Console\Command;

/**
 * Phase 6 slice 5: smoke RMO legacy path aliases.
 */
class SmokeSlice6OfficerAliases extends Command
{
    protected $signature = 'rms:smoke-slice6-officer-aliases';

    protected $description = 'Smoke Laravel RMO /officer/ai-review, /review, /final-validation aliases';

    public function handle(OfficerQueueController $queues): int
    {
        $ai = $queues->aiReview();
        $review = $queues->review();
        $final = $queues->finalValidation();

        if (! str_contains($ai->getTargetUrl(), '/officer/tickets')) {
            $this->error('ai-review alias does not redirect to /officer/tickets');

            return self::FAILURE;
        }
        if (! str_contains($review->getTargetUrl(), '/officer/tickets')) {
            $this->error('review alias does not redirect to /officer/tickets');

            return self::FAILURE;
        }
        if (! str_contains($final->getTargetUrl(), '/officer/action-plans')) {
            $this->error('final-validation alias does not redirect to /officer/action-plans');

            return self::FAILURE;
        }

        $this->info('officer legacy aliases OK');

        return self::SUCCESS;
    }
}
