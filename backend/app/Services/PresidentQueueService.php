<?php

namespace App\Services;

use App\Models\RiskTicket;

/**
 * Phase 5 slice 30: President queue lists from Postgres (mirrors Express president queues).
 */
class PresidentQueueService
{
    public function __construct(
        private readonly PresidentDashboardService $dashboard,
    ) {}

    /**
     * @return array{
     *   filter: string,
     *   activeNav: string,
     *   title: string,
     *   desc: string,
     *   emptyMessage: string,
     *   stats: array<string, mixed>,
     *   tickets: list<array<string, mixed>>
     * }
     */
    public function listForFilter(string $filter): array
    {
        $filter = $this->normalizeFilter($filter);
        $meta = $this->pageMeta($filter);
        $stats = $this->dashboard->stats();

        $tickets = match ($filter) {
            'pending' => $this->dashboard->listPendingQueueTickets(),
            'high' => $this->dashboard->listByLevel('high'),
            'critical' => $this->dashboard->listByLevel('critical'),
            default => $this->dashboard->listPendingQueueTickets(),
        };

        return [
            'filter' => $filter,
            'activeNav' => $meta['activeNav'],
            'title' => $meta['title'],
            'desc' => $meta['desc'],
            'emptyMessage' => $meta['emptyMessage'],
            'stats' => $stats,
            'tickets' => $tickets
                ->map(fn (RiskTicket $t) => $this->dashboard->listRow($t))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, trends: list<array<string, mixed>>}
     */
    public function trendsData(): array
    {
        return [
            'stats' => $this->dashboard->stats(),
            'trends' => $this->dashboard->trends(),
        ];
    }

    private function normalizeFilter(string $filter): string
    {
        return match ($filter) {
            'pending', 'high', 'critical' => $filter,
            default => 'pending',
        };
    }

    /**
     * @return array{activeNav: string, title: string, desc: string, emptyMessage: string}
     */
    private function pageMeta(string $filter): array
    {
        return match ($filter) {
            'high' => [
                'activeNav' => 'high',
                'title' => 'High risks',
                'desc' => 'High-risk reports. Action plans on these tickets require presidential approval or return for revision.',
                'emptyMessage' => 'No high risk reports at this time.',
            ],
            'critical' => [
                'activeNav' => 'critical',
                'title' => 'Critical risks',
                'desc' => 'Extreme/Critical risk reports — highest priority for presidential review.',
                'emptyMessage' => 'No critical risk reports at this time.',
            ],
            default => [
                'activeNav' => 'pending',
                'title' => 'Pending decisions',
                'desc' => 'High and Critical risk action plans awaiting your approval or return for revision.',
                'emptyMessage' => 'No tickets awaiting presidential decision.',
            ],
        };
    }
}
