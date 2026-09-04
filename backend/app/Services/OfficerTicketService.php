<?php

namespace App\Services;

use App\Models\Department;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Departments;
use App\Support\Roles;
use Illuminate\Validation\ValidationException;

/**
 * RMO / Compliance officer workflow: reopen, AI route approval, recommend/escalate, H/C validation.
 * Close remains prohibited for these roles.
 */
class OfficerTicketService
{
    public function __construct(
        private readonly WorkflowNotificationService $workflowNotifications,
    ) {}

    public function findForOfficer(string $reference): ?RiskTicket
    {
        return RiskTicket::query()
            ->where('reference', $reference)
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->first();
    }

    public function reopen(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        if (! in_array($user->role, [Roles::RM_OFFICER, Roles::PRESIDENT], true)) {
            abort(403, 'Forbidden.');
        }

        if (! in_array($ticket->status, ['closed', 'resolved'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only closed tickets can be reopened.'],
            ]);
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        $targetRaw = trim((string) ($input['department'] ?? $input['targetDepartment'] ?? $ticket->department ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required to reopen this ticket.'],
            ]);
        }

        $target = $this->resolveActiveDepartment($targetRaw);
        if (! $target) {
            throw ValidationException::withMessages([
                'department' => ['Select a valid department to assign this ticket.'],
            ]);
        }

        $now = now();
        $previousStatus = $ticket->status;
        $fromDepartment = (string) $ticket->department;

        $history = is_array($ticket->reopen_history) ? $ticket->reopen_history : [];
        $history[] = [
            'at' => $now->toIso8601String(),
            'byUsername' => $user->username,
            'byName' => $user->name ?: $user->username,
            'reason' => $reason,
            'fromStatus' => $previousStatus,
            'targetDepartment' => $target,
        ];

        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $payload['reopenCount'] = ((int) ($payload['reopenCount'] ?? 0)) + 1;
        $payload['reopenedAt'] = $now->toIso8601String();
        $payload['reopenedBy'] = $user->username;
        $payload['reopenedByName'] = $user->name ?: $user->username;
        $payload['reopenReason'] = $reason;

        $ownership = [
            'state' => 'pending',
            'ownerUsername' => null,
            'ownerName' => null,
            'ownerDepartment' => $target,
            'assignedAt' => $now->toIso8601String(),
            'acceptedAt' => null,
            'rejectedAt' => null,
            'rejectionReason' => null,
            'reassignedFrom' => $fromDepartment,
        ];

        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = $this->audit($user, 'Ticket reopened', sprintf(
            '%s reopened %s and assigned it to %s. Reason: %s',
            $user->name ?: $user->username,
            $ticket->reference,
            $target,
            $reason,
        ), $now);

        $ticket->fill([
            'reopen_history' => $history,
            'accomplishment_external_id' => null,
            'department' => $target,
            'ownership' => $ownership,
            'status' => 'reopened',
            'closure' => null,
            'audit_trail' => $audit,
            'payload' => $payload,
            'mitigation_due_at' => null,
            'source_updated_at' => $now,
        ]);
        $ticket->save();
        $fresh = $ticket->fresh();
        $this->workflowNotifications->ticketReopened($fresh, $user);

        return $fresh;
    }

    /**
     * Approve AI recommendation (or override department) and open dept ownership.
     *
     * @param  array<string, mixed>  $input
     */
    public function approveAiRoute(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertRmOfficer($user);

        if ((string) $ticket->status !== 'pending_ai_review') {
            throw ValidationException::withMessages([
                'status' => ['This ticket is not awaiting AI routing review.'],
            ]);
        }

        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        $ai = is_array($ticket->ai) ? $ticket->ai : [];
        $recommended = trim((string) ($input['department'] ?? $input['targetDepartment']
            ?? $ownership['recommendedDepartment']
            ?? $ai['matchedDepartment']
            ?? $ai['recommendedDepartment']
            ?? $ai['responsibleDepartment']
            ?? ''));

        $target = $this->resolveActiveDepartment($recommended);
        if (! $target) {
            throw ValidationException::withMessages([
                'department' => ['Select a valid active department for assignment.'],
            ]);
        }

        $now = now();
        $priority = (string) ($ticket->priority ?: ($ai['priority'] ?? 'medium'));
        $ai['routingStatus'] = 'officer_approved';
        $ai['manualReviewRequired'] = false;
        $ai['matchedDepartment'] = $target;

        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = $this->audit($user, 'AI routing approved', "Assigned to {$target} after RMO review.", $now);

        $ticket->fill([
            'status' => 'assigned',
            'department' => $target,
            'priority' => $priority,
            'ai' => $ai,
            'ownership' => [
                'state' => 'pending',
                'ownerUsername' => null,
                'ownerName' => null,
                'ownerDepartment' => $target,
                'assignedAt' => $now->toIso8601String(),
                'acceptedAt' => null,
                'rejectedAt' => null,
                'rejectionReason' => null,
            ],
            'audit_trail' => $audit,
            'routed_at' => $now,
            'response_due_at' => $now->copy()->addHours((int) config('rms.response_sla_hours', 24)),
            'source_updated_at' => $now,
        ]);
        $ticket->save();
        $fresh = $ticket->fresh();
        $this->workflowNotifications->ticketAssigned($fresh, $user);

        return $fresh;
    }

