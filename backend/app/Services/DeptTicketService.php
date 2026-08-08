<?php

namespace App\Services;

use App\Models\Department;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Departments;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 slice 6: dept return/reassign/close + slice 5 ownership/action plan.
 * Notifications remain Express-owned until a later slice.
 */
class DeptTicketService
{
    /** @var list<string> */
    private const OWNERSHIP_STATUSES = ['assigned'];

    /** @var list<string> */
    private const EXECUTION_STATUSES = ['in_progress', 'reopened'];

    /** @var list<string> */
    private const REASSIGN_STATUSES = ['assigned', 'in_progress', 'reopened'];

    /** @var list<string> */
    private const CLOSURE_STATUSES = ['pending_audit'];

    public function findForDeptHead(string $reference, User $user): ?RiskTicket
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->first();

        if (! $ticket || ! $this->isDeptHeadTicket($ticket, $user)) {
            return null;
        }

        return $ticket;
    }

    public function accept(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertDeptHeadAccess($ticket, $user);
        if (! in_array($ticket->status, self::OWNERSHIP_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['This ticket is not awaiting an ownership decision.'],
            ]);
        }

        $now = now();
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        $ownership['state'] = 'accepted';
        $ownership['ownerUsername'] = $user->username;
        $ownership['ownerName'] = $user->name ?: $user->username;
        $ownership['ownerPosition'] = $user->position;
        $ownership['ownerDepartment'] = $ticket->department;
        $ownership['acceptedAt'] = $now->toIso8601String();

        $note = trim((string) ($input['comment'] ?? ''));
        $audit = $this->appendAudit(
            $ticket,
            'Department accepted ticket',
            sprintf(
                '%s accepted ownership for %s.%s',
                $user->name ?: $user->username,
                $ticket->department ?: 'department',
                $note !== '' ? " Note: {$note}" : '',
            ),
            $user,
            $now,
        );

        $ticket->fill([
            'ownership' => $ownership,
            'status' => 'in_progress',
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function reject(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertDeptHeadAccess($ticket, $user);
        if (! in_array($ticket->status, self::OWNERSHIP_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['This ticket is not awaiting an ownership decision.'],
            ]);
        }

        $reason = trim((string) ($input['reason'] ?? $input['comment'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required to reject ownership.'],
            ]);
        }

        $now = now();
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        $ownership['state'] = 'rejected';
        $ownership['rejectedAt'] = $now->toIso8601String();
        $ownership['rejectionReason'] = $reason;
        $ownership['rejectedByUsername'] = $user->username;
        $ownership['rejectedByName'] = $user->name ?: $user->username;
        $ownership['rejectedByPosition'] = $user->position;
        $ownership['ownerUsername'] = null;
        $ownership['ownerName'] = null;

        $audit = $this->appendAudit($ticket, 'Returned by department', $reason, $user, $now);

        $ticket->fill([
            'ownership' => $ownership,
            'status' => 'ownership_rejected',
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function saveActionPlan(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertDeptHeadAccess($ticket, $user);
        if (! $this->canExecute($ticket, $user)) {
            throw ValidationException::withMessages([
                'status' => ['Accept ownership before creating an action plan.'],
            ]);
        }

        $summary = trim((string) ($input['summary'] ?? ''));
        if ($summary === '') {
            throw ValidationException::withMessages([
                'summary' => ['An action plan summary is required.'],
            ]);
        }

        $stepsRaw = $input['steps'] ?? [];
        if (is_string($stepsRaw)) {
            $steps = array_values(array_filter(array_map('trim', explode("\n", $stepsRaw))));
        } elseif (is_array($stepsRaw)) {
            $steps = array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $stepsRaw)));
        } else {
            $steps = [];
        }
        $steps = array_slice($steps, 0, 30);

        $targetDate = trim((string) ($input['targetDate'] ?? $input['target_date'] ?? ''));
        $submitForReview = in_array($input['submitForReview'] ?? $input['submit_for_review'] ?? false, [true, 1, '1', 'true'], true);

        if ($submitForReview && $targetDate === '') {
            throw ValidationException::withMessages([
                'targetDate' => ['A target completion date is required before sending the plan to the reporter.'],
            ]);
        }

        $now = now();
        $existing = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        $existed = $existing !== [];

        $plan = [
            'summary' => $summary,
            'steps' => $steps,
            'targetDate' => $targetDate !== '' ? $targetDate : ($existing['targetDate'] ?? null),
            'createdAt' => $existing['createdAt'] ?? $now->toIso8601String(),
            'updatedAt' => $now->toIso8601String(),
            'updatedByName' => $user->name ?: $user->username,
            'version' => ((int) ($existing['version'] ?? 0)) + 1,
            'publishedToReporterAt' => $submitForReview ? $now->toIso8601String() : ($existing['publishedToReporterAt'] ?? null),
            'submittedForReviewAt' => $submitForReview ? $now->toIso8601String() : ($existing['submittedForReviewAt'] ?? null),
        ];

        $status = $ticket->status;
        $presidentPhase = null;
        $payload = is_array($ticket->payload) ? $ticket->payload : [];

        if ($submitForReview) {
            $needsPresident = Departments::requiresPresidentApproval(
                is_array($ticket->ai) ? $ticket->ai : null,
                $ticket->likelihood,
                $ticket->impact,
            );
            if ($needsPresident) {
                $status = 'pending_president';
                $presidentPhase = 'action_plan';
                $plan['publishedToReporterAt'] = null;
                $plan['submittedForReviewAt'] = $now->toIso8601String();
                $ticket->president_plan_decision = null;
                $audit = $this->appendAudit(
                    $ticket,
                    'Action plan submitted to President',
                    'High/Critical action plan submitted for presidential approval.'
                        .($targetDate !== '' ? " Target date: {$targetDate}." : ''),
                    $user,
                    $now,
                );
            } else {
                $status = 'in_mitigation';
                $audit = $this->appendAudit(
                    $ticket,
                    'Action plan sent to reporter',
                    'Mitigation plan published to the reporter for implementation.'
                        .($targetDate !== '' ? " Target date: {$targetDate}." : ''),
                    $user,
                    $now,
                );
            }
        } else {
            $audit = $this->appendAudit(
                $ticket,
                $existed ? 'Action plan updated' : 'Action plan created',
                mb_strlen($summary) > 160 ? mb_substr($summary, 0, 160).'…' : $summary,
                $user,
                $now,
            );
        }

        $mitigationDue = null;
        if (! empty($plan['targetDate'])) {
            try {
                $mitigationDue = Carbon::parse((string) $plan['targetDate'])->endOfDay();
            } catch (\Throwable) {
                $mitigationDue = $ticket->mitigation_due_at;
            }
        }

        if ($presidentPhase !== null) {
            $payload['presidentReviewPhase'] = $presidentPhase;
        }

        $ticket->fill([
            'action_plan' => $plan,
            'status' => $status,
            'audit_trail' => $audit,
            'mitigation_due_at' => $mitigationDue,
            'source_updated_at' => $now,
            'payload' => $payload === [] ? $ticket->payload : $payload,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function returnForRevision(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertDeptHeadAccess($ticket, $user);
        if (! $this->canExecute($ticket, $user)) {
            throw ValidationException::withMessages([
                'status' => ['Accept ownership before returning this ticket for revision.'],
            ]);
        }

        $reason = trim((string) ($input['reason'] ?? $input['comment'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required to return this ticket for revision.'],
            ]);
        }

        $now = now();
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        $ownership['state'] = 'rejected';
        $ownership['rejectedAt'] = $now->toIso8601String();
        $ownership['rejectionReason'] = $reason;
        $ownership['rejectedByUsername'] = $user->username;
        $ownership['rejectedByName'] = $user->name ?: $user->username;
        $ownership['rejectedByPosition'] = $user->position;
        $ownership['ownerUsername'] = null;
        $ownership['ownerName'] = null;
        $ownership['ownerPosition'] = null;

        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $payload['returnedAt'] = $now->toIso8601String();

        $audit = $this->appendAudit($ticket, 'Returned for revision', $reason, $user, $now);

        $ticket->fill([
            'ownership' => $ownership,
            'status' => 'ownership_rejected',
            'audit_trail' => $audit,
            'payload' => $payload,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function reassign(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertDeptHeadAccess($ticket, $user);
        if (! in_array($ticket->status, self::REASSIGN_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['This ticket can no longer be reassigned.'],
            ]);
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        $comment = trim((string) ($input['comment'] ?? ''));
        $targetRaw = trim((string) ($input['targetDepartment'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required to request reassignment.'],
            ]);
        }
        if ($targetRaw === '') {
            throw ValidationException::withMessages([
                'targetDepartment' => ['Select the target department for reassignment.'],
            ]);
        }

        $target = $this->resolveActiveDepartment($targetRaw);
        if (! $target) {
            throw ValidationException::withMessages([
                'targetDepartment' => ['Invalid target department.'],
            ]);
        }
        if (Departments::match($target, $ticket->department)) {
            throw ValidationException::withMessages([
                'targetDepartment' => ['The ticket is already assigned to that department.'],
            ]);
        }

        $now = now();
        $fromDepartment = (string) $ticket->department;
        $combinedNote = $comment !== '' ? "{$reason}\n\n{$comment}" : $reason;

        $reassignments = is_array($ticket->reassignments) ? $ticket->reassignments : [];
        $reassignments[] = [
            'id' => 'reasg-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $now->toIso8601String(),
            'fromDepartment' => $fromDepartment,
            'toDepartment' => $target,
            'reason' => $combinedNote,
            'reasonSummary' => $reason,
            'comment' => $comment,
            'byUsername' => $user->username,
            'byName' => $user->name ?: $user->username,
        ];

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

        $audit = $this->appendAudit(
            $ticket,
            'Ticket reassigned',
            "Reassigned from {$fromDepartment} to {$target}. Reason: {$reason}",
            $user,
            $now,
        );

        $ticket->fill([
            'department' => $target,
            'ownership' => $ownership,
            'reassignments' => $reassignments,
            'status' => 'assigned',
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function close(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertDeptHeadAccess($ticket, $user);
        if (! in_array($ticket->status, self::CLOSURE_STATUSES, true) || ! $ticket->accomplishment_external_id) {
            throw ValidationException::withMessages([
                'status' => ['This ticket is not awaiting department closure after an accomplishment report.'],
            ]);
        }

        $closingNotes = trim((string) ($input['closingNotes'] ?? $input['notes'] ?? $input['summary'] ?? ''));
        $now = now();
        $closure = [
            'closedAt' => $now->toIso8601String(),
            'closedBy' => $user->username,
            'closedByName' => $user->name ?: $user->username,
            'closedByRole' => 'dept_head',
            'notes' => $closingNotes !== '' ? $closingNotes : 'Closed after reviewing the reporter accomplishment report.',
        ];

        $audit = $this->appendAudit(
            $ticket,
            'Ticket closed by department',
            sprintf(
                '%s closed %s after reviewing the accomplishment report.',
                $user->name ?: $user->username,
                $ticket->reference,
            ),
            $user,
            $now,
        );

        $ticket->fill([
            'status' => 'closed',
            'closure' => $closure,
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function assignPersonnel(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertDeptHeadAccess($ticket, $user);
        if (! $this->canExecute($ticket, $user)) {
            throw ValidationException::withMessages([
                'status' => ['Accept ownership before assigning personnel.'],
            ]);
        }

        $name = trim((string) ($input['personName'] ?? $input['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'personName' => ['Personnel name is required.'],
            ]);
        }
        $role = trim((string) ($input['personRole'] ?? $input['role'] ?? ''));

        $now = now();
        $personnel = is_array($ticket->personnel) ? $ticket->personnel : [];
        $personnel[] = [
            'id' => 'per-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'name' => $name,
            'role' => $role !== '' ? $role : null,
            'assignedAt' => $now->toIso8601String(),
            'assignedByName' => $user->name ?: $user->username,
        ];

        $detail = $role !== '' ? "{$name} — {$role}" : $name;
        $audit = $this->appendAudit($ticket, 'Personnel assigned', $detail, $user, $now);

        $ticket->fill([
            'personnel' => $personnel,
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    /**
     * Metadata-only document mirror (MinIO file ownership remains Express until a later slice).
     */
    public function recordDocuments(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertDeptHeadAccess($ticket, $user);
        if (! in_array($ticket->status, self::EXECUTION_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Documents can be uploaded once the ticket is in progress.'],
            ]);
        }

        $fileCount = (int) ($input['fileCount'] ?? $input['uploaded'] ?? 0);
        $fileNames = $input['fileNames'] ?? [];
        if (! is_array($fileNames)) {
            $fileNames = [];
        }
        $fileNames = array_values(array_filter(array_map(
            static fn ($n) => trim((string) $n),
            $fileNames,
        )));

        if ($fileCount < 1 && $fileNames === []) {
            throw ValidationException::withMessages([
                'fileCount' => ['Select at least one document to upload.'],
            ]);
        }
        if ($fileCount < 1) {
            $fileCount = count($fileNames);
        }

        $now = now();
        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $docs = is_array($payload['deptDocuments'] ?? null) ? $payload['deptDocuments'] : [];
        $docs[] = [
            'at' => $now->toIso8601String(),
            'byUsername' => $user->username,
            'byName' => $user->name ?: $user->username,
            'fileCount' => $fileCount,
            'fileNames' => $fileNames,
        ];
        $payload['deptDocuments'] = $docs;

        $label = $fileCount === 1 ? '1 document' : "{$fileCount} documents";
        $audit = $this->appendAudit(
            $ticket,
            'Documents uploaded',
            "{$label} added by ".($user->name ?: $user->username).'.',
            $user,
            $now,
        );

        $ticket->fill([
            'evidence_count' => max((int) $ticket->evidence_count, 0) + $fileCount,
            'payload' => $payload,
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function addThreadComment(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $this->assertDeptHeadAccess($ticket, $user);

        return app(ThreadCommentService::class)->add($ticket, $user, $input, 'comment');
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

    private function assertDeptHeadAccess(RiskTicket $ticket, User $user): void
    {
        if (! $this->isDeptHeadTicket($ticket, $user)) {
            abort(404, 'Ticket not found.');
        }
    }

    private function canExecute(RiskTicket $ticket, User $user): bool
    {
        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];

        return in_array($ticket->status, self::EXECUTION_STATUSES, true)
            && ($ownership['ownerUsername'] ?? null) === $user->username;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function appendAudit(RiskTicket $ticket, string $action, string $detail, User $user, Carbon $at): array
    {
        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = [
            'id' => 'aud-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $at->toIso8601String(),
            'action' => $action,
            'detail' => $detail,
            'actorUsername' => $user->username,
            'actorName' => $user->name ?: $user->username,
            'actorRole' => 'dept_head',
        ];

        return $audit;
    }
}
