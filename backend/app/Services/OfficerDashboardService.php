<?php

namespace App\Services;

use App\Models\RiskTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 25: RMO dashboard stats / department tiles / risk matrix from Postgres.
 * Mirrors docker/web getOfficerDashboardData + getOfficerStats (read path).
 */
class OfficerDashboardService
{
    /** @var list<string> */
    private const AI_REVIEW_STATUSES = ['submitted', 'assigned', 'in_progress', 'ownership_rejected'];

    /** @var list<string> */
    private const MONITORING_STATUSES = [
        'assigned',
        'in_progress',
        'ownership_rejected',
        'pending_president',
        'pending_president_final',
        'under_review',
        'returned',
        'under_audit',
        'audit_returned',
        'in_mitigation',
        'reopened',
        'pending_audit',
    ];

    /** @var list<string> */
    private const ACTION_PLAN_STATUSES = ['in_progress', 'pending_president', 'reopened'];

    /** @var list<string> */
    private const OVERDUE_EXCLUDED = [
        'pending_audit',
        'pending_president_final',
        'resolved',
        'closed',
    ];

    /** @var list<string> */
    private const REVISION_STATUSES = ['returned', 'ownership_rejected'];

    /**
     * @return array{
     *   stats: array<string, int>,
     *   departments: list<array{name: string, count: int}>,
     *   matrix: list<list<int>>
     * }
     */
    public function data(): array
    {
        $tickets = $this->tickets();
        $stats = $this->stats($tickets);
        $departments = $this->departments($tickets);
        $matrix = $this->matrix($tickets);

        return [
            'stats' => $stats,
            'departments' => $departments,
            'matrix' => $matrix,
        ];
    }

    /**
     * @return Collection<int, RiskTicket>
     */
    public function tickets(): Collection
    {
        return RiskTicket::query()
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->orderByDesc('source_updated_at')
            ->orderByDesc('id')
            ->get();
    }

    public function isTicketOverdue(RiskTicket $ticket): bool
    {
        return $this->isOverdue($ticket);
    }

    public function ticketHasActionPlan(RiskTicket $ticket): bool
    {
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : [];

        return trim((string) ($plan['summary'] ?? '')) !== '';
    }

    /**
     * @return list<string>
     */
    public function monitoringStatuses(): array
    {
        return self::MONITORING_STATUSES;
    }

    /**
     * @return list<string>
     */
    public function actionPlanStatuses(): array
    {
        return self::ACTION_PLAN_STATUSES;
    }

    /**
     * @param  Collection<int, RiskTicket>  $tickets
     * @return array<string, int>
     */
    public function statsForTickets(Collection $tickets): array
    {
        return $this->stats($tickets);
    }

    /**
     * @param  Collection<int, RiskTicket>  $tickets
     * @return array<string, int>
     */
    private function stats(Collection $tickets): array
    {
        $monitoring = $tickets->whereIn('status', self::MONITORING_STATUSES);
        $aiReview = $tickets->filter(function (RiskTicket $t) {
            if (in_array((string) $t->status, self::AI_REVIEW_STATUSES, true)) {
                return true;
            }
            $ai = is_array($t->ai) ? $t->ai : [];

            return ! empty($ai['manualReviewRequired']);
        });
        $actionPlans = $tickets->filter(function (RiskTicket $t) {
            if (! in_array((string) $t->status, self::ACTION_PLAN_STATUSES, true)) {
                return false;
            }

            return $this->ticketHasActionPlan($t);
        });
        $compliance = $tickets->filter(
            fn (RiskTicket $t) => (string) $t->category === 'compliance'
                && ! in_array((string) $t->status, ['closed', 'resolved'], true)
        );

        return [
            'total' => $tickets->count(),
            'awaitingReview' => $aiReview->count(),
            'pendingReview' => $tickets->filter(function (RiskTicket $t) {
                $ai = is_array($t->ai) ? $t->ai : [];

                return ! empty($ai['manualReviewRequired']);
            })->count(),
            'returnedByAudit' => $tickets->where('status', 'audit_returned')->count(),
            'awaitingFinalValidation' => $actionPlans->count(),
            'inMitigation' => $monitoring->count(),
            'returned' => $tickets->where('status', 'returned')->count(),
            'closed' => $tickets->whereIn('status', ['closed', 'resolved'])->count(),
            'overdueMitigation' => $tickets->filter(fn (RiskTicket $t) => $this->isOverdue($t))->count(),
            'open' => $tickets->filter(
                fn (RiskTicket $t) => ! in_array((string) $t->status, ['closed', 'resolved'], true)
            )->count(),
            'complianceOpen' => $compliance->count(),
            'escalated' => $tickets->filter(function (RiskTicket $t) {
                $escalations = is_array($t->escalations) ? $t->escalations : [];

                return count($escalations) > 0;
            })->count(),
        ];
    }

    /**
     * @param  Collection<int, RiskTicket>  $tickets
     * @return list<array{name: string, count: int}>
     */
    private function departments(Collection $tickets): array
    {
        $map = [];
        foreach ($tickets as $ticket) {
            $name = trim((string) ($ticket->department ?: 'Unassigned')) ?: 'Unassigned';
            $map[$name] = ($map[$name] ?? 0) + 1;
        }

        $rows = [];
        foreach ($map as $name => $count) {
            $rows[] = ['name' => $name, 'count' => $count];
        }

        usort($rows, function (array $a, array $b) {
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }

            return strcmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /**
     * 5×5 matrix: rows = likelihood 5→1, cols = impact 1→5.
     *
     * @param  Collection<int, RiskTicket>  $tickets
     * @return list<list<int>>
     */
    private function matrix(Collection $tickets): array
    {
        $matrix = array_fill(0, 5, array_fill(0, 5, 0));
        foreach ($tickets as $ticket) {
            $likelihood = max(1, min(5, (int) ($ticket->likelihood ?: 1)));
            $impact = max(1, min(5, (int) ($ticket->impact ?: 1)));
            $matrix[5 - $likelihood][$impact - 1]++;
        }

        return $matrix;
    }

    private function isOverdue(RiskTicket $ticket): bool
    {
        $status = (string) $ticket->status;
        if (in_array($status, ['closed', 'resolved', 'draft'], true)) {
            return false;
        }
        if (in_array($status, self::REVISION_STATUSES, true)) {
            return false;
        }
        if (in_array($status, self::OVERDUE_EXCLUDED, true)) {
            return false;
        }
        if ($ticket->accomplishment_external_id) {
            return false;
        }

        $due = $this->dueAt($ticket);

        return $due !== null && now()->greaterThan($due);
    }

    private function dueAt(RiskTicket $ticket): ?Carbon
    {
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        $raw = $plan['targetDate'] ?? null;
        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                // fall through
            }
        }

        return $ticket->mitigation_due_at;
    }
}
