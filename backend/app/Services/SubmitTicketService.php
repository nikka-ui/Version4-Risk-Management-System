<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\DraftAiAnalysis;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 slice 4: submit draft/revision → assigned (Postgres only).
 * Express remains live SoT; notifications/report logs stay Express-owned for now.
 */
class SubmitTicketService
{
    /** @var list<string> */
    private const REVISION_STATUSES = ['returned', 'ownership_rejected'];

    public function submit(RiskTicket $ticket, User $user): RiskTicket
    {
        if ($ticket->deleted || $ticket->submitted_by !== $user->username) {
            abort(404, 'Ticket not found.');
        }

        $status = (string) $ticket->status;
        $allowed = $status === 'draft' || in_array($status, self::REVISION_STATUSES, true);
        if (! $allowed) {
            throw ValidationException::withMessages([
                'status' => ['This ticket cannot be submitted.'],
            ]);
        }

        if ((int) $ticket->evidence_count < 1) {
            throw ValidationException::withMessages([
                'evidenceCount' => ['At least one evidence file is required before submit.'],
            ]);
        }

        $wasRevision = in_array($status, self::REVISION_STATUSES, true);
        $five = is_array($ticket->five_w1h) ? $ticket->five_w1h : [];

        $ai = DraftAiAnalysis::analyze([
            'title' => (string) $ticket->title,
            'location' => (string) $ticket->location,
            'fiveW1H' => $five,
            'evidenceCount' => (int) $ticket->evidence_count,
        ]);

        $now = now();
        $department = (string) ($ai['responsibleDepartment'] ?? 'Operations');
        $priority = (string) ($ai['priority'] ?? 'medium');

        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = $this->auditEvent(
            $wasRevision ? 'Report resubmitted' : 'Reporter created ticket',
            $wasRevision
                ? 'Reporter revised and resubmitted the risk report.'
                : 'Risk report submitted for AI analysis.',
            $user->username,
            $user->name ?: $user->username,
            'supervisor',
            $now,
        );
        $audit[] = $this->auditEvent(
            'AI classified ticket',
            sprintf(
                '%s · %s · %d%% confidence',
                $ai['riskCategory'] ?? 'operational',
                $ai['riskLevel']['label'] ?? 'Risk',
                (int) round(((float) ($ai['confidence'] ?? 0.7)) * 100),
            ),
            'system',
            'AI Routing Engine',
            'system',
            $now,
        );
        $audit[] = $this->auditEvent(
            "Assigned to {$department}",
            sprintf('%s priority. Awaiting Department Head acceptance.', ucfirst($priority)),
            'system',
            'AI Routing Engine',
            'system',
            $now,
        );

        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        if ($wasRevision) {
            $payload['returnRevisionHash'] = null;
            $payload['returnedAt'] = null;
            $payload['officerNotes'] = null;
        }

        $ticket->fill([
            'status' => 'assigned',
            'category' => $ai['riskCategory'] ?? $ticket->category,
            'likelihood' => $ai['likelihood'] ?? $ticket->likelihood,
            'impact' => $ai['impact'] ?? $ticket->impact,
            'risk_score' => ((int) ($ai['likelihood'] ?? 1)) * ((int) ($ai['impact'] ?? 1)),
            'priority' => $priority,
            'department' => $department,
            'ai' => $ai,
            'ownership' => [
                'state' => 'pending',
                'ownerUsername' => null,
                'ownerName' => null,
                'ownerDepartment' => $department,
                'assignedAt' => $now->toIso8601String(),
                'acceptedAt' => null,
                'rejectedAt' => null,
                'rejectionReason' => null,
            ],
            'audit_trail' => $audit,
            'submitted_at' => $now,
            'routed_at' => $now,
            'source_updated_at' => $now,
            'mitigation_due_at' => $wasRevision ? null : $ticket->mitigation_due_at,
            'payload' => $payload === [] ? null : $payload,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditEvent(
        string $action,
        string $detail,
        string $actorUsername,
        string $actorName,
        string $actorRole,
        \Illuminate\Support\Carbon $at,
    ): array {
        return [
            'id' => 'aud-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $at->toIso8601String(),
            'action' => $action,
            'detail' => $detail,
            'actorUsername' => $actorUsername,
            'actorName' => $actorName,
            'actorRole' => $actorRole,
        ];
    }
}
