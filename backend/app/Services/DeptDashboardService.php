<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Departments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 22–23: Department Head dashboard + queue lists from Laravel Postgres.
 * Mirrors docker/web getDeptHeadStats + listTicketsForDeptHead* (read path).
 */
class DeptDashboardService
{
    /** @var list<string> */
    private const VISIBLE_STATUSES = [
        'assigned',
        'in_progress',
        'ownership_rejected',
        'under_audit',
        'audit_returned',
        'pending_president',
        'in_mitigation',
        'pending_audit',
        'pending_president_final',
        'resolved',
        'closed',
        'reopened',
    ];

    /** @var list<string> */
    private const INBOX_STATUSES = ['assigned'];

    /** @var list<string> */
    private const ACTIVE_STATUSES = ['in_progress'];

    /** @var list<string> */
    private const CLOSURE_STATUSES = ['pending_audit'];

    /** @var list<string> */
    private const EXECUTION_STATUSES = ['in_progress', 'reopened'];

    /** @var list<string> */
    private const OVERDUE_EXCLUDED = [
        'pending_audit',
        'pending_president_final',
        'resolved',
        'closed',
    ];

    /** @var list<string> */
    private const REVISION_STATUSES = ['returned', 'ownership_rejected'];

    /** @var list<string> */
    private const QUEUE_FILTERS = [
        'inbox',
        'active',
        'drafts',
        'returned',
        'overdue',
        'closure',
        'tickets',
    ];

    /**
     * @return array{stats: array<string, int>, recent: list<array<string, mixed>>}
     */
    public function forUser(User $user): array
    {
        $tickets = $this->ticketsFor($user);
        $stats = $this->stats($user, $tickets);
        $recent = $tickets->take(6)->map(fn (RiskTicket $t) => $this->listRow($t, $user))->values()->all();

        return [
            'stats' => $stats,
            'recent' => $recent,
        ];
    }

    /**
     * Phase 5 slice 23: filtered queue list for Department Head console.
     *
     * @return array{
     *   filter: string,
     *   title: string,
     *   desc: string,
     *   activeNav: string,
     *   emptyMessage: string,
     *   stats: array<string, int>,
     *   tickets: list<array<string, mixed>>,
     *   showDueColumn: bool,
     *   variant: string
     * }
     */
    public function listForUser(User $user, string $filter): array
    {
        $filter = $this->normalizeFilter($filter);
        $tickets = $this->ticketsFor($user);
        $stats = $this->stats($user, $tickets);
        $filtered = $this->applyFilter($tickets, $user, $filter);
        $meta = $this->pageMeta($filter);

        return [
            'filter' => $filter,
            'title' => $meta['title'],
            'desc' => $meta['desc'],
            'activeNav' => $meta['activeNav'],
            'emptyMessage' => $meta['emptyMessage'],
            'stats' => $stats,
            'tickets' => $filtered->map(fn (RiskTicket $t) => $this->listRow($t, $user))->values()->all(),
            'showDueColumn' => $filter === 'overdue',
            'variant' => $meta['variant'],
        ];
    }

    private function normalizeFilter(string $filter): string
    {
        $filter = strtolower(trim($filter));

        return in_array($filter, self::QUEUE_FILTERS, true) ? $filter : 'tickets';
    }

    /**
     * @param  Collection<int, RiskTicket>  $tickets
     * @return Collection<int, RiskTicket>
     */
    private function applyFilter(Collection $tickets, User $user, string $filter): Collection
    {
        return match ($filter) {
            'inbox' => $tickets->whereIn('status', self::INBOX_STATUSES)->values(),
            'active' => $tickets->filter(
                fn (RiskTicket $t) => in_array((string) $t->status, self::ACTIVE_STATUSES, true)
                    && ! $this->returnedByPresident($t)
            )->values(),
            'drafts' => $tickets
                ->filter(
                    fn (RiskTicket $t) => $this->hasDraftActionPlan($t)
                        && (($t->ownership['ownerUsername'] ?? null) === $user->username)
                        && ! $this->returnedByPresident($t)
                )
                ->sortByDesc(fn (RiskTicket $t) => $this->draftSavedAt($t)?->timestamp ?? 0)
                ->values(),
            'returned' => $tickets
                ->filter(fn (RiskTicket $t) => $this->returnedByPresident($t) && $this->ownedOrUnownedBy($t, $user))
                ->sortByDesc(fn (RiskTicket $t) => $this->returnedAt($t)?->timestamp ?? 0)
                ->values(),
            'overdue' => $tickets->filter(fn (RiskTicket $t) => $this->isOverdue($t))->values(),
            'closure' => $tickets->whereIn('status', self::CLOSURE_STATUSES)->values(),
            default => $tickets->values(),
        };
    }

