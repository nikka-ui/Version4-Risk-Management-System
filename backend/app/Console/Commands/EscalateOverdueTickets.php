<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Services\OfficerDashboardService;
use App\Services\WorkflowNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * SLA approaching + overdue escalation for response_due_at and mitigation_due_at.
 */
class EscalateOverdueTickets extends Command
{
    protected $signature = 'rms:escalate-overdue {--dry-run : List only, do not notify}';

    protected $description = 'Notify stakeholders for tickets approaching or past response/mitigation SLA due dates';

    public function handle(
        OfficerDashboardService $dashboard,
        WorkflowNotificationService $notifications,
    ): int {
        $dry = (bool) $this->option('dry-run');
        $approachingHours = max(1, (int) config('rms.sla_approaching_hours', 8));
        $now = now();
        $approachUntil = $now->copy()->addHours($approachingHours);

        $tickets = RiskTicket::query()
            ->where('deleted', false)
            ->whereNotIn('status', ['draft', 'closed', 'resolved'])
            ->where(function ($q) {
                $q->whereNotNull('mitigation_due_at')
                    ->orWhereNotNull('response_due_at');
            })
            ->orderBy('mitigation_due_at')
            ->limit(300)
            ->get();

        $overdue = 0;
        $approaching = 0;

        foreach ($tickets as $ticket) {
            $payload = is_array($ticket->payload) ? $ticket->payload : [];
            $isOverdue = $dashboard->isTicketOverdue($ticket)
                || ($ticket->response_due_at && $ticket->response_due_at->lt($now)
                    && in_array((string) $ticket->status, ['assigned', 'reopened', 'pending_ai_review'], true));

            if ($isOverdue) {
                $overdue++;
                $this->line('[overdue] '.$ticket->reference);
                if (! $dry && ! $this->recentlyNotified($payload, 'slaEscalatedAt')) {
                    $notifications->slaOverdue($ticket);
                    $payload['slaEscalatedAt'] = $now->toIso8601String();
                    $ticket->payload = $payload;
                    $ticket->source_updated_at = $now;
                    $ticket->save();
                }

                continue;
            }

            $dueSoon = false;
            foreach (['mitigation_due_at', 'response_due_at'] as $field) {
                $due = $ticket->{$field};
                if ($due instanceof Carbon && $due->gt($now) && $due->lte($approachUntil)) {
                    $dueSoon = true;
                    break;
                }
            }

            if (! $dueSoon) {
                continue;
            }

            $approaching++;
            $this->line('[approaching] '.$ticket->reference);
            if (! $dry && ! $this->recentlyNotified($payload, 'slaApproachingAt')) {
                $notifications->slaApproaching($ticket);
                $payload['slaApproachingAt'] = $now->toIso8601String();
                $ticket->payload = $payload;
                $ticket->source_updated_at = $now;
                $ticket->save();
            }
        }

        $this->info(($dry ? 'Would notify' : 'Notified')." overdue={$overdue} approaching={$approaching}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recentlyNotified(array $payload, string $key): bool
    {
        $last = $payload[$key] ?? null;
        if (! is_string($last) || $last === '') {
            return false;
        }

        try {
            return now()->diffInHours(Carbon::parse($last)) < 24;
        } catch (\Throwable) {
            return false;
        }
    }
}
