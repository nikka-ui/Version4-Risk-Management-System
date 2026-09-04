<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Departments;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared ticket visibility for Blade + Sanctum API (deny by default).
 */
class TicketAccessService
{
    public function __construct(
        private readonly DeptTicketService $deptTickets,
        private readonly PresidentTicketService $presidentTickets,
        private readonly OfficerTicketService $officerTickets,
    ) {}

    public function canAccess(User $user, string $reference, bool $includeDeleted = false): bool
    {
        return $this->findAccessible($reference, $user, $includeDeleted) !== null;
    }

    public function findAccessible(string $reference, User $user, bool $includeDeleted = false): ?RiskTicket
    {
        $query = RiskTicket::query()->where('reference', $reference);
        if (! $includeDeleted) {
            $query->where('deleted', false);
        }

        $ticket = $query->first();
        if (! $ticket) {
            return null;
        }

        return $this->filterVisible($ticket, $user);
    }

    public function filterVisible(RiskTicket $ticket, User $user): ?RiskTicket
    {
        if ($ticket->deleted && $user->role !== Roles::ADMIN) {
            return null;
        }

        return match ($user->role) {
            Roles::SUPERVISOR, Roles::EMPLOYEE => $ticket->submitted_by === $user->username
                ? $ticket
                : null,
            Roles::DEPT_HEAD => $ticket->status === 'draft'
                ? null
                : ($this->deptTickets->findForDeptHead($ticket->reference, $user) ? $ticket : null),
            Roles::RM_OFFICER, Roles::COMPLIANCE_OFFICER => $ticket->status === 'draft'
                ? null
                : ($this->officerTickets->findForOfficer($ticket->reference) ? $ticket : null),
            Roles::PRESIDENT => $this->presidentTickets->findForPresident($ticket->reference),
            Roles::EXECUTIVE => $ticket->status === 'draft' ? null : $ticket,
            Roles::ADMIN => $ticket,
            default => null,
        };
    }

    /**
     * Apply role-scoped filters to a ticket list query.
     *
     * @param  Builder<RiskTicket>  $query
     * @return Builder<RiskTicket>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            Roles::SUPERVISOR, Roles::EMPLOYEE => $query->where('submitted_by', $user->username),
            // Dept / president lists are refined in PHP (alias-aware / risk-level).
            Roles::DEPT_HEAD, Roles::PRESIDENT => $query->where('status', '!=', 'draft'),
            Roles::RM_OFFICER, Roles::COMPLIANCE_OFFICER => $query->where('status', '!=', 'draft'),
            Roles::EXECUTIVE => $query->where('status', '!=', 'draft'),
            Roles::ADMIN => $query,
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Refine a collection for roles that need alias-aware department matching.
     *
     * @param  iterable<RiskTicket>  $tickets
     * @return list<RiskTicket>
     */
    public function filterCollection(iterable $tickets, User $user): array
    {
        $out = [];
        foreach ($tickets as $ticket) {
            if (! $ticket instanceof RiskTicket) {
                continue;
            }
            if ($this->filterVisible($ticket, $user)) {
                $out[] = $ticket;
            }
        }

        return $out;
    }

    public function isHighOrCritical(RiskTicket $ticket): bool
    {
        return Departments::requiresPresidentApproval(
            is_array($ticket->ai) ? $ticket->ai : null,
            $ticket->likelihood,
            $ticket->impact,
        );
    }
}
