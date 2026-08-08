<?php

namespace App\Services;

use App\Models\Department;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Departments;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 slice 7: RMO reopen (+ shared officer ticket lookup).
 * Notifications remain Express-owned.
 */
class OfficerTicketService
{
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
        $audit[] = [
            'id' => 'aud-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $now->toIso8601String(),
            'action' => 'Ticket reopened by RMO',
            'detail' => sprintf(
                '%s reopened %s and assigned it to %s. Reason: %s',
                $user->name ?: $user->username,
                $ticket->reference,
                $target,
                $reason,
            ),
            'actorUsername' => $user->username,
            'actorName' => $user->name ?: $user->username,
            'actorRole' => 'rm_officer',
        ];

        $ticket->fill([
            'reopen_history' => $history,
            'accomplishment_external_id' => null,
            'department' => $target,
            'ownership' => $ownership,
            'status' => 'assigned',
            'closure' => null,
            'audit_trail' => $audit,
            'payload' => $payload,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function addThreadComment(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        return app(ThreadCommentService::class)->add($ticket, $user, $input, 'governance');
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
}
