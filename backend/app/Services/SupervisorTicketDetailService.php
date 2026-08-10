<?php

namespace App\Services;

use App\Models\Accomplishment;
use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use Illuminate\Support\Carbon;

/**
 * Phase 5 slice 9: read-only Ticket Reporter ticket detail from Postgres.
 */
class SupervisorTicketDetailService
{
    private const REVISION_STATUSES = ['returned', 'ownership_rejected'];

    /**
     * @return array{redirect_edit: true, reference: string}|array{
     *   redirect_edit: false,
     *   ticket: array<string, mixed>,
     *   attachments: list<array<string, mixed>>,
     *   fiveW1H: array<string, string>,
     *   timeline: list<array<string, mixed>>,
     *   accomplishment: array<string, mixed>|null
     * }|null
     */
    public function forUsername(string $username, string $reference): ?array
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('submitted_by', $username)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            return null;
        }

        $status = (string) $ticket->status;
        if ($status === 'draft' || in_array($status, self::REVISION_STATUSES, true)) {
            return [
                'redirect_edit' => true,
                'reference' => $ticket->reference,
            ];
        }

        $ai = is_array($ticket->ai) ? $ticket->ai : [];
        $five = is_array($ticket->five_w1h) ? $ticket->five_w1h : [];
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        $dueRaw = $plan['targetDate'] ?? null;
        $due = null;
        if (is_string($dueRaw) && $dueRaw !== '') {
            try {
                $due = Carbon::parse($dueRaw);
            } catch (\Throwable) {
                $due = null;
            }
        }
        $due = $due ?: $ticket->mitigation_due_at;

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

        $accomplishment = Accomplishment::query()
            ->where('ticket_ref', $ticket->reference)
            ->orderByDesc('submitted_at')
            ->first();

        $timeline = [];
        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        foreach (array_slice(array_reverse($audit), 0, 20) as $event) {
            if (! is_array($event)) {
                continue;
            }
            $timeline[] = [
                'action' => (string) ($event['action'] ?? $event['type'] ?? 'Event'),
                'detail' => (string) ($event['detail'] ?? $event['notes'] ?? ''),
                'actorName' => (string) ($event['actorName'] ?? $event['actor'] ?? ''),
                'at' => (string) ($event['at'] ?? $event['createdAt'] ?? ''),
            ];
        }

        return [
            'redirect_edit' => false,
            'ticket' => [
                'reference' => $ticket->reference,
                'title' => $ticket->title ?: '—',
                'status' => $status,
                'statusLabel' => $this->statusLabel($status),
                'description' => $ticket->description ?: '—',
                'location' => $ticket->location ?: '—',
                'category' => $ticket->category ?: '—',
                'categoryLabel' => $ticket->category
                    ? str_replace('_', ' ', ucfirst((string) $ticket->category))
                    : '—',
                'priority' => $ticket->priority ?: ($ai['priority'] ?? null),
                'reporterDepartment' => $ticket->reporter_department ?: '—',
                'department' => $ticket->department
                    ?: ($ai['responsibleDepartment'] ?? 'Pending AI routing'),
                'likelihood' => $ticket->likelihood,
                'impact' => $ticket->impact,
                'riskScore' => $ticket->risk_score
                    ?: (($ticket->likelihood && $ticket->impact)
                        ? ((int) $ticket->likelihood * (int) $ticket->impact)
                        : null),
                'dueAt' => $due?->toIso8601String(),
                'aiConfidence' => $ai['confidence'] ?? null,
                'aiSummary' => $ai['summary'] ?? ($ai['rationale'] ?? null),
                'updatedAt' => optional($ticket->source_updated_at)?->toIso8601String(),
                'submittedAt' => optional($ticket->submitted_at)?->toIso8601String(),
            ],
            'attachments' => $attachments,
            'fiveW1H' => [
                'what' => (string) ($five['what'] ?? ''),
                'why' => (string) ($five['why'] ?? ''),
                'where' => (string) ($five['where'] ?? ''),
                'when' => (string) ($five['when'] ?? ''),
                'who' => (string) ($five['who'] ?? ''),
                'how' => (string) ($five['how'] ?? ''),
            ],
            'timeline' => $timeline,
            'accomplishment' => $accomplishment ? [
                'summary' => $accomplishment->summary ?: '—',
                'outcomes' => $accomplishment->outcomes ?: '—',
                'submittedAt' => optional($accomplishment->submitted_at)?->toIso8601String(),
            ] : null,
        ];
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
