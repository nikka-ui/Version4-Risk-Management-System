<?php

namespace App\Services;

use App\Models\RiskTicket;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 26: RMO queue lists from Postgres (mirrors Express officer queues).
 */
class OfficerQueueService
{
    public function __construct(
        private readonly OfficerDashboardService $dashboard,
    ) {}

    /**
     * @return array{
     *   filter: string,
     *   activeNav: string,
     *   title: string,
     *   desc: string,
     *   emptyMessage: string,
     *   stats: array<string, int>,
     *   tickets: list<array<string, mixed>>
     * }
     */
    public function listForFilter(string $filter): array
    {
        $filter = $this->normalizeFilter($filter);
        $meta = $this->pageMeta($filter);
        $all = $this->dashboard->tickets();
        $stats = $this->dashboard->statsForTickets($all);
        $filtered = $this->applyFilter($all, $filter);

        return [
            'filter' => $filter,
            'activeNav' => $meta['activeNav'],
            'title' => $meta['title'],
            'desc' => $meta['desc'],
            'emptyMessage' => $meta['emptyMessage'],
            'stats' => $stats,
            'tickets' => $filtered->map(fn (RiskTicket $t) => $this->listRow($t))->values()->all(),
        ];
    }

    private function normalizeFilter(string $filter): string
    {
        return match ($filter) {
            'tickets', 'register', 'overdue', 'monitoring', 'action-plans' => $filter === 'register' ? 'tickets' : $filter,
            default => 'tickets',
        };
    }

    /**
     * @return array{activeNav: string, title: string, desc: string, emptyMessage: string}
     */
    private function pageMeta(string $filter): array
    {
        return match ($filter) {
            'overdue' => [
                'activeNav' => 'overdue',
                'title' => 'Overdue & SLA',
                'desc' => 'Tickets past their target date or SLA threshold.',
                'emptyMessage' => 'No overdue tickets.',
            ],
            'monitoring' => [
                'activeNav' => 'monitoring',
                'title' => 'Active monitoring',
                'desc' => 'All active organizational risks across the department ownership lifecycle.',
                'emptyMessage' => 'No active tickets to monitor.',
            ],
            'action-plans' => [
                'activeNav' => 'action-plans',
                'title' => 'Department action plans',
                'desc' => 'Review action plans submitted by owning departments (read-only).',
                'emptyMessage' => 'No department action plans to review.',
            ],
            default => [
                'activeNav' => 'register',
                'title' => 'Organization risk register',
                'desc' => 'Complete view of organizational risk tickets (excluding drafts).',
                'emptyMessage' => 'No submitted tickets yet.',
            ],
        };
    }

    /**
     * @param  Collection<int, RiskTicket>  $tickets
     * @return Collection<int, RiskTicket>
     */
    private function applyFilter(Collection $tickets, string $filter): Collection
    {
        return match ($filter) {
            'overdue' => $tickets->filter(fn (RiskTicket $t) => $this->dashboard->isTicketOverdue($t))->values(),
            'monitoring' => $tickets->whereIn('status', $this->dashboard->monitoringStatuses())->values(),
            'action-plans' => $tickets->filter(function (RiskTicket $t) {
                if (! in_array((string) $t->status, $this->dashboard->actionPlanStatuses(), true)) {
                    return false;
                }

                return $this->dashboard->ticketHasActionPlan($t);
            })->values(),
            default => $tickets->values(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(RiskTicket $ticket): array
    {
        $status = (string) $ticket->status;

        return [
            'reference' => $ticket->reference,
            'title' => $ticket->title ?: '—',
            'submittedBy' => $ticket->submitted_by,
            'submittedByName' => $ticket->submitted_by_name ?: $ticket->submitted_by ?: '—',
            'department' => $ticket->department ?: '—',
            'category' => $ticket->category ?: '—',
            'categoryLabel' => $ticket->category
                ? str_replace('_', ' ', ucfirst((string) $ticket->category))
                : '—',
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'updatedAt' => optional($ticket->source_updated_at)?->toIso8601String(),
            'isOverdue' => $this->dashboard->isTicketOverdue($ticket),
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
            'under_audit' => 'Under audit',
            'audit_returned' => 'Audit returned',
            'in_mitigation' => 'In mitigation',
            'reopened' => 'Reopened',
            'pending_audit' => 'Pending audit',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            'draft' => 'Draft',
            default => $status ? str_replace('_', ' ', ucfirst($status)) : '—',
        };
    }
}
