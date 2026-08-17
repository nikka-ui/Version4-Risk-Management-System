<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\AiAnalysisService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 11 slice 6: smoke admin ticket AI reclassify (persist + live ticket.ai refresh).
 */
class SmokeSlice11AiReclassify extends Command
{
    protected $signature = 'rms:smoke-slice11-ai-reclassify';

    protected $description = 'Smoke admin ticket AI reclassify';

    public function handle(AiAnalysisService $ai): int
    {
        $ref = 'RISK-SMOKE-RECL-'.strtoupper(bin2hex(random_bytes(2)));
        $ticket = RiskTicket::query()->create([
            'external_id' => 'tkt-smoke-recl-'.bin2hex(random_bytes(2)),
            'reference' => $ref,
            'title' => 'Network outage risk',
            'location' => 'Data center',
            'status' => 'assigned',
            'category' => 'financial',
            'department' => 'Finance',
            'priority' => 'high',
            'submitted_by' => 'smoke_reporter',
            'submitted_by_name' => 'Smoke Reporter',
            'likelihood' => 2,
            'impact' => 2,
            'risk_score' => 4,
            'evidence_count' => 1,
            'five_w1h' => [
                'what' => 'Core switch failed during peak hours',
                'why' => 'Aging hardware',
                'where' => 'Rack A',
                'how' => 'Single point of failure caused outage',
            ],
            'ai' => ['riskCategory' => 'financial', 'summary' => 'Stale AI'],
            'audit_trail' => [],
            'deleted' => false,
            'source_created_at' => now(),
            'source_updated_at' => now(),
        ]);

        $before = count($ai->listForTicket($ref));
        $username = 'smoke_recl_'.bin2hex(random_bytes(3));
        $admin = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Reclassify Admin',
            'email' => "{$username}@rms.local",
            'password' => 'SmokeRecl11!',
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($admin);
        $result = $ai->reclassifyTicket($ticket->fresh(), $admin);
        Auth::logout();

        if (($result['riskCategory'] ?? '') === '') {
            $ticket->delete();
            $admin->delete();
            $this->error('reclassify missing riskCategory');

            return self::FAILURE;
        }
        $this->info('reclassify OK ('.($result['riskCategory'] ?? 'unknown').')');

        $fresh = RiskTicket::query()->where('reference', $ref)->first();
        if (! $fresh || ($fresh->ai['riskCategory'] ?? '') !== ($result['riskCategory'] ?? null)) {
            $ticket->delete();
            $admin->delete();
            $this->error('ticket.ai not refreshed');

            return self::FAILURE;
        }
        if ($fresh->status !== 'assigned' || $fresh->department !== 'Finance') {
            $ticket->delete();
            $admin->delete();
            $this->error('workflow fields changed unexpectedly');

            return self::FAILURE;
        }
        $this->info('ticket.ai refreshed; workflow unchanged');

        $after = count($ai->listForTicket($ref));
        if ($after <= $before) {
            $ticket->delete();
            $admin->delete();
            $this->error('history row not persisted');

            return self::FAILURE;
        }
        $this->info('history persisted ('.$after.' runs)');

        RiskTicket::query()->where('reference', $ref)->delete();
        $admin->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
