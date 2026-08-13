<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Phase 8 slice 1: Ticket Reporter create/edit multipart (MinIO + Postgres + Express mirror).
 */
class ReporterTicketFormMutationService
{
    public function __construct(
        private readonly DraftTicketService $drafts,
        private readonly AttachmentService $attachments,
        private readonly SupervisorTicketFormService $forms,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $files
     */
    public function createPreview(User $user, array $input, array $files): RiskTicket
    {
        $ref = trim((string) ($input['referenceOverride'] ?? $input['reference'] ?? ''));
        $existing = $ref !== '' ? $this->drafts->findOwnedDraft($ref, $user) : null;
        if ($existing) {
            if ($existing->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => ['This ticket can no longer be edited.'],
                ]);
            }

            return $this->applyEdit($existing, $user, $input, $files, revision: false);
        }

        if ($files === []) {
            throw ValidationException::withMessages([
                'attachments' => ['At least one evidence file is required.'],
            ]);
        }

        $ticket = $this->drafts->create($user, array_merge($input, [
            'reference' => $ref !== '' ? $ref : null,
            'evidenceCount' => count($files),
            'reporter_department' => $user->department,
        ]));

        $this->attachments->storeUploadedFiles($ticket->reference, $files, $user->username);
        $count = $this->attachments->syncEvidenceCount($ticket->reference);
        if ($count < 1) {
            throw ValidationException::withMessages([
                'attachments' => ['At least one evidence file is required.'],
            ]);
        }

        return $this->drafts->updateDraft($ticket->fresh(), $user, array_merge($input, [
            'evidenceCount' => $count,
        ]));
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $files
     */
    public function updateEdit(User $user, string $reference, array $input, array $files): RiskTicket
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('submitted_by', $user->username)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            abort(404, 'Ticket not found.');
        }

        $revision = in_array($ticket->status, ['returned', 'ownership_rejected'], true);
        if ($ticket->status !== 'draft' && ! $revision) {
            throw ValidationException::withMessages([
                'status' => ['This ticket can no longer be edited.'],
            ]);
        }

        return $this->applyEdit($ticket, $user, $input, $files, revision: $revision);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $files
     */
    private function applyEdit(RiskTicket $ticket, User $user, array $input, array $files, bool $revision): RiskTicket
    {
        foreach ($this->parseRemoveIds($input['removeAttachmentIds'] ?? null) as $id) {
            $att = $this->attachments->findById($id);
            if ($att && $att->ticket_ref === $ticket->reference) {
                $this->attachments->deleteWithStorage($id);
            }
        }

        if ($files !== []) {
            $this->attachments->storeUploadedFiles($ticket->reference, $files, $user->username);
        }

        $count = $this->attachments->syncEvidenceCount($ticket->reference);
        if ($count < 1) {
            throw ValidationException::withMessages([
                'attachments' => ['At least one evidence file is required.'],
            ]);
        }

        if ($revision) {
            $proposed = $ticket->toExpressArray();
            $five = is_array($input['fiveW1H'] ?? null) ? $input['fiveW1H'] : [];
            $proposed['title'] = trim((string) ($input['title'] ?? ''));
            $proposed['location'] = trim((string) ($input['location'] ?? ''));
            $proposed['fiveW1H'] = [
                'what' => trim((string) ($input['what'] ?? $five['what'] ?? '')),
                'why' => trim((string) ($input['why'] ?? $five['why'] ?? '')),
                'where' => trim((string) ($input['where'] ?? $five['where'] ?? '')),
                'when' => trim((string) ($input['when'] ?? $five['when'] ?? '')),
                'who' => trim((string) ($input['who'] ?? $five['who'] ?? '')),
                'how' => trim((string) ($input['how'] ?? $five['how'] ?? '')),
            ];
            $proposed['evidenceCount'] = $count;
            if (! $this->forms->hasRevisionSinceReturn($proposed)) {
                throw ValidationException::withMessages([
                    'revision' => ['You must update the report details or evidence before resubmitting.'],
                ]);
            }
        }

        return $this->drafts->updateEditable($ticket->fresh(), $user, array_merge($input, [
            'evidenceCount' => $count,
        ]));
    }

    /**
     * @return list<string>
     */
    private function parseRemoveIds(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = [$raw];
        }
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