    /**
     * @return array{title: string, desc: string, activeNav: string, emptyMessage: string, variant: string}
     */
    private function pageMeta(string $filter): array
    {
        return match ($filter) {
            'inbox' => [
                'title' => 'Ownership inbox',
                'desc' => 'Risk tickets the AI routed to your department. Accept ownership, reject with a reason, or reassign to another department.',
                'activeNav' => 'inbox',
                'emptyMessage' => 'No tickets are awaiting your ownership decision.',
                'variant' => 'queue',
            ],
            'active' => [
                'title' => 'In progress',
                'desc' => 'Tickets you own before the action plan is sent — accept ownership, build the mitigation plan, and publish it to the reporter.',
                'activeNav' => 'active',
                'emptyMessage' => 'You have no tickets in progress.',
                'variant' => 'queue',
            ],
            'drafts' => [
                'title' => 'Action plan drafts',
                'desc' => 'Saved action plans you have not sent yet. Open a ticket to continue editing, then submit when ready.',
                'activeNav' => 'drafts',
                'emptyMessage' => 'No action plan drafts. Save a draft from a ticket you own in progress.',
                'variant' => 'drafts',
            ],
            'returned' => [
                'title' => 'Returned by President',
                'desc' => 'High/Critical action plans the President returned for revision. Open each ticket to review the feedback, update the plan, and resubmit.',
                'activeNav' => 'returned',
                'emptyMessage' => 'No tickets are currently returned by the President.',
                'variant' => 'returned',
            ],
            'overdue' => [
                'title' => 'Overdue tickets',
                'desc' => 'Department tickets past the mitigation target date. These may be waiting on reporter implementation or still need your follow-up.',
                'activeNav' => 'overdue',
                'emptyMessage' => 'No overdue tickets. All active department tickets are within their target dates.',
                'variant' => 'queue',
            ],
            'closure' => [
                'title' => 'Pending closure',
                'desc' => 'Reporter accomplishment reports awaiting your review and ticket closure.',
                'activeNav' => 'closure',
                'emptyMessage' => 'No tickets are awaiting closure.',
                'variant' => 'queue',
            ],
            default => [
                'title' => 'All department tickets',
                'desc' => 'Every risk ticket associated with your department across the full lifecycle.',
                'activeNav' => 'tickets',
                'emptyMessage' => 'No department tickets yet.',
                'variant' => 'queue',
            ],
        };
    }

