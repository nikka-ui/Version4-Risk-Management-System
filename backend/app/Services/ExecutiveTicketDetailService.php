<?php

namespace App\Services;

use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Support\Departments;

/**
 * Phase 6 slice 4 + Phase 7 slice 10: Executive ticket detail (read + comment POST) from Postgres.
 */
class ExecutiveTicketDetailService
{
    public function __construct(
        private readonly ExecutiveDashboardService $dashboard,
        private readonly OfficerDashboardService $overdueHelper,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forReference(string $reference): ?array
    {
        $ticket = RiskTicket::query()
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->where('reference', $reference)
            ->first();

        if (! $ticket) {
            return null;
        }

        $status = (string) $ticket->status;
        $ai = is_array($ticket->ai) ? $ticket->ai : [];
        $five = is_array($ticket->five_w1h) ? $ticket->five_w1h : [];
        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $riskLevel = Departments::riskLevelId($ai, $ticket->likelihood, $ticket->impact);
        $isOverdue = $this->overdueHelper->isTicketOverdue($ticket);
        $officerNotes = trim((string) ($payload['officerNotes'] ?? $ticket->mitigation_approach ?? ''));

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

        $statsPayload = $this->dashboard->data();

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
                'submittedBy' => $ticket->submitted_by,
                'submittedByName' => $ticket->submitted_by_name ?: $ticket->submitted_by ?: '—',
                'likelihood' => (int) ($ticket->likelihood ?: 0),
                'impact' => (int) ($ticket->impact ?: 0),
                'riskScore' => $ticket->risk_score
                    ?: (($ticket->likelihood && $ticket->impact)
                        ? ((int) $ticket->likelihood * (int) $ticket->impact)
                        : null),
                'riskLevel' => $riskLevel,
                'riskLevelLabel' => match ($riskLevel) {
                    'critical' => 'Critical',
                    'high' => 'High',
                    'moderate' => 'Moderate',
                    default => 'Low',
                },
                'isOverdue' => $isOverdue,
                'submittedAt' => optional($ticket->submitted_at ?? $ticket->source_created_at)?->toIso8601String(),
                'updatedAt' => optional($ticket->source_updated_at)?->toIso8601String(),
                'aiSummary' => $ai['summary'] ?? null,
                'aiLikelihood' => $ai['likelihood'] ?? null,
                'aiImpact' => $ai['impact'] ?? null,
                'aiConfidence' => $ai['confidence'] ?? null,
                'hasAi' => $ai !== [],
                'officerNotes' => $officerNotes !== '' ? $officerNotes : null,
                'mitigationDueAt' => optional($ticket->mitigation_due_at)?->toIso8601String(),
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
            'threadComments' => $this->normalizeThreadComments(
                is_array($ticket->thread_comments) ? $ticket->thread_comments : []
            ),
            'capabilities' => [
                'canPostComment' => $status !== 'draft',
            ],
            'stats' => $statsPayload['stats'],
            'activeNav' => 'overview',
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
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['at'] ?? '', $b['at'] ?? ''));

        return $rows;
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
}
