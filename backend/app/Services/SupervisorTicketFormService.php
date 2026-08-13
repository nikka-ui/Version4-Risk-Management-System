<?php

namespace App\Services;

use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Models\User;

/**
 * Phase 5 slice 13 + Phase 7 slice 7 + Phase 8 slice 1: Ticket Reporter create/edit/preview form data.
 */
class SupervisorTicketFormService
{
    private const REVISION_STATUSES = ['returned', 'ownership_rejected'];

    public function __construct(
        private readonly DraftTicketService $drafts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function newForm(User $user): array
    {
        return [
            'mode' => 'new',
            'ticketRef' => $this->drafts->peekNextReference(),
            'ticket' => null,
            'attachments' => [],
            'formAction' => '/supervisor/tickets/new/preview',
            'activeNav' => 'new',
            'isRevise' => false,
            'isEdit' => false,
            'isDeptReturn' => false,
            'officerNotes' => null,
            'ownership' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function editForm(User $user, string $reference): ?array
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('submitted_by', $user->username)
            ->where('deleted', false)
            ->first();

        if (! $ticket || ! $this->canRevise($ticket)) {
            return null;
        }

        $status = (string) $ticket->status;
        $isRevise = in_array($status, self::REVISION_STATUSES, true);
        $express = $ticket->toExpressArray();

        return [
            'mode' => $isRevise ? 'revise' : 'edit',
            'ticketRef' => $ticket->reference,
            'ticket' => $express,
            'attachments' => $this->attachmentsFor($ticket->reference),
            'formAction' => '/supervisor/tickets/'.rawurlencode($ticket->reference).'/edit',
            'activeNav' => $isRevise ? 'returned' : 'tickets',
            'isRevise' => $isRevise,
            'isEdit' => $status === 'draft' || $isRevise,
            'isDeptReturn' => $status === 'ownership_rejected',
            'officerNotes' => $express['officerNotes'] ?? null,
            'ownership' => is_array($express['ownership'] ?? null) ? $express['ownership'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function previewForm(User $user, string $reference): ?array
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('submitted_by', $user->username)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            return null;
        }

        $express = $ticket->toExpressArray();
        $status = (string) $ticket->status;
        $isRevise = in_array($status, self::REVISION_STATUSES, true);
        $revisionBlocked = $isRevise && ! $this->hasRevisionSinceReturn($express);

        return [
            'ticket' => $express,
            'attachments' => $this->attachmentsFor($ticket->reference),
            'isRevise' => $isRevise,
            'revisionBlocked' => $revisionBlocked,
            'activeNav' => 'new',
        ];
    }

    private function canRevise(RiskTicket $ticket): bool
    {
        $status = (string) $ticket->status;

        return $status === 'draft' || in_array($status, self::REVISION_STATUSES, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachmentsFor(string $reference): array
    {
        return RiskAttachment::query()
            ->where('ticket_ref', $reference)
            ->orderByDesc('uploaded_at')
            ->get()
            ->map(fn (RiskAttachment $a) => [
                'id' => $a->id,
                'name' => $a->original_name ?: 'file',
                'size' => (int) $a->size_bytes,
                'uploadedAt' => optional($a->uploaded_at)?->toIso8601String(),
                'storageKey' => $a->storage_key,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    public function hasRevisionSinceReturn(array $ticket): bool
    {
        $hash = $ticket['returnRevisionHash'] ?? null;
        if (! is_string($hash) || $hash === '') {
            return true;
        }

        $five = is_array($ticket['fiveW1H'] ?? null) ? $ticket['fiveW1H'] : [];
        $snapshot = json_encode([
            'title' => $ticket['title'] ?? '',
            'location' => $ticket['location'] ?? '',
            'what' => $five['what'] ?? '',
            'why' => $five['why'] ?? '',
            'where' => $five['where'] ?? '',
            'when' => $five['when'] ?? '',
            'who' => $five['who'] ?? '',
            'how' => $five['how'] ?? '',
            'evidenceCount' => $ticket['evidenceCount'] ?? 0,
        ]);

        return md5($snapshot ?: '') !== $hash;
    }
}
