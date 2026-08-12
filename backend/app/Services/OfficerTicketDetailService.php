<?php

namespace App\Services;

use App\Models\Accomplishment;
use App\Models\Department;
use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Support\Departments;
use Illuminate\Support\Carbon;

/**
 * Phase 5 slice 27: RMO ticket detail (read + capability flags) from Postgres.
 * Thread-comment / reopen POSTs stay on Express.
 */
class OfficerTicketDetailService
{
    public function __construct(
        private readonly OfficerDashboardService $dashboard,
        private readonly OfficerTicketService $tickets,
    ) {}

    /**
     * @return array{
     *   ticket: array<string, mixed>,
     *   fiveW1H: array<string, string>,
     *   attachments: list<array<string, mixed>>,
     *   actionPlan: array<string, mixed>|null,
     *   accomplishment: array<string, mixed>|null,
     *   closure: array<string, mixed>|null,
     *   threadComments: list<array<string, mixed>>,
     *   departments: list<string>,
     *   capabilities: array<string, bool>,
     *   stats: array<string, int>,
     *   activeNav: string
     * }|null
     */
    public function forReference(string $reference): ?array
    {
        $ticket = $this->tickets->findForOfficer($reference);
        if (! $ticket) {
            return null;
        }

        $status = (string) $ticket->status;
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        $ai = is_array($ticket->ai) ? $ticket->ai : [];
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : null;
        $five = is_array($ticket->five_w1h) ? $ticket->five_w1h : [];
        $closure = is_array($ticket->closure) ? $ticket->closure : null;
        $ownerState = (string) ($ownership['state'] ?? ($status === 'assigned' ? 'pending' : 'unassigned'));
        $riskLevel = Departments::riskLevelId($ai, $ticket->likelihood, $ticket->impact);
        $due = $this->dueAt($ticket);
        $isOverdue = $this->dashboard->isTicketOverdue($ticket);

        $accomplishment = Accomplishment::query()
            ->where('ticket_ref', $ticket->reference)
            ->orderByDesc('submitted_at')
            ->first();

        $attachments = RiskAttachment::query()
            ->where('ticket_ref', $ticket->reference)
            ->orderByDesc('uploaded_at')
            ->get()
            ->map(fn (RiskAttachment $a) => [
                'id' => $a->id,
                'name' => $a->original_name ?: 'file',
                'size' => (int) $a->size_bytes,
                'uploadedAt' => optional($a->uploaded_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        $departments = Department::query()
            ->where('active', true)
            ->where('status', '!=', 'inactive')
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->values()
            ->all();

        $stats = $this->dashboard->statsForTickets($this->dashboard->tickets());

        return [
            'ticket' => [
                'reference' => $ticket->reference,
                'title' => $ticket->title ?: '—',
                'description' => $ticket->description ?: '—',
                'location' => $ticket->location ?: '—',
                'status' => $status,
                'statusLabel' => $this->statusLabel($status),
                'category' => $ticket->category ?: '—',
                'categoryLabel' => $ticket->category
                    ? str_replace('_', ' ', ucfirst((string) $ticket->category))
                    : '—',
                'department' => $ticket->department ?: '—',
                'reporterDepartment' => $ticket->reporter_department ?: '—',
                'submittedBy' => $ticket->submitted_by,
                'submittedByName' => $ticket->submitted_by_name ?: $ticket->submitted_by ?: '—',
                'likelihood' => (int) ($ticket->likelihood ?: 0),
                'impact' => (int) ($ticket->impact ?: 0),
                'riskScore' => $ticket->risk_score
                    ?: (($ticket->likelihood && $ticket->impact)
                        ? ((int) $ticket->likelihood * (int) $ticket->impact)
                        : null),
                'riskLevel' => $riskLevel,
                'riskLevelLabel' => $this->riskLevelLabel($riskLevel),
                'dueAt' => $due?->toIso8601String(),
                'isOverdue' => $isOverdue,
                'submittedAt' => optional($ticket->submitted_at ?? $ticket->created_at)?->toIso8601String(),
                'updatedAt' => optional($ticket->source_updated_at)?->toIso8601String(),
                'ownershipState' => $ownerState,
                'ownershipLabel' => match ($ownerState) {
                    'pending' => 'Awaiting department acceptance',
                    'accepted' => 'Owned by department',
                    'rejected' => 'Returned by department',
                    default => 'Unassigned',
                },
                'ownershipTone' => match ($ownerState) {
                    'pending' => 'info',
                    'accepted' => 'rmo',
                    'rejected' => 'warn',
                    default => 'muted',
                },
                'ownerName' => $ownership['ownerName'] ?? null,
                'aiSummary' => $ai['summary'] ?? ($ai['rationale'] ?? null),
                'aiConfidence' => $ai['confidence'] ?? null,
                'aiLikelihood' => $ai['likelihood'] ?? null,
                'aiImpact' => $ai['impact'] ?? null,
                'aiManualReview' => ! empty($ai['manualReviewRequired']),
                'hasAi' => $ai !== [],
            ],
            'fiveW1H' => [
                'what' => (string) ($five['what'] ?? ''),
                'why' => (string) ($five['why'] ?? ''),
                'where' => (string) ($five['where'] ?? ''),
                'when' => (string) ($five['when'] ?? ''),
                'who' => (string) ($five['who'] ?? ''),
                'how' => (string) ($five['how'] ?? ''),
            ],
            'attachments' => $attachments,
            'actionPlan' => $plan && trim((string) ($plan['summary'] ?? '')) !== '' ? [
                'summary' => (string) ($plan['summary'] ?? ''),
                'steps' => array_values(array_filter(array_map(
                    fn ($s) => trim((string) $s),
                    is_array($plan['steps'] ?? null) ? $plan['steps'] : [],
                ))),
                'targetDate' => isset($plan['targetDate']) ? substr((string) $plan['targetDate'], 0, 10) : '',
                'version' => (int) ($plan['version'] ?? 1),
                'updatedAt' => $plan['updatedAt'] ?? null,
            ] : null,
            'accomplishment' => $accomplishment ? [
                'summary' => $accomplishment->summary ?: '—',
                'outcomes' => $accomplishment->outcomes ?: '—',
                'submittedByName' => $accomplishment->submitted_by_name ?: $accomplishment->submitted_by ?: '—',
                'submittedAt' => optional($accomplishment->submitted_at)?->toIso8601String(),
                'evidence' => array_values(array_map(function ($e) {
                    if (! is_array($e)) {
                        return ['name' => '—'];
                    }

                    return ['name' => (string) ($e['name'] ?? $e['originalName'] ?? '—')];
                }, is_array($accomplishment->evidence) ? $accomplishment->evidence : [])),
            ] : null,
            'closure' => $closure ? [
                'notes' => (string) ($closure['notes'] ?? 'Ticket closed.'),
                'closedByName' => (string) ($closure['closedByName'] ?? $closure['closedBy'] ?? 'Department'),
                'closedAt' => (string) ($closure['closedAt'] ?? ''),
            ] : null,
            'threadComments' => $this->normalizeThreadComments(
                is_array($ticket->thread_comments) ? $ticket->thread_comments : []
            ),
            'departments' => $departments,
            'capabilities' => [
                'canReopen' => in_array($status, ['closed', 'resolved'], true),
                'canPostComment' => true,
            ],
            'stats' => $stats,
            'activeNav' => 'register',
        ];
    }

    /**
     * @param  list<mixed>  $comments
     * @return list<array<string, mixed>>
     */
    private function normalizeThreadComments(array $comments): array
    {
        $rows = [];
        foreach ($comments as $c) {
            if (! is_array($c) || empty($c['id'])) {
                continue;
            }
            $rows[] = [
                'id' => (string) $c['id'],
                'body' => (string) ($c['body'] ?? ''),
                'authorName' => (string) ($c['authorName'] ?? $c['authorUsername'] ?? 'Unknown'),
                'authorUsername' => (string) ($c['authorUsername'] ?? ''),
                'roleLabel' => (string) ($c['authorPosition'] ?? $c['roleLabel'] ?? $c['authorRole'] ?? ''),
                'kind' => (string) ($c['kind'] ?? 'comment'),
                'parentId' => isset($c['parentId']) && $c['parentId'] ? (string) $c['parentId'] : null,
                'at' => (string) ($c['at'] ?? ''),
                'editedAt' => isset($c['editedAt']) ? (string) $c['editedAt'] : null,
            ];
        }

        usort($rows, function (array $a, array $b) {
            return strcmp($a['at'] ?? '', $b['at'] ?? '');
        });

        return $rows;
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

    private function riskLevelLabel(?string $level): string
    {
        return match ($level) {
            'low' => 'Low',
            'moderate' => 'Moderate',
            'high' => 'High',
            'critical' => 'Critical',
            default => $level ? ucfirst($level) : '—',
        };
    }
}
