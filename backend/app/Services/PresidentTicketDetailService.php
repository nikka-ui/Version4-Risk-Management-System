<?php

namespace App\Services;

use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Support\Departments;
use Illuminate\Support\Carbon;

/**
 * Phase 5 slice 31: President ticket detail (read + capability flags) from Postgres.
 * Decision / comment POSTs stay on Express.
 */
class PresidentTicketDetailService
{
    public function __construct(
        private readonly PresidentTicketService $tickets,
        private readonly PresidentDashboardService $dashboard,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forReference(string $reference): ?array
    {
        $ticket = $this->tickets->findForPresident($reference);
        if (! $ticket) {
            return null;
        }

        $status = (string) $ticket->status;
        $ai = is_array($ticket->ai) ? $ticket->ai : [];
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : null;
        $five = is_array($ticket->five_w1h) ? $ticket->five_w1h : [];
        $riskLevel = $this->dashboard->ticketRiskLevelId($ticket);
        $isOverdue = $this->dashboard->isTicketOverdue($ticket);
        $due = $this->dueAt($ticket);

        $presidentPlan = is_array($ticket->president_plan_decision) ? $ticket->president_plan_decision : null;
        $presidentFinal = is_array($ticket->president_final_decision) ? $ticket->president_final_decision : null;
        $presidentLegacy = is_array($ticket->president_decision) ? $ticket->president_decision : null;

        $needsActionPlan = $this->tickets->needsActionPlanDecision($ticket);
        $needsFinal = $status === 'pending_president_final' && ! $this->hasDecision($presidentFinal);
        $needsDecision = $needsActionPlan || $needsFinal;

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

        $finalResolution = is_array($ticket->final_resolution) ? $ticket->final_resolution : null;
        $rmuRecommendations = is_array($ticket->rmu_recommendations) ? $ticket->rmu_recommendations : [];
        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $auditNotes = isset($payload['auditNotes']) ? (string) $payload['auditNotes'] : '';
        $auditTrail = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $complianceTrail = array_values(array_filter(
            $auditTrail,
            fn ($e) => is_array($e) && preg_match('/compliance|audit/i', (string) ($e['action'] ?? '')),
        ));

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
                'riskLevel' => $riskLevel,
                'riskLevelLabel' => $this->riskLevelLabel($riskLevel),
                'dueAt' => $due?->toIso8601String(),
                'isOverdue' => $isOverdue,
                'submittedAt' => optional($ticket->submitted_at ?? $ticket->source_created_at)?->toIso8601String(),
                'updatedAt' => optional($ticket->source_updated_at)?->toIso8601String(),
                'aiSummary' => $ai['summary'] ?? null,
                'aiLikelihood' => $ai['likelihood'] ?? null,
                'aiImpact' => $ai['impact'] ?? null,
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
                'targetDate' => $plan['targetDate'] ?? ($plan['dueAt'] ?? null),
                'updatedByName' => $plan['updatedByName'] ?? null,
                'updatedAt' => $plan['updatedAt'] ?? null,
            ] : null,
            'finalResolution' => $finalResolution && trim((string) ($finalResolution['summary'] ?? '')) !== '' ? [
                'summary' => (string) ($finalResolution['summary'] ?? ''),
                'outcomes' => (string) ($finalResolution['outcomes'] ?? ''),
                'submittedByName' => (string) ($finalResolution['submittedByName'] ?? '—'),
                'submittedAt' => (string) ($finalResolution['submittedAt'] ?? ''),
            ] : null,
            'rmuRecommendations' => array_values(array_map(fn ($r) => [
                'body' => (string) ($r['body'] ?? ''),
                'authorName' => (string) ($r['authorName'] ?? $r['authorUsername'] ?? '—'),
                'at' => (string) ($r['at'] ?? ''),
            ], array_filter($rmuRecommendations, 'is_array'))),
            'compliance' => [
                'notes' => trim($auditNotes),
                'trail' => array_map(fn ($e) => [
                    'action' => (string) ($e['action'] ?? ''),
                    'detail' => (string) ($e['detail'] ?? ''),
                    'at' => (string) ($e['at'] ?? ''),
                ], array_slice(array_reverse($complianceTrail), 0, 5)),
            ],
            'decisions' => $this->decisionCards($presidentPlan, $presidentFinal, $presidentLegacy),
            'threadComments' => $this->normalizeThreadComments(
                is_array($ticket->thread_comments) ? $ticket->thread_comments : []
            ),
            'capabilities' => [
                'canApproveActionPlan' => $needsActionPlan,
                'canFinalDecision' => $needsFinal,
                'canPostComment' => true,
                'showModals' => $needsActionPlan || $needsFinal,
            ],
            'stats' => $this->dashboard->stats(),
            'activeNav' => $needsDecision
                ? 'pending'
                : ($riskLevel === 'critical' ? 'critical' : 'high'),
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

    /**
     * @return list<array{title:string,decision:string,note:?string,authorName:string,at:string}>
     */
    private function decisionCards(?array $plan, ?array $final, ?array $legacy): array
    {
        $cards = [];
        foreach ([
            [$plan, 'President approval', 'action_plan'],
            [$final, 'President final decision', 'final'],
            [$legacy, 'President approval', null],
        ] as [$decision, $title, $phase]) {
            if (! $this->hasDecision($decision)) {
                continue;
            }
            $cards[] = [
                'title' => ($phase === 'final' || ($decision['phase'] ?? null) === 'final') ? 'President final decision' : $title,
                'decision' => (string) ($decision['decision'] ?? 'Decision'),
                'note' => trim((string) ($decision['note'] ?? '')) ?: null,
                'authorName' => (string) ($decision['authorName'] ?? 'President'),
                'at' => (string) ($decision['at'] ?? ''),
            ];
        }

        return $cards;
    }

    private function hasDecision(?array $decision): bool
    {
        return is_array($decision) && $decision !== [];
    }

    private function dueAt(RiskTicket $ticket): ?Carbon
    {
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        $raw = $plan['targetDate'] ?? ($plan['dueAt'] ?? null);
        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                // fall through
            }
        }

        return $ticket->mitigation_due_at;
    }

    private function riskLevelLabel(string $levelId): string
    {
        return match ($levelId) {
            'critical' => 'Critical',
            'high' => 'High',
            'moderate' => 'Moderate',
            'low' => 'Low',
            default => ucfirst($levelId),
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending_president' => 'Awaiting President Approval',
            'pending_president_final' => 'Awaiting President Final Decision',
            default => $status ? str_replace('_', ' ', ucfirst($status)) : '—',
        };
    }
}
