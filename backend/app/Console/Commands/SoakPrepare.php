<?php

namespace App\Console\Commands;

use App\Models\Accomplishment;
use App\Models\Department;
use App\Models\Position;
use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Phase 4/5: prepare Laravel Postgres for dual-write (import Express store).
 * Imports users/org/tickets from Express store.json (idempotent) and prints parity counts.
 * Does not modify store.json. USE_LARAVEL_API defaults ON as of Phase 5 slice 1.
 */
class SoakPrepare extends Command
{
    protected $signature = 'rms:soak-prepare
                            {--skip-import : Only report counts; do not run imports}
                            {--dry-run : Pass --dry-run through import commands}';

    protected $description = 'Phase 4 soak: import Express store into Laravel and report parity readiness';

    public function handle(): int
    {
        $this->info('Phase 4 soak prepare — Express store.json remains live SoT.');

        if (! $this->option('skip-import')) {
            $dry = $this->option('dry-run') ? ['--dry-run' => true] : [];
            $this->call('rms:import-users', $dry);
            $this->call('rms:import-org', $dry);
            $this->call('rms:import-tickets', $dry);
        }

        $this->newLine();
        $this->table(
            ['Resource', 'Laravel count'],
            [
                ['users', User::query()->count()],
                ['departments', Department::query()->count()],
                ['positions', Position::query()->count()],
                ['risk_tickets', RiskTicket::query()->count()],
                ['accomplishments', Accomplishment::query()->count()],
                ['risk_attachments', RiskAttachment::query()->count()],
            ],
        );

        $this->newLine();
        $this->line('Next: dual-write is ON by default (Phase 5). Run rms:smoke-soak, then exercise the UI.');
        $this->line('Opt out: docker compose ... -f docker/compose.soak.yml up -d web');
        $this->line('Express remains the browser entry until later Phase 5 UI slices.');

        return self::SUCCESS;
    }
}
