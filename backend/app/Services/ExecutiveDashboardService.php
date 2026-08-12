<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Support\Departments;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 28: Executive Committee dashboard stats (read path).
 * Express parity: mirrors docker/web/lib/tickets.js getExecutiveStats + getExecutiveDashboardData.
 */
class ExecutiveDashboardService
{
    /** @var list<string> */
    private const RISK_LEVELS = ['low', 'moderate', 'high', 'critical'];

    /** @var array<string, string> */
    private const CATEGORIES = [
        'operational' => 'Operational',
        'financial' => 'Financial',
        'compliance' => 'Compliance',
        'strategic' => 'Strategic',
        'reputational' => 'Reputational',
        'environmental' => 'Environmental Risk',
    ];

    public function __construct(
        private readonly OfficerDashboardService $overdueHelper,
    ) {}

    /**
     * @return array{
     *   stats: array{
     *     total: int,
     *     byLevel: array<string,int>,
     *     byCategory: array<string,int>,
     *     highCriticalCount: int,
     *     open: int,
     *     closed: int
     *   },
     *   departments: list<array{name: string, total: int, open: int, closed: int, high: int, critical: int, overdue: int}>,
     *   matrix: list<list<int>>
     * }
     */
    public function data(): array
    {
        $tickets = $this->tickets();

        $byLevel = ['low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 0];
        $byCategory = [];
        $highCriticalCount = 0;
        $open = 0;
        $closed = 0;

        $deptMap = [];

        $matrix = array_fill(0, 5, array_fill(0, 5, 0));

        foreach ($tickets as $ticket) {
            $riskLevel = Departments::riskLevelId(is_array($ticket->ai) ? $ticket->ai : null, $ticket->likelihood, $ticket->impact);
            $category = (string) ($ticket->category ?: '');

            $byLevel[$riskLevel] = ($byLevel[$riskLevel] ?? 0) + 1;
            if ($category !== '') {
                $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
            }
            if (in_array($riskLevel, ['high', 'critical'], true)) {
                $highCriticalCount++;
            }

            if (in_array((string) $ticket->status, ['closed', 'resolved'], true)) {
                $closed++;
            } else {
                $open++;
            }

            $dept = trim((string) ($ticket->department ?: '')) ?: 'Unassigned';
            if (! isset($deptMap[$dept])) {
                $deptMap[$dept] = [
                    'name' => $dept,
                    'total' => 0,
                    'open' => 0,
                    'closed' => 0,
                    'high' => 0,
                    'critical' => 0,
                    'overdue' => 0,
                ];
            }

            $row = &$deptMap[$dept];
            $row['total']++;
            if (in_array((string) $ticket->status, ['closed', 'resolved'], true)) {
                $row['closed']++;
            } else {
                $row['open']++;
            }
            if ($riskLevel === 'high') $row['high']++;
            if ($riskLevel === 'critical') $row['critical']++;
            if ($this->overdueHelper->isTicketOverdue($ticket)) {
                $row['overdue']++;
            }
            unset($row);

            $likelihood = max(1, min(5, (int) ($ticket->likelihood ?: 1)));
            $impact = max(1, min(5, (int) ($ticket->impact ?: 1)));
            $matrix[5 - $likelihood][$impact - 1]++;
        }

        $departments = array_values($deptMap);
        usort($departments, function (array $a, array $b) {
            if ($a['total'] !== $b['total']) {
                return $b['total'] <=> $a['total'];
            }

            return strcmp($a['name'], $b['name']);
        });

        // Only include known category keys for stable rendering.
        $byCategoryStable = [];
        foreach (array_keys(self::CATEGORIES) as $key) {
            $byCategoryStable[$key] = (int) ($byCategory[$key] ?? 0);
        }

        return [
            'stats' => [
                'total' => $tickets->count(),
                'byLevel' => $byLevel,
                'byCategory' => $byCategoryStable,
                'highCriticalCount' => $highCriticalCount,
                'open' => $open,
                'closed' => $closed,
            ],
            'departments' => $departments,
            'matrix' => $matrix,
        ];
    }

    /**
     * @return Collection<int, RiskTicket>
     */
    private function tickets(): Collection
    {
        return RiskTicket::query()
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->orderByDesc('source_updated_at')
            ->orderByDesc('id')
            ->get();
    }
}

