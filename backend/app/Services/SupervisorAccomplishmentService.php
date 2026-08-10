<?php

namespace App\Services;

use App\Models\Accomplishment;
use App\Models\RiskTicket;

/**
 * Phase 5 slice 11: Ticket Reporter accomplishment history from Postgres.
 */
class SupervisorAccomplishmentService
{
    /**
     * @return list<array{ticketRef: string, ticketTitle: string, summary: string, submittedAt: ?string}>
     */
    public function listForUsername(string $username): array
    {
        $visibleRefs = RiskTicket::query()
            ->where('submitted_by', $username)
            ->where('deleted', false)
            ->pluck('reference')
            ->all();

        if ($visibleRefs === []) {
            return [];
        }

        return Accomplishment::query()
            ->where('submitted_by', $username)
            ->whereIn('ticket_ref', $visibleRefs)
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (Accomplishment $a) => [
                'ticketRef' => (string) $a->ticket_ref,
                'ticketTitle' => $a->ticket_title !== null && $a->ticket_title !== ''
                    ? (string) $a->ticket_title
                    : '—',
                'summary' => $a->summary !== null && $a->summary !== ''
                    ? (string) $a->summary
                    : '—',
                'submittedAt' => optional($a->submitted_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
