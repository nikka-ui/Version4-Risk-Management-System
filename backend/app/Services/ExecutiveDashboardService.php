<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Support\Departments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 28 + Phase 6 slice 3: Executive dashboard + oversight pages (read path).
 * Express parity: docker/web/lib/tickets.js getExecutiveStats + getExecutiveDashboardData.
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
     *   matrix: list<list<int>>,
     *   trends: list<array{key:string,label:string,count:int,highCritical:int}>,
     *   byStatus: array<string,int>,
     *   highCriticalTickets: list<array<string,mixed>>,
     *   categories: array<string,string>
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
        $byStatus = [];
        $highCritical = [];

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
                $highCritical[] = $this->listRow($ticket, $riskLevel);
            }

            $status = (string) $ticket->status;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

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

        usort($highCritical, function (array $a, array $b) {
            return strcmp((string) ($b['updatedAt'] ?? ''), (string) ($a['updatedAt'] ?? ''));
        });

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
            'trends' => $this->trends($tickets),
            'byStatus' => $byStatus,
            'highCriticalTickets' => $highCritical,
            'categories' => self::CATEGORIES,
        ];
    }

    /**
     * @return array{
     *   stats: array<string, mixed>,
     *   tickets: list<array<string, mixed>>,
     *   filters: array{level: string, category: string},
     *   categories: array<string, string>
     * }
     */
    public function register(?string $level = null, ?string $category = null): array
    {
        $level = is_string($level) ? strtolower(trim($level)) : '';
        $category = is_string($category) ? strtolower(trim($category)) : '';
        if (! in_array($level, self::RISK_LEVELS, true)) {
            $level = '';
        }
        if ($category !== '' && ! isset(self::CATEGORIES[$category])) {
            $category = '';
        }

        $payload = $this->data();
        $tickets = $this->tickets()
            ->map(function (RiskTicket $t) {
                $riskLevel = Departments::riskLevelId(is_array($t->ai) ? $t->ai : null, $t->likelihood, $t->impact);

                return $this->listRow($t, $riskLevel);
            })
            ->sort(function (array $a, array $b) {
                $order = ['low' => 1, 'moderate' => 2, 'high' => 3, 'critical' => 4];
                $cmp = ($order[$a['riskLevel']] ?? 0) <=> ($order[$b['riskLevel']] ?? 0);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) ($b['updatedAt'] ?? ''), (string) ($a['updatedAt'] ?? ''));
            })
            ->values()
            ->all();

        if ($level !== '') {
            $tickets = array_values(array_filter($tickets, fn ($t) => ($t['riskLevel'] ?? '') === $level));
        }
        if ($category !== '') {
            $tickets = array_values(array_filter($tickets, fn ($t) => ($t['category'] ?? '') === $category));
        }

        return [
            'stats' => $payload['stats'],
            'tickets' => $tickets,
            'filters' => [
                'level' => $level,
                'category' => $category,
            ],
            'categories' => self::CATEGORIES,
        ];
    }

    /**
     * @param  Collection<int, RiskTicket>  $tickets
     * @return list<array{key:string,label:string,count:int,highCritical:int}>
     */
    private function trends(Collection $tickets): array
    {
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
            $key = Carbon::parse($raw)->format('Y-m');
            if (! isset($monthMap[$key])) {
                continue;
            }
            $monthMap[$key]['count']++;
            $level = Departments::riskLevelId(is_array($ticket->ai) ? $ticket->ai : null, $ticket->likelihood, $ticket->impact);
            if (in_array($level, ['high', 'critical'], true)) {
                $monthMap[$key]['highCritical']++;
            }
        }

        return array_values($monthMap);
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(RiskTicket $ticket, string $riskLevel): array
    {
        $status = (string) $ticket->status;
        $category = (string) ($ticket->category ?: '');

        return [
            'reference' => $ticket->reference,
            'title' => $ticket->title ?: '—',
            'department' => $ticket->department ?: '—',
            'category' => $category,
            'categoryLabel' => self::CATEGORIES[$category] ?? ($category !== '' ? str_replace('_', ' ', ucfirst($category)) : '—'),
            'riskLevel' => $riskLevel,
            'riskLevelLabel' => match ($riskLevel) {
                'critical' => 'Critical',
                'high' => 'High',
                'moderate' => 'Moderate',
                default => 'Low',
            },
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'updatedAt' => optional($ticket->source_updated_at)?->toIso8601String(),
            'isOverdue' => $this->overdueHelper->isTicketOverdue($ticket),
        ];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'submitted' => 'Submitted',
            'assigned' => 'Assigned',
            'in_progress' => 'In progress',
            'ownership_rejected' => 'Ownership rejected',
            'pending_president' => 'Pending president',
            'pending_president_final' => 'Pending president (final)',
            'under_review' => 'Under review',
            'returned' => 'Returned',
            'in_mitigation' => 'In mitigation',
            'reopened' => 'Reopened',
            'pending_audit' => 'Pending audit',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => $status ? str_replace('_', ' ', ucfirst($status)) : '—',
        };
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

