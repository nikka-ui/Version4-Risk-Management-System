<?php

namespace App\Services;

use App\Models\Accomplishment;
use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Phase 8 slice 2: Ticket Reporter add-evidence + accomplishment multipart (MinIO + Postgres + Express mirror).
 */
class ReporterEvidenceMutationService
{
    /** @var list<string> */
    private const ACCOMPLISHMENT_STATUSES = ['in_mitigation', 'in_progress', 'reopened'];

    /** @var list<string> */
    private const REVISION_UPLOAD_STATUSES = ['returned', 'ownership_rejected', 'reopened'];

    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function addEvidence(User $user, string $reference, array $files): RiskTicket
    {
        $ticket = $this->findOwned($reference, $user);
        if (! $this->canUploadEvidence($ticket)) {
            throw ValidationException::withMessages([
                'status' => ['You cannot add evidence while this ticket is with the department head or PCEO. Upload action-plan proof only when you are implementing the published plan, or after the ticket is returned to you.'],
            ]);
        }
        if ($files === []) {
            throw ValidationException::withMessages([
                'attachments' => ['Upload at least one evidence file.'],
            ]);
        }

        $saved = $this->attachments->storeUploadedFiles($ticket->reference, $files, $user->username);
        if ($this->canSubmitAccomplishment($ticket)) {
            $this->rememberImplementationIds($ticket, $saved);
        }

        $ticket->evidence_count = $this->attachments->syncEvidenceCount($ticket->reference);
        $ticket->source_updated_at = now();
        $ticket->save();

        return $ticket->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $files
     */
    public function submitAccomplishment(User $user, string $reference, array $input, array $files): RiskTicket
    {
        $ticket = $this->findOwned($reference, $user);
        if ($ticket->accomplishment_external_id) {
            throw ValidationException::withMessages([
                'status' => ['An accomplishment report has already been submitted for this ticket.'],
            ]);
        }
        if (! $this->canSubmitAccomplishment($ticket)) {
            throw ValidationException::withMessages([
                'status' => ['No active mitigation assignment for this ticket.'],
            ]);
        }

        $summary = trim((string) ($input['summary'] ?? ''));
        $outcomes = trim((string) ($input['outcomes'] ?? ''));
        if ($summary === '' || $outcomes === '') {
            throw ValidationException::withMessages([
                'summary' => ['Implementation summary and outcomes are required.'],
            ]);
        }

        if ($files !== []) {
            $saved = $this->attachments->storeUploadedFiles($ticket->reference, $files, $user->username);
            $this->rememberImplementationIds($ticket, $saved);
            $ticket = $ticket->fresh();
        }

        $implementation = $this->implementationEvidence($ticket);
        if ($implementation === []) {
            throw ValidationException::withMessages([
                'attachments' => ['Upload at least one evidence file proving the department action plan was applied before submitting your accomplishment report. Original ticket attachments do not count.'],
            ]);
        }

        $now = now();
        $accId = 'acc-'.(int) round(microtime(true) * 1000);
        Accomplishment::query()->create([
            'external_id' => $accId,
            'ticket_ref' => $ticket->reference,
            'ticket_title' => $ticket->title,
            'summary' => $summary,
            'outcomes' => $outcomes,
            'submitted_by' => $user->username,
            'submitted_by_name' => $user->name ?: $user->username,
            'submitted_at' => $now,
            'evidence' => array_map(fn (RiskAttachment $a) => [
                'id' => $a->id,
                'name' => $a->original_name ?: 'file',
                'uploadedAt' => optional($a->uploaded_at)?->toIso8601String(),
                'purpose' => 'implementation',
            ], $implementation),
        ]);

        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = [
            'id' => 'aud-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $now->toIso8601String(),
            'action' => 'Accomplishment report submitted',
            'detail' => 'Reporter submitted the accomplishment report. Awaiting department head review and closure.',
            'actorUsername' => $user->username,
            'actorName' => $user->name ?: $user->username,
            'actorRole' => 'supervisor',
        ];

        $ticket->fill([
            'accomplishment_external_id' => $accId,
            'status' => 'pending_audit',
            'audit_trail' => $audit,
            'evidence_count' => $this->attachments->syncEvidenceCount($ticket->reference),
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        $actor = $user->name ?: $user->username;
        foreach ([Roles::DEPT_HEAD, Roles::RM_OFFICER] as $role) {
            try {
                $this->notifications->create([
                    'recipientRole' => $role,
                    'type' => 'accomplishment_submitted',
                    'title' => 'Accomplishment report submitted',
                    'message' => "{$actor} submitted an accomplishment report for {$ticket->reference}. Review and close the ticket when complete.",
                    'ticketRef' => $ticket->reference,
                    'fromUsername' => $user->username,
                    'fromName' => $actor,
                    'fromRole' => 'supervisor',
                ]);
            } catch (\Throwable) {
                // Notifications are best-effort; ticket write already succeeded.
            }
        }

        return $ticket->fresh();
    }

    public function canUploadEvidence(RiskTicket $ticket): bool
    {
        if ($this->canSubmitAccomplishment($ticket)) {
            return true;
        }

        return in_array((string) $ticket->status, self::REVISION_UPLOAD_STATUSES, true);
    }

    public function canSubmitAccomplishment(RiskTicket $ticket): bool
    {
        if ($ticket->accomplishment_external_id) {
            return false;
        }
        if (! in_array((string) $ticket->status, self::ACCOMPLISHMENT_STATUSES, true)) {
            return false;
        }

        return $this->hasMitigationAssignment($ticket);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function implementationEvidenceList(RiskTicket $ticket): array
    {
        return array_map(fn (RiskAttachment $a) => [
            'id' => $a->id,
            'name' => $a->original_name ?: 'file',
            'size' => (int) $a->size_bytes,
            'uploadedAt' => optional($a->uploaded_at)?->toIso8601String(),
        ], $this->implementationEvidence($ticket));
    }

    private function findOwned(string $reference, User $user): RiskTicket
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('submitted_by', $user->username)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            abort(404, 'Ticket not found.');
        }

        return $ticket;
    }

    private function hasMitigationAssignment(RiskTicket $ticket): bool
    {
        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $officerNotes = trim((string) ($payload['officerNotes'] ?? ''));
        $hasRmoPlan = $officerNotes !== '' && $ticket->mitigation_due_at !== null;

        $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        $hasDeptPlan = ($ownership['state'] ?? null) === 'accepted'
            && trim((string) ($plan['summary'] ?? '')) !== ''
            && (! empty($plan['publishedToReporterAt']) || ! empty($plan['submittedForReviewAt']));

        return $hasRmoPlan || $hasDeptPlan;
    }

    /**
     * @param  list<RiskAttachment>  $saved
     */
    private function rememberImplementationIds(RiskTicket $ticket, array $saved): void
    {
        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $ids = is_array($payload['implementationEvidenceIds'] ?? null) ? $payload['implementationEvidenceIds'] : [];
        foreach ($saved as $att) {
            if (! $att instanceof RiskAttachment || $att->id === '') {
                continue;
            }
            if (! in_array($att->id, $ids, true)) {
                $ids[] = $att->id;
            }
        }
        $payload['implementationEvidenceIds'] = $ids;
        $ticket->payload = $payload;
        $ticket->save();
    }

    /**
     * @return list<RiskAttachment>
     */
    private function implementationEvidence(RiskTicket $ticket): array
    {
        $payload = is_array($ticket->payload) ? $ticket->payload : [];
        $ids = is_array($payload['implementationEvidenceIds'] ?? null) ? $payload['implementationEvidenceIds'] : [];
        $assignedAt = $this->mitigationAssignmentAt($ticket);

        return RiskAttachment::query()
            ->where('ticket_ref', $ticket->reference)
            ->orderBy('uploaded_at')
            ->get()
            ->filter(function (RiskAttachment $a) use ($ids, $assignedAt) {
                if (in_array($a->id, $ids, true)) {
                    return true;
                }
                if ($assignedAt && $a->uploaded_at && $a->uploaded_at->greaterThanOrEqualTo($assignedAt)) {
                    return true;
                }

                return false;
            })
            ->values()
            ->all();
    }

    private function mitigationAssignmentAt(RiskTicket $ticket): ?Carbon
    {
        $plan = is_array($ticket->action_plan) ? $ticket->action_plan : [];
        foreach (['publishedToReporterAt', 'submittedForReviewAt'] as $key) {
            $raw = $plan[$key] ?? null;
            if (is_string($raw) && $raw !== '') {
                try {
                    return Carbon::parse($raw);
                } catch (\Throwable) {
                }
            }
        }

        foreach (array_reverse(is_array($ticket->audit_trail) ? $ticket->audit_trail : []) as $event) {
            if (! is_array($event)) {
                continue;
            }
            $text = strtolower((string) (($event['action'] ?? '').' '.($event['detail'] ?? '')));
            if (! preg_match('/action plan sent|mitigation plan approved|implementation required/', $text)) {
                continue;
            }
            $at = $event['at'] ?? null;
            if (is_string($at) && $at !== '') {
                try {
                    return Carbon::parse($at);
                } catch (\Throwable) {
                }
            }
        }

        return null;
    }
}
