<?php

namespace App\Services;

use App\Models\Department;
use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Departments;
use Illuminate\Validation\ValidationException;

/**
 * Submit draft/revision → AI classify; auto-route only when confidence + dept match allow it.
 */
class SubmitTicketService
{
    /** @var list<string> */
    private const REVISION_STATUSES = ['returned', 'ownership_rejected'];

    public function __construct(
        private readonly AiAnalysisService $aiAnalysis,
        private readonly WorkflowNotificationService $workflowNotifications,
    ) {}

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

        $evidenceCount = RiskAttachment::query()->where('ticket_ref', $ticket->reference)->count();
        if ($evidenceCount < 1) {
            throw ValidationException::withMessages([
                'evidenceCount' => ['At least one evidence file must be uploaded before submit.'],
            ]);
        }
        $ticket->evidence_count = $evidenceCount;

        $wasRevision = in_array($status, self::REVISION_STATUSES, true);
        $five = is_array($ticket->five_w1h) ? $ticket->five_w1h : [];

        $ai = $this->aiAnalysis->analyze([
            'title' => (string) $ticket->title,
            'location' => (string) $ticket->location,
            'fiveW1H' => $five,
            'evidenceCount' => $evidenceCount,
        ], (string) $ticket->reference);

        $now = now();
        $recommendedRaw = trim((string) ($ai['responsibleDepartment'] ?? ''));
        $matchedDepartment = $this->resolveActiveDepartment($recommendedRaw);
        $priority = (string) ($ai['priority'] ?? 'medium');
        $confidence = (float) ($ai['confidence'] ?? 0);
        $manualReview = ! empty($ai['manualReviewRequired']) || $confidence < (float) config('rms.ai_auto_route_min_confidence', 0.75);
        $autoRouteAllowed = (bool) config('rms.ai_auto_route', false);
        $canAutoRoute = $autoRouteAllowed && ! $manualReview && $matchedDepartment !== null;

        $ai['recommendedDepartment'] = $recommendedRaw !== '' ? $recommendedRaw : null;
        $ai['matchedDepartment'] = $matchedDepartment;
        $ai['manualReviewRequired'] = $manualReview || $matchedDepartment === null;
        $ai['routingStatus'] = $canAutoRoute ? 'auto_assigned' : 'pending_review';

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
                is_array($ai['riskLevel'] ?? null) ? ($ai['riskLevel']['label'] ?? 'Risk') : 'Risk',
                (int) round($confidence * 100),
            ),
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
        $payload['aiRecommendation'] = [
            'department' => $matchedDepartment ?? $recommendedRaw,
            'priority' => $priority,
            'confidence' => $confidence,
            'at' => $now->toIso8601String(),
        ];

        if ($canAutoRoute) {
            $department = $matchedDepartment;
            $audit[] = $this->auditEvent(
                "Assigned to {$department}",
                sprintf('%s priority. Awaiting Department Head acceptance.', ucfirst($priority)),
                'system',
                'AI Routing Engine',
                'system',
                $now,
            );

            $ticket->fill([
                'status' => 'assigned',
                'category' => $ai['riskCategory'] ?? $ticket->category,
                'likelihood' => $ai['likelihood'] ?? $ticket->likelihood,
                'impact' => $ai['impact'] ?? $ticket->impact,
                'risk_score' => ((int) ($ai['likelihood'] ?? 1)) * ((int) ($ai['impact'] ?? 1)),
                'priority' => $priority,
                'department' => $department,
                'evidence_count' => $evidenceCount,
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
                'response_due_at' => $now->copy()->addHours((int) config('rms.response_sla_hours', 24)),
                'source_updated_at' => $now,
                'mitigation_due_at' => $wasRevision ? null : $ticket->mitigation_due_at,
                'payload' => $payload,
            ]);
            $ticket->save();
            $fresh = $ticket->fresh();
            $this->workflowNotifications->ticketAssigned($fresh, $user);

            return $fresh;
        }

        $reason = $matchedDepartment === null
            ? 'AI department recommendation did not match an active department.'
            : 'AI confidence below threshold or manual review required.';
        $audit[] = $this->auditEvent(
            'Pending AI routing review',
            $reason.' Awaiting Risk Management Officer approval before department ownership opens.',
            'system',
            'AI Routing Engine',
            'system',
            $now,
        );

        $ticket->fill([
            'status' => 'pending_ai_review',
            'category' => $ai['riskCategory'] ?? $ticket->category,
            'likelihood' => $ai['likelihood'] ?? $ticket->likelihood,
            'impact' => $ai['impact'] ?? $ticket->impact,
            'risk_score' => ((int) ($ai['likelihood'] ?? 1)) * ((int) ($ai['impact'] ?? 1)),
            'priority' => $priority,
            'department' => null,
            'evidence_count' => $evidenceCount,
            'ai' => $ai,
            'ownership' => [
                'state' => 'pending_ai_review',
                'ownerUsername' => null,
                'ownerName' => null,
                'ownerDepartment' => $matchedDepartment,
                'recommendedDepartment' => $matchedDepartment ?? $recommendedRaw,
                'assignedAt' => null,
                'acceptedAt' => null,
                'rejectedAt' => null,
                'rejectionReason' => null,
            ],
            'audit_trail' => $audit,
            'submitted_at' => $now,
            'routed_at' => null,
            'source_updated_at' => $now,
            'mitigation_due_at' => $wasRevision ? null : $ticket->mitigation_due_at,
            'payload' => $payload,
        ]);
        $ticket->save();
        $fresh = $ticket->fresh();
        $this->workflowNotifications->pendingAiReview($fresh, $user);

        return $fresh;
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
