<?php

namespace App\Services;

use App\Models\Department;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Departments;

/**
 * Phase 5 slice 14: System Administrator dashboard stats from Laravel Postgres.
 */
class AdminDashboardService
{
    /** @var list<string> */
    private const CLOSED_STATUSES = ['closed', 'resolved'];

    /**
     * @return array{
     *   stats: array<string, int>,
     *   recentUsers: list<array<string, mixed>>,
     *   deletedTickets: list<array<string, mixed>>,
     *   auditLogs: list<array<string, mixed>>
     * }
     */
    public function data(): array
    {
        $users = User::query()->where('deleted', false)->get();
        $activeUsers = $users->filter(fn (User $u) => $u->isActiveAccount());

        $visibleTickets = RiskTicket::query()->where('deleted', false)->get();
        $ticketStats = $this->ticketStats($visibleTickets);

        return [
            'stats' => [
                'totalUsers' => $users->count(),
                'activeUsers' => $activeUsers->count(),
                'departments' => Department::query()->count(),
                'openTickets' => $ticketStats['open'],
                'closedTickets' => $ticketStats['closed'],
                'highRiskTickets' => $ticketStats['highRisk'],
                'criticalRiskTickets' => $ticketStats['criticalRisk'],
            ],
            'recentUsers' => $this->recentUsers(),
            'deletedTickets' => $this->recentDeletedTickets(),
            'auditLogs' => [],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RiskTicket>  $tickets
     * @return array{open: int, closed: int, highRisk: int, criticalRisk: int}
     */
    private function ticketStats($tickets): array
    {
        $open = 0;
        $closed = 0;
        $highRisk = 0;
        $criticalRisk = 0;

        foreach ($tickets as $ticket) {
            if (in_array((string) $ticket->status, self::CLOSED_STATUSES, true)) {
                $closed++;
            } else {
                $open++;
            }

            $level = Departments::riskLevelId(
                is_array($ticket->ai) ? $ticket->ai : null,
                $ticket->likelihood,
                $ticket->impact,
            );

            if ($level === 'high') {
                $highRisk++;
            } elseif ($level === 'critical') {
                $criticalRisk++;
            }
        }

        return compact('open', 'closed', 'highRisk', 'criticalRisk');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentUsers(): array
    {
        return User::query()
            ->where('deleted', false)
            ->where('active', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (User $u) => $u->toIdentityArray())
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentDeletedTickets(): array
    {
        return RiskTicket::query()
            ->where('deleted', true)
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get()
            ->map(fn (RiskTicket $t) => [
                'ticketRef' => $t->reference,
                'title' => $t->title,
                'deletedBy' => $t->deleted_by ?: '—',
                'at' => optional($t->deleted_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
