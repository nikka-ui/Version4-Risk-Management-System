<?php

namespace App\Services;

use App\Models\Accomplishment;
use App\Models\Notification;
use App\Models\RiskTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 7: Ticket Reporter dashboard stats/recent list from Laravel Postgres.
 */
class SupervisorDashboardService
{
    private const REVISION_STATUSES = ['returned', 'ownership_rejected'];

    /** @var list<string> */
    private const ACTION_STATUSES = ['in_mitigation', 'returned', 'reopened', 'ownership_rejected'];

    private const OVERDUE_EXCLUDED = [
        'pending_audit',
        'pending_president_final',
        'resolved',
        'closed',
    ];

    /**
     * @return array{stats: array<string, int>, recent: list<array<string, mixed>>}
     */
    public function forUsername(string $username): array
    {
        $tickets = $this->ticketsFor($username);
        $stats = $this->stats($username, $tickets);
        $recent = $tickets->take(5)->map(fn (RiskTicket $t) => $this->listRow($t))->values()->all();

        return [
            'stats' => $stats,
            'recent' => $recent,
        ];
    }

    /**
     * Phase 5 slice 8: filtered ticket list for Ticket Reporter console.
     *
     * @return array{
     *   filter: string,
     *   title: string,
     *   desc: string,
     *   activeNav: string,
     *   stats: array<string, int>,
     *   counts: array<string, int>,
     *   tickets: list<array<string, mixed>>,
     *   showDueColumn: bool
     * }
     */
    public function listForUsername(string $username, ?string $filter = null): array
    {
        $filter = $this->normalizeFilter($filter);
        $tickets = $this->ticketsFor($username);
        $stats = $this->stats($username, $tickets);
        $rows = $tickets->map(fn (RiskTicket $t) => $this->listRow($t));
        $filtered = $this->applyFilter($rows, $filter);

        $meta = $this->pageMeta($filter);
        $overdueCount = (int) ($stats['overdue'] ?? 0);
        $showDue = $filter === 'overdue' || $overdueCount > 0;

        return [
            'filter' => $filter,
            'title' => $meta['title'],
            'desc' => $meta['desc'],
            'activeNav' => $meta['activeNav'],
            'stats' => $stats,
            'counts' => [
                'all' => $tickets->count(),
                'draft' => (int) ($stats['drafts'] ?? 0),
                'returned' => (int) ($stats['returned'] ?? 0),
                'overdue' => $overdueCount,
                'submitted' => (int) ($stats['submitted'] ?? 0),
                'closed' => (int) ($stats['closed'] ?? 0),
            ],
            'tickets' => $filtered->values()->all(),
            'showDueColumn' => $showDue,
        ];
    }