    /**
     * Formal RMO review: recommend or escalate (close still prohibited).
     *
     * @param  array<string, mixed>  $input
     */
    public function recordReviewDecision(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertRmOfficer($user);

        $decision = strtolower(trim((string) ($input['decision'] ?? '')));
        if (! in_array($decision, ['recommend', 'escalate'], true)) {
            throw ValidationException::withMessages([
                'decision' => ['Decision must be recommend or escalate.'],
            ]);
        }

        $note = trim((string) ($input['note'] ?? $input['comment'] ?? $input['reason'] ?? ''));
        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => ['A note is required for review decisions.'],
            ]);
        }

        $now = now();
        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $reviews = is_array($payload['rmoReviews'] ?? null) ? $payload['rmoReviews'] : [];
        $reviews[] = [
            'at' => $now->toIso8601String(),
            'decision' => $decision,
            'note' => $note,
            'byUsername' => $user->username,
            'byName' => $user->name ?: $user->username,
        ];
        $payload['rmoReviews'] = $reviews;

        $escalations = is_array($ticket->escalations) ? $ticket->escalations : [];
        if ($decision === 'escalate') {
            $escalations[] = [
                'at' => $now->toIso8601String(),
                'reason' => $note,
                'byUsername' => $user->username,
                'byName' => $user->name ?: $user->username,
                'toRole' => Roles::PRESIDENT,
            ];
        }

        $action = $decision === 'escalate' ? 'RMO escalated ticket' : 'RMO recommendation recorded';
        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = $this->audit($user, $action, $note, $now);

        $status = $ticket->status;
        if ($decision === 'escalate' && Departments::requiresPresidentApproval(
            is_array($ticket->ai) ? $ticket->ai : null,
            $ticket->likelihood,
            $ticket->impact,
        )) {
            $status = in_array((string) $ticket->status, ['pending_audit', 'under_audit'], true)
                ? 'pending_president_final'
                : 'pending_president';
        }

        $ticket->fill([
            'payload' => $payload,
            'escalations' => $escalations,
            'audit_trail' => $audit,
            'status' => $status,
            'source_updated_at' => $now,
        ]);
        $ticket->save();
        $fresh = $ticket->fresh();

        if ($decision === 'escalate') {
            if ($status === 'pending_president_final') {
                $this->workflowNotifications->pendingPresidentFinal($fresh, $user);
            } else {
                $this->workflowNotifications->reviewEscalated($fresh, $note, $user);
            }
        } else {
            $this->workflowNotifications->reviewRecommended($fresh, $note, $user);
        }

        return $fresh;
    }

    /**
     * RMU / Compliance validates H/C accomplishment → pending_president_final.
     *
     * @param  array<string, mixed>  $input
     */
    public function validateAccomplishment(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertGovernanceRole($user);

        if ((string) $ticket->status !== 'under_audit' || ! $ticket->accomplishment_external_id) {
            throw ValidationException::withMessages([
                'status' => ['This ticket is not awaiting accomplishment validation.'],
            ]);
        }

        if (! Departments::requiresPresidentApproval(
            is_array($ticket->ai) ? $ticket->ai : null,
            $ticket->likelihood,
            $ticket->impact,
        )) {
            throw ValidationException::withMessages([
                'status' => ['Only High/Critical tickets use compliance validation before presidential final.'],
            ]);
        }

        $note = trim((string) ($input['note'] ?? $input['comment'] ?? ''));
        $now = now();
        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = $this->audit(
            $user,
            'Accomplishment validated',
            $note !== '' ? $note : 'Accomplishment validated; forwarded for presidential final decision.',
            $now,
        );

        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $payload['presidentReviewPhase'] = 'final';
        $payload['accomplishmentValidatedAt'] = $now->toIso8601String();
        $payload['accomplishmentValidatedBy'] = $user->username;

        $ticket->fill([
            'status' => 'pending_president_final',
            'audit_trail' => $audit,
            'payload' => $payload,
            'source_updated_at' => $now,
        ]);
        $ticket->save();
        $fresh = $ticket->fresh();
        $this->workflowNotifications->pendingPresidentFinal($fresh, $user);

        return $fresh;
    }

    /**
     * Return accomplishment to mitigation for more work (NOT close).
     *
     * @param  array<string, mixed>  $input
     */
    public function returnAccomplishment(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertGovernanceRole($user);

        if (! in_array((string) $ticket->status, ['under_audit', 'pending_audit'], true)) {
            throw ValidationException::withMessages([
                'status' => ['This ticket is not awaiting accomplishment review.'],
            ]);
        }

        $note = trim((string) ($input['note'] ?? $input['comment'] ?? $input['reason'] ?? ''));
        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => ['A reason is required to return an accomplishment.'],
            ]);
        }

        $now = now();
        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = $this->audit($user, 'Accomplishment returned', $note, $now);

        $ticket->fill([
            'status' => 'audit_returned',
            'accomplishment_external_id' => null,
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();
        $fresh = $ticket->fresh();
        $this->workflowNotifications->ticketReturned($fresh, $user);

        return $fresh;
    }

    public function addThreadComment(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        return app(ThreadCommentService::class)->add($ticket, $user, $input, 'governance');
    }

    private function assertRmOfficer(User $user): void
    {
        if ($user->role !== Roles::RM_OFFICER) {
            abort(403, 'Forbidden.');
        }
    }

    private function assertGovernanceRole(User $user): void
    {
        if (! in_array($user->role, [Roles::RM_OFFICER, Roles::COMPLIANCE_OFFICER], true)) {
            abort(403, 'Forbidden.');
        }
    }

    private function resolveActiveDepartment(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $departments = Department::query()->where('active', true)->get();
        foreach ($departments as $department) {
            if (Departments::match($department->name, $raw) || strcasecmp($department->name, $raw) === 0) {
                return $department->name;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function audit(User $user, string $action, string $detail, \Illuminate\Support\Carbon $at): array
    {
        return [
            'id' => 'aud-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $at->toIso8601String(),
            'action' => $action,
            'detail' => $detail,
            'actorUsername' => $user->username,
            'actorName' => $user->name ?: $user->username,
            'actorRole' => $user->role,
        ];
    }
}