    /**
     * @return Collection<int, RiskTicket>
     */
    private function ticketsFor(User $user): Collection
    {
        return RiskTicket::query()
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->orderByDesc('source_updated_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (RiskTicket $t) => $this->isDeptHeadTicket($t, $user))
            ->values();
    }

    private function isDeptHeadTicket(RiskTicket $ticket, User $user): bool
    {
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        if (($ownership['ownerUsername'] ?? null) === $user->username) {
            return true;
        }
        if (Departments::match($user->department, $ticket->department)) {
            return true;
        }
        if (Departments::match($user->department, $ownership['ownerDepartment'] ?? null)) {
            return true;
        }

        return false;
    }

    /**
     * @param  Collection<int, RiskTicket>  $tickets
     * @return array<string, int>
     */
    private function stats(User $user, Collection $tickets): array
    {
        $returned = $tickets->filter(fn (RiskTicket $t) => $this->returnedByPresident($t)
            && $this->ownedOrUnownedBy($t, $user))->count();

        $unread = Notification::query()
            ->where('recipient_username', $user->username)
            ->whereNull('read_at')
            ->count();

        return [
            'total' => $tickets->count(),
            'inbox' => $tickets->whereIn('status', self::INBOX_STATUSES)->count(),
            'active' => $tickets->filter(
                fn (RiskTicket $t) => in_array((string) $t->status, self::ACTIVE_STATUSES, true)
                    && ! $this->returnedByPresident($t)
            )->count(),
            'drafts' => $tickets->filter(
                fn (RiskTicket $t) => $this->hasDraftActionPlan($t)
                    && (($t->ownership['ownerUsername'] ?? null) === $user->username)
                    && ! $this->returnedByPresident($t)
            )->count(),
            'returned' => $returned,
            'pendingClosure' => $tickets->whereIn('status', self::CLOSURE_STATUSES)->count(),
            'awaitingPresident' => $tickets->where('status', 'pending_president')->count(),
            'rejected' => $tickets->where('status', 'ownership_rejected')->count(),
            'overdue' => $tickets->filter(fn (RiskTicket $t) => $this->isOverdue($t))->count(),
            'closed' => $tickets->whereIn('status', ['closed', 'resolved'])->count(),
            'unreadNotifications' => $unread,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(RiskTicket $ticket, User $user): array
    {
        $status = (string) $ticket->status;
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        $state = (string) ($ownership['state'] ?? 'unassigned');
        $due = $this->dueAt($ticket);
        $draftSaved = $this->draftSavedAt($ticket);
        $return = $this->returnDecision($ticket);
        $reason = trim((string) ($return['note'] ?? ''));
        if (strlen($reason) > 80) {
            $reason = substr($reason, 0, 80).'…';
        }

        return [
            'reference' => $ticket->reference,
            'title' => $ticket->title ?: '—',
            'submittedBy' => $ticket->submitted_by,
            'submittedByName' => $ticket->submitted_by_name ?: $ticket->submitted_by ?: '—',
            'category' => $ticket->category ?: '—',
            'categoryLabel' => $ticket->category
                ? str_replace('_', ' ', ucfirst((string) $ticket->category))
                : '—',
            'ownershipState' => $state,
            'ownershipLabel' => match ($state) {
                'pending' => 'Awaiting acceptance',
                'accepted' => 'Owned',
                'rejected' => 'Rejected',
                default => 'Unassigned',
            },
            'ownershipTone' => match ($state) {
                'pending' => 'info',
                'accepted' => 'rmo',
                'rejected' => 'warn',
                default => 'muted',
            },
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'updatedAt' => optional($ticket->source_updated_at)?->toIso8601String(),
            'dueAt' => $due?->toIso8601String(),
            'draftSavedAt' => $draftSaved?->toIso8601String(),
            'returnReason' => $reason !== '' ? $reason : '—',
            'returnedAt' => optional($this->returnedAt($ticket))?->toIso8601String(),
            'isOverdue' => $this->isOverdue($ticket),
            'returnedByPresident' => $this->returnedByPresident($ticket),
        ];
    }

    private function ownedOrUnownedBy(RiskTicket $ticket, User $user): bool
    {
        $owner = $ticket->ownership['ownerUsername'] ?? null;

        return ! $owner || $owner === $user->username;
    }

    private function returnedByPresident(RiskTicket $ticket): bool
    {
        $plan = is_array($ticket->president_plan_decision) ? $ticket->president_plan_decision : null;
        if (
            $plan
            && in_array((string) ($plan['decisionId'] ?? ''), ['return', 'reject'], true)
            && (string) $ticket->status === 'in_progress'
        ) {
            return true;
        }

        $final = is_array($ticket->president_final_decision) ? $ticket->president_final_decision : null;
        if (
            $final
            && ($final['decisionId'] ?? null) === 'return'
            && in_array((string) $ticket->status, ['in_mitigation', 'in_progress', 'reopened'], true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function returnDecision(RiskTicket $ticket): ?array
    {
        $plan = is_array($ticket->president_plan_decision) ? $ticket->president_plan_decision : null;
        if ($plan && in_array((string) ($plan['decisionId'] ?? ''), ['return', 'reject'], true)) {
            return $plan;
        }
        $final = is_array($ticket->president_final_decision) ? $ticket->president_final_decision : null;
        if ($final && ($final['decisionId'] ?? null) === 'return') {
            return $final;
        }

        return null;
    }

    private function returnedAt(RiskTicket $ticket): ?Carbon
    {
        $decision = $this->returnDecision($ticket);
        $raw = $decision['at'] ?? null;
        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                // fall through
            }
        }

        return $ticket->source_updated_at;
    }

    private function hasDraftActionPlan(RiskTicket $ticket): bool
    {
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        if (trim((string) ($plan['summary'] ?? '')) === '') {
            return false;
        }
        if (! empty($plan['publishedToReporterAt']) || ! empty($plan['submittedForReviewAt'])) {
            return false;
        }
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        if (($ownership['state'] ?? null) !== 'accepted') {
            return false;
        }

        return in_array((string) $ticket->status, self::EXECUTION_STATUSES, true);
    }

    private function draftSavedAt(RiskTicket $ticket): ?Carbon
    {
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        foreach (['draftUpdatedAt', 'updatedAt', 'savedAt'] as $key) {
            $raw = $plan[$key] ?? null;
            if (is_string($raw) && $raw !== '') {
                try {
                    return Carbon::parse($raw);
                } catch (\Throwable) {
                    // continue
                }
            }
        }

        return $ticket->source_updated_at;
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

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'assigned' => 'Assigned',
            'in_progress' => 'In progress',
            'ownership_rejected' => 'Ownership rejected',
            'pending_president' => 'Awaiting President',
            'pending_audit' => 'Pending closure',
            'pending_president_final' => 'Awaiting President (final)',
            'in_mitigation' => 'In mitigation',
            'reopened' => 'Reopened',
            'closed' => 'Closed',
            'resolved' => 'Resolved',
            default => $status ? str_replace('_', ' ', ucfirst($status)) : '—',
        };
    }
}