    /**
     * Phase 5 slice 12: tickets awaiting reporter action (implementation, revision, accomplishment).
     *
     * @return list<array<string, mixed>>
     */
    public function actionsForUsername(string $username): array
    {
        return $this->ticketsFor($username)
            ->filter(fn (RiskTicket $t) => in_array((string) $t->status, self::ACTION_STATUSES, true))
            ->map(fn (RiskTicket $t) => array_merge($this->listRow($t), [
                'riskScore' => (int) ($t->risk_score ?? ((int) $t->likelihood * (int) $t->impact)),
            ]))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, RiskTicket>
     */
    private function ticketsFor(string $username): Collection
    {
        return RiskTicket::query()
            ->where('submitted_by', $username)
            ->where('deleted', false)
            ->orderByDesc('source_updated_at')
            ->orderByDesc('id')
            ->get();
    }

    private function normalizeFilter(?string $filter): string
    {
        $filter = strtolower(trim((string) $filter));

        return in_array($filter, ['draft', 'returned', 'overdue', 'submitted', 'closed'], true)
            ? $filter
            : '';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilter(Collection $rows, string $filter): Collection
    {
        return match ($filter) {
            'draft' => $rows->where('status', 'draft'),
            'returned' => $rows->whereIn('status', self::REVISION_STATUSES),
            'overdue' => $rows->where('isOverdue', true),
            'closed' => $rows->whereIn('status', ['closed', 'resolved']),
            'submitted' => $rows->where('status', '!=', 'draft'),
            default => $rows,
        };
    }

    /**
     * @return array{title: string, desc: string, activeNav: string}
     */
    private function pageMeta(string $filter): array
    {
        return match ($filter) {
            'draft' => [
                'title' => 'Draft reports',
                'desc' => 'Reports saved but not yet submitted. Edit or delete drafts before submission.',
                'activeNav' => 'drafts',
            ],
            'submitted' => [
                'title' => 'Submitted reports',
                'desc' => 'Tickets submitted for AI analysis and automatic routing to the responsible department.',
                'activeNav' => 'submitted',
            ],
            'returned' => [
                'title' => 'Returned reports',
                'desc' => 'Reports returned by the Risk Management Unit or responsible department for revision and resubmission.',
                'activeNav' => 'returned',
            ],
            'overdue' => [
                'title' => 'Overdue tickets',
                'desc' => 'Tickets past the department or RMO target date. These need attention from the handling department.',
                'activeNav' => 'overdue',
            ],
            'closed' => [
                'title' => 'My tickets',
                'desc' => 'All risk tickets you have reported — from drafts through closure. Responsible department is assigned by AI on submit.',
                'activeNav' => 'tickets',
            ],
            default => [
                'title' => 'My tickets',
                'desc' => 'All risk tickets you have reported — from drafts through closure. Responsible department is assigned by AI on submit.',
                'activeNav' => 'tickets',
            ],
        };
    }

    /**
     * @param  Collection<int, RiskTicket>  $tickets
     * @return array<string, int>
     */
    private function stats(string $username, Collection $tickets): array
    {
        $refs = $tickets->pluck('reference')->filter()->all();
        $accomplishments = Accomplishment::query()
            ->where('submitted_by', $username)
            ->when($refs !== [], fn ($q) => $q->whereIn('ticket_ref', $refs))
            ->when($refs === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->count();

        $unread = Notification::query()
            ->where('recipient_username', $username)
            ->whereNull('read_at')
            ->count();

        return [
            'total' => $tickets->count(),
            'drafts' => $tickets->where('status', 'draft')->count(),
            'submitted' => $tickets->where('status', '!=', 'draft')->count(),
            'returned' => $tickets->whereIn('status', self::REVISION_STATUSES)->count(),
            'overdue' => $tickets->filter(fn (RiskTicket $t) => $this->isOverdue($t))->count(),
            'closed' => $tickets->whereIn('status', ['closed', 'resolved'])->count(),
            'accomplishments' => $accomplishments,
            'unreadNotifications' => $unread,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(RiskTicket $ticket): array
    {
        $due = $this->dueAt($ticket);
        $status = (string) $ticket->status;

        return [
            'reference' => $ticket->reference,
            'title' => $ticket->title ?: '—',
            'category' => $ticket->category ?: '—',
            'categoryLabel' => $ticket->category
                ? str_replace('_', ' ', ucfirst((string) $ticket->category))
                : '—',
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'evidenceCount' => (int) $ticket->evidence_count,
            'updatedAt' => optional($ticket->source_updated_at)?->toIso8601String(),
            'dueAt' => $due?->toIso8601String(),
            'isOverdue' => $this->isOverdue($ticket),
            'canEdit' => $status === 'draft' || in_array($status, self::REVISION_STATUSES, true),
            'canDelete' => $status === 'draft',
            'isRevision' => in_array($status, self::REVISION_STATUSES, true),
        ];
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
        if (! $due) {
            return false;
        }

        return now()->greaterThan($due);
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

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'assigned' => 'Assigned',
            'returned' => 'Returned',
            'ownership_rejected' => 'Ownership rejected',
            'in_mitigation' => 'In mitigation',
            'closed' => 'Closed',
            'resolved' => 'Resolved',
            default => $status ? str_replace('_', ' ', ucfirst($status)) : '—',
        };
    }
}
