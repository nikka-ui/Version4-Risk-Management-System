<?php

namespace App\Services;

use App\Models\Department;
use App\Models\RiskTicket;
use App\Support\Departments;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 18: System Administrator ticket list from Laravel Postgres.
 */
class AdminTicketService
{
    /** @var array<string, string> */
    private const STATUS_OPTIONS = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'assigned' => 'Assigned to Department',
        'ownership_rejected' => 'Returned by Department',
        'in_progress' => 'In Progress (Department)',
        'pending_president' => 'Awaiting President Approval',
        'pending_president_final' => 'Awaiting President Final Decision',
        'under_review' => 'Under RMO Review',
        'returned' => 'Returned for Revision',
        'under_audit' => 'Legacy — Under Review',
        'audit_returned' => 'Legacy — Returned for Revision',
        'in_mitigation' => 'Implementation Required',
        'pending_audit' => 'Accomplishment Submitted',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'reopened' => 'Reopened',
    ];

    /**
     * @return array{
     *   tickets: list<array<string,mixed>>,
     *   departments: list<array<string,mixed>>,
     *   statusOptions: array<string,string>,
     *   filters: array<string,mixed>
     * }
     */
    public function list(?string $q, ?string $department, ?string $level, ?string $status, bool $deleted): array
    {
        $query = RiskTicket::query()
            ->where('deleted', $deleted);

        // Status filter mirrors Express admin tickets behavior.
        if ($status === 'closed') {
            $query->whereIn('status', ['closed', 'resolved']);
        } elseif ($status === 'open') {
            $query->whereNotIn('status', ['closed', 'resolved']);
        } elseif ($status) {
            $query->where('status', $status);
        }

        if ($department) {
            $query->where('department', $department);
        }

        if ($q) {
            $needle = mb_strtolower(trim($q));
            $query->where(function ($qq) use ($needle) {
                $qq->whereRaw('LOWER(reference) LIKE ?', ['%'.$needle.'%'])
                    ->orWhereRaw('LOWER(title) LIKE ?', ['%'.$needle.'%'])
                    ->orWhereRaw('LOWER(submitted_by_name) LIKE ?', ['%'.$needle.'%'])
                    ->orWhereRaw('LOWER(department) LIKE ?', ['%'.$needle.'%']);
            });
        }

        $tickets = $query
            ->orderByDesc('source_updated_at')
            ->orderByDesc('id')
            ->get();

        $tickets = $this->withDerivedFields($tickets);

        if ($level) {
            $tickets = $tickets->filter(fn (array $t) => ($t['riskLevel'] ?? null) === $level)->values();
        }

        return [
            'tickets' => $tickets->all(),
            'departments' => $this->activeDepartments(),
            'statusOptions' => self::STATUS_OPTIONS,
            'filters' => [
                'q' => $q ?? '',
                'department' => $department ?? '',
                'level' => $level ?? '',
                'status' => $status ?? '',
                'deleted' => $deleted,
            ],
        ];
    }

    /**
     * @param Collection<int,RiskTicket> $tickets
     * @return Collection<int, array<string,mixed>>
     */
    private function withDerivedFields(Collection $tickets): Collection
    {
        return $tickets->map(function (RiskTicket $ticket): array {
            $ai = is_array($ticket->ai) ? $ticket->ai : null;
            $riskLevel = Departments::riskLevelId($ai, $ticket->likelihood, $ticket->impact);

            return [
                'id' => $ticket->external_id,
                'reference' => $ticket->reference,
                'title' => $ticket->title ?: '—',
                'department' => $ticket->department ?: '—',
                'riskLevel' => $riskLevel,
                'riskLevelLabel' => $this->riskLevelLabel($riskLevel),
                'status' => $ticket->status,
                'statusLabel' => $this->statusLabel($ticket->status),
                'updatedAt' => optional($ticket->source_updated_at)->toIso8601String(),
                'deleted' => (bool) $ticket->deleted,
                'deletionReason' => $ticket->deletion_reason,
            ];
        })->values();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function activeDepartments(): array
    {
        return Department::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Department $d) => $d->toExpressArray())
            ->values()
            ->all();
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
        if ($status && isset(self::STATUS_OPTIONS[$status])) {
            return self::STATUS_OPTIONS[$status];
        }

        return $status ? str_replace('_', ' ', ucfirst($status)) : '—';
    }
}

