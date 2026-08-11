<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Support\Departments;
use Illuminate\Support\Carbon;

class AdminTicketDetailService
{
    /**
     * @return array<string,mixed>|null
     */
    public function findByReference(string $reference): ?array
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->first();

        if (! $ticket) {
            return null;
        }

        $status = (string) $ticket->status;
        $ai = is_array($ticket->ai) ? $ticket->ai : null;
        $riskLevel = Departments::riskLevelId($ai, $ticket->likelihood, $ticket->impact);

        return [
            'reference' => (string) $ticket->reference,
            'title' => $ticket->title ?: '',
            'department' => $ticket->department ?: '—',
            'submittedByName' => $ticket->submitted_by_name ?: ($ticket->submitted_by ?: '—'),
            'riskLevel' => $riskLevel,
            'riskLevelLabel' => $this->riskLevelLabel($riskLevel),
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'updatedAt' => $this->iso($ticket->source_updated_at),
            'deleted' => (bool) $ticket->deleted,
            'deletionReason' => $ticket->deletion_reason ?: null,
        ];
    }

    private function iso(?Carbon $dt): ?string
    {
        return $dt?->toIso8601String();
    }

    private function riskLevelLabel(?string $riskLevel): string
    {
        return match ($riskLevel) {
            'low' => 'Low',
            'moderate' => 'Moderate',
            'high' => 'High',
            'critical' => 'Critical',
            default => $riskLevel ? ucfirst(str_replace('_', ' ', $riskLevel)) : '—',
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'assigned' => 'Assigned',
            'returned' => 'Returned',
            'ownership_rejected' => 'Ownership rejected',
            'in_mitigation' => 'In mitigation',
            'closed' => 'Closed',
            'resolved' => 'Resolved',
            default => $status ? str_replace('_', ' ', ucfirst($status)) : '—',
        };
    }
}

