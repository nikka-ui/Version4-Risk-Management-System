<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Support\Departments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 29–30: President dashboard + queue stats (read path).
 *
 * Express parity: docker/web/lib/tickets.js getPresidentStats + getPresidentDashboardData.
 */
class PresidentDashboardService
{
    /** @var array<string, int> */
    private const RISK_LEVEL_ORDER = ['low' => 1, 'moderate' => 2, 'high' => 3, 'critical' => 4];

    public function __construct(
        private readonly OfficerDashboardService $overdueHelper,
        private readonly ExecutiveDashboardService $executiveStats,
    ) {}

    /**
     * @return array{
     *   stats: array<string, mixed>,
     *   org: array<string, mixed>,
     *   matrix: list<list<int>>
     * }
     */
    public function data(): array
    {
        $stats = $this->stats();
        $executive = $this->executiveStats->data();
        $orgStats = $executive['stats'] ?? [];

        return [
            'stats' => $stats,
            'org' => [
                'byLevel' => $orgStats['byLevel'] ?? ['low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 0],
                'total' => (int) ($orgStats['total'] ?? 0),
                'open' => (int) ($orgStats['open'] ?? 0),
                'closed' => (int) ($orgStats['closed'] ?? 0),
            ],
            'matrix' => $executive['matrix'] ?? [],
        ];
    }

    /**
     * Sidebar + KPI stats (mirrors getPresidentStats).
     *
     * @return array{
     *   total: int,
     *   byLevel: array{high:int,critical:int},
     *   highCount: int,
     *   criticalCount: int,
     *   pendingCount: int,
     *   pendingTickets: list<array<string, mixed>>,
     *   open: int,
     *   closed: int
     * }
     */
    public function stats(): array
    {
        $tickets = $this->presidentVisibleTickets();
        $byLevel = ['high' => 0, 'critical' => 0];

        foreach ($tickets as $ticket) {
            $level = $this->ticketRiskLevelId($ticket);
            if (isset($byLevel[$level])) {
                $byLevel[$level]++;
            }
        }

        $pending = $this->listPendingQueueTickets()
            ->sortByDesc(fn (RiskTicket $t) => optional($t->source_updated_at)?->timestamp ?? 0)
            ->values();

        $open = $tickets->filter(fn (RiskTicket $t) => ! $this->isFinal($t->status))->count();
        $closed = $tickets->filter(fn (RiskTicket $t) => $this->isFinal($t->status))->count();

        return [
            'total' => $tickets->count(),
            'byLevel' => $byLevel,
            'highCount' => $byLevel['high'],
            'criticalCount' => $byLevel['critical'],
            'pendingCount' => $pending->count(),
            'pendingTickets' => $pending
                ->take(10)
                ->map(fn (RiskTicket $t) => $this->listRow($t))
                ->values()
                ->all(),
            'open' => $open,
            'closed' => $closed,
        ];
    }

    /**
     * @return Collection<int, RiskTicket>
     */
    public function presidentVisibleTickets(): Collection
    {
        return RiskTicket::query()
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->get()
            ->filter(fn (RiskTicket $t) => in_array($this->ticketRiskLevelId($t), ['high', 'critical'], true))
            ->values();
    }

    /**
     * Pending presidential decisions (mirrors listPresidentPendingQueue).
     *
     * @return Collection<int, RiskTicket>
     */
    public function listPendingQueueTickets(): Collection
    {
        return $this->presidentVisibleTickets()
            ->filter(function (RiskTicket $t): bool {
                $status = (string) $t->status;

                return in_array($status, ['pending_president', 'pending_president_final'], true)
                    || $this->needsPresidentActionPlanDecision($t);
            })
            ->sortByDesc(fn (RiskTicket $t) => optional($t->source_updated_at)?->timestamp ?? 0)
            ->values();
    }

    /**
     * High/Critical register filtered by level (mirrors listTicketsForPresident).
     *
     * @return Collection<int, RiskTicket>
     */
    public function listByLevel(string $level): Collection
    {
        if (! in_array($level, ['high', 'critical'], true)) {
            return collect();
        }

        return $this->presidentVisibleTickets()
            ->filter(fn (RiskTicket $t) => $this->ticketRiskLevelId($t) === $level)
            ->sort(fn (RiskTicket $a, RiskTicket $b) => $this->compareByRiskLevel($a, $b))
            ->values();
    }

    /**
     * Monthly org-wide trends (mirrors buildExecutiveTrends on org tickets).
     *
     * @return list<array{key:string,label:string,count:int,highCritical:int}>
     */
    public function trends(): array
    {
        $tickets = RiskTicket::query()
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->get();

        $months = [];
        $now = now();

        for ($i = 11; $i >= 0; $i--) {
            $d = $now->copy()->subMonths($i)->startOfMonth();
            $months[] = [
                'key' => $d->format('Y-m'),
                'label' => $d->format('M y'),
                'count' => 0,
                'highCritical' => 0,
            ];
        }

        $monthMap = collect($months)->keyBy('key')->all();

        foreach ($tickets as $ticket) {
            $raw = $ticket->submitted_at ?? $ticket->source_created_at;
            if (! $raw) {
                continue;
            }

            $d = Carbon::parse($raw);
            $key = $d->format('Y-m');
            if (! isset($monthMap[$key])) {
                continue;
            }

            $monthMap[$key]['count']++;
            $level = $this->ticketRiskLevelId($ticket);
            if (in_array($level, ['high', 'critical'], true)) {
                $monthMap[$key]['highCritical']++;
            }
        }

        return array_values($monthMap);
    }

    public function ticketRiskLevelId(RiskTicket $t): string
    {
        return Departments::riskLevelId(is_array($t->ai) ? $t->ai : null, $t->likelihood, $t->impact);
    }

    public function isTicketOverdue(RiskTicket $t): bool
    {
        return $this->overdueHelper->isTicketOverdue($t);
    }

    /**
     * @return array<string, mixed>
     */
    public function listRow(RiskTicket $ticket): array
    {
        $levelId = $this->ticketRiskLevelId($ticket);
        $status = (string) $ticket->status;
        $category = (string) ($ticket->category ?: '');

        return [
            'reference' => (string) $ticket->reference,
            'title' => (string) ($ticket->title ?: '—'),
            'level' => $levelId,
            'riskLevelLabel' => $this->riskLevelLabel($levelId),
            'category' => $category ?: '—',
            'categoryLabel' => $category
                ? str_replace('_', ' ', ucfirst($category))
                : '—',
            'department' => (string) ($ticket->department ?: '—'),
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'updatedAt' => optional($ticket->source_updated_at)?->toIso8601String(),
            'isOverdue' => $this->isTicketOverdue($ticket),
        ];
    }

    private function compareByRiskLevel(RiskTicket $a, RiskTicket $b): int
    {
        $rankA = self::RISK_LEVEL_ORDER[$this->ticketRiskLevelId($a)] ?? 0;
        $rankB = self::RISK_LEVEL_ORDER[$this->ticketRiskLevelId($b)] ?? 0;

        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        $aTs = optional($a->source_updated_at)?->timestamp ?? 0;
        $bTs = optional($b->source_updated_at)?->timestamp ?? 0;

        return $bTs <=> $aTs;
    }

    private function isFinal(?string $status): bool
    {
        return in_array((string) $status, ['closed', 'resolved'], true);
    }

    /** Mirrors needsPresidentActionPlanDecision in Express. */
    private function needsPresidentActionPlanDecision(RiskTicket $t): bool
    {
        if (! in_array($this->ticketRiskLevelId($t), ['high', 'critical'], true)) {
            return false;
        }

        if (! strlen(trim($this->actionPlanSummary($t)))) {
            return false;
        }

        if ($this->hasPresidentPlanDecision($t)) {
            return false;
        }

        if ((string) $t->status === 'pending_president_final') {
            return false;
        }

        if ($this->isFinal($t->status) || (string) $t->status === 'draft') {
            return false;
        }

        return true;
    }

    private function actionPlanSummary(RiskTicket $t): string
    {
        $plan = $t->action_plan;

        return is_array($plan) ? (string) ($plan['summary'] ?? '') : '';
    }

    private function hasPresidentPlanDecision(RiskTicket $t): bool
    {
        $decision = $t->president_plan_decision;
        if (is_array($decision) && $decision !== []) {
            return true;
        }
        if (is_string($decision)) {
            return trim($decision) !== '';
        }

        return ! empty($decision);
    }

    private function riskLevelLabel(string $levelId): string
    {
        return match ($levelId) {
            'critical' => 'Critical',
            'high' => 'High',
            'moderate' => 'Moderate',
            'low' => 'Low',
            default => ucfirst($levelId),
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending_president' => 'Awaiting President Approval',
            'pending_president_final' => 'Awaiting President Final Decision',
            default => $status ? str_replace('_', ' ', ucfirst($status)) : '—',
        };
    }
}
