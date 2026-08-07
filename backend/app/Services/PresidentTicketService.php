<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Departments;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 slice 6: presidential action-plan and final decisions (Postgres only).
 */
class PresidentTicketService
{
    public function findForPresident(string $reference): ?RiskTicket
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->first();

        if (! $ticket || ! $this->isPresidentVisible($ticket)) {
            return null;
        }

        return $ticket;
    }

    public function recordDecision(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        if ($user->role !== 'president') {
            abort(403, 'Forbidden.');
        }

        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $presidentReviewPhase = $payload['presidentReviewPhase'] ?? null;

        $isFinalPhase = $ticket->status === 'pending_president_final' || $presidentReviewPhase === 'final';
        $isActionPlanPhase = $isFinalPhase
            ? false
            : ($ticket->status === 'pending_president' || $this->needsActionPlanDecision($ticket));

        if (! $isFinalPhase && ! $isActionPlanPhase) {
            throw ValidationException::withMessages([
                'status' => ['This ticket is not awaiting a presidential decision.'],
            ]);
        }

        $existingDecision = $isFinalPhase ? $ticket->president_final_decision : $ticket->president_plan_decision;
        if (is_array($existingDecision) && (
            ($existingDecision['decisionId'] ?? null) === 'approve'
            || ($isFinalPhase && $existingDecision !== [])
        )) {
            throw ValidationException::withMessages([
                'decision' => ['A presidential decision has already been recorded for this review stage.'],
            ]);
        }

        $decision = strtolower(trim((string) ($input['decision'] ?? '')));
        $allowed = $isFinalPhase ? ['close', 'return', 'approve'] : ['approve', 'reject', 'return', 'decline'];
        if (! in_array($decision, $allowed, true)) {
            $choices = implode(', ', array_values(array_filter($allowed, static fn ($d) => $d !== 'decline')));
            throw ValidationException::withMessages([
                'decision' => ["Invalid decision. Choose {$choices}."],
            ]);
        }

        $normalizedDecision = $decision === 'decline' ? 'reject' : $decision;
        $note = trim((string) ($input['note'] ?? $input['comment'] ?? ''));
        if (in_array($normalizedDecision, ['reject', 'return'], true) && $note === '') {
            throw ValidationException::withMessages([
                'note' => ['A reason is required when rejecting or returning a ticket.'],
            ]);
        }

        $now = now();
        $decisionLabels = [
            'approve' => 'Approved',
            'reject' => 'Declined',
            'return' => 'Returned',
            'close' => 'Closed',
        ];

        $decisionRecord = [
            'decision' => $decisionLabels[$normalizedDecision] ?? $normalizedDecision,
            'decisionId' => $normalizedDecision,
            'note' => $note !== '' ? $note : null,
            'authorUsername' => $user->username,
            'authorName' => $user->name ?: $user->username,
            'authorPosition' => $user->position,
            'at' => $now->toIso8601String(),
            'phase' => $isFinalPhase ? 'final' : 'action_plan',
        ];

        $status = $ticket->status;
        $actionPlan = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $updates = [
            'source_updated_at' => $now,
        ];

        if ($isFinalPhase) {
            $updates['president_final_decision'] = $decisionRecord;
            if (in_array($normalizedDecision, ['close', 'approve'], true)) {
                $status = 'closed';
                $audit = $this->appendPresidentAudit($audit, 'President approved', $note ?: 'President approved closure after accomplishment review.', $user, $now);
                $audit = $this->appendPresidentAudit($audit, 'Ticket closed', 'Ticket closed following presidential final decision.', $user, $now);
            } elseif ($normalizedDecision === 'return') {
                $status = 'in_mitigation';
                $audit = $this->appendPresidentAudit($audit, 'President returned ticket', $note ?: 'Returned to department for further implementation.', $user, $now);
            }
        } else {
            $updates['president_plan_decision'] = $decisionRecord;
            if ($normalizedDecision === 'approve') {
                $status = 'in_mitigation';
                $actionPlan['publishedToReporterAt'] = $actionPlan['publishedToReporterAt'] ?? $now->toIso8601String();
                $actionPlan['submittedForReviewAt'] = $actionPlan['submittedForReviewAt'] ?? $now->toIso8601String();
                $updates['action_plan'] = $actionPlan;
                $audit = $this->appendPresidentAudit($audit, 'President approved', $note ?: 'Action plan approved. Released to the reporter for implementation.', $user, $now);
            } elseif ($normalizedDecision === 'reject') {
                $status = 'in_progress';
                $updates['action_plan'] = null;
                $audit = $this->appendPresidentAudit($audit, 'President declined action plan', $note ?: 'Action plan declined by the President.', $user, $now);
            } elseif ($normalizedDecision === 'return') {
                $status = 'in_progress';
                $actionPlan['publishedToReporterAt'] = null;
                $actionPlan['submittedForReviewAt'] = null;
                $updates['action_plan'] = $actionPlan;
                $audit = $this->appendPresidentAudit($audit, 'President returned action plan', $note ?: 'Returned to department for revision.', $user, $now);
            }
        }

        unset($payload['presidentReviewPhase']);
        if ($payload !== []) {
            $updates['payload'] = $payload;
        } else {
            $updates['payload'] = null;
        }

        $updates['status'] = $status;
        $updates['audit_trail'] = $audit;

        $ticket->fill($updates);
        $ticket->save();

        return $ticket->fresh();
    }

    private function isPresidentVisible(RiskTicket $ticket): bool
    {
        return in_array(
            Departments::riskLevelId(
                is_array($ticket->ai) ? $ticket->ai : null,
                $ticket->likelihood,
                $ticket->impact,
            ),
            ['high', 'critical'],
            true,
        );
    }

    private function needsActionPlanDecision(RiskTicket $ticket): bool
    {
        if (! Departments::requiresPresidentApproval(
            is_array($ticket->ai) ? $ticket->ai : null,
            $ticket->likelihood,
            $ticket->impact,
        )) {
            return false;
        }

        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        if (trim((string) ($plan['summary'] ?? '')) === '') {
            return false;
        }

        if ($ticket->president_plan_decision) {
            return false;
        }

        if ($ticket->status === 'pending_president_final') {
            return false;
        }

        if (in_array($ticket->status, ['closed', 'resolved', 'draft'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $audit
     * @return list<array<string, mixed>>
     */
    private function appendPresidentAudit(array $audit, string $action, string $detail, User $user, Carbon $at): array
    {
        $audit[] = [
            'id' => 'aud-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $at->toIso8601String(),
            'action' => $action,
            'detail' => $detail,
            'actorUsername' => $user->username,
            'actorName' => $user->name ?: $user->username,
            'actorRole' => 'president',
        ];

        return $audit;
    }
}
