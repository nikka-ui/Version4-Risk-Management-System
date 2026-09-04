<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\Log;

/**
 * Workflow notification emitters (Laravel-owned; replaces Express-owned comments).
 */
class WorkflowNotificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function ticketAssigned(RiskTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $dept = (string) ($ticket->department ?: 'a department');
        $this->emitRole(Roles::DEPT_HEAD, 'ticket_assigned', 'Ticket assigned', "{$ref} was assigned to {$dept}.", $ticket, $actor);
        $this->emitRole(Roles::RM_OFFICER, 'ticket_assigned', 'Ticket assigned', "{$ref} was assigned to {$dept}.", $ticket, $actor);
        $this->emitReporter($ticket, 'ticket_assigned', 'Ticket routed', "Your report {$ref} was routed to {$dept}.", $actor);
    }

    public function pendingAiReview(RiskTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $this->emitRole(Roles::RM_OFFICER, 'pending_ai_review', 'AI routing needs review', "{$ref} requires RMO approval before department ownership opens.", $ticket, $actor);
        $this->emitReporter($ticket, 'pending_ai_review', 'Report under review', "Your report {$ref} is awaiting Risk Management routing review.", $actor);
    }

    public function ticketReassigned(RiskTicket $ticket, string $from, string $to, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $msg = "{$ref} reassigned from {$from} to {$to}.";
        $this->emitRole(Roles::DEPT_HEAD, 'ticket_reassigned', 'Ticket reassigned', $msg, $ticket, $actor);
        $this->emitRole(Roles::RM_OFFICER, 'ticket_reassigned', 'Ticket reassigned', $msg, $ticket, $actor);
        $this->emitReporter($ticket, 'ticket_reassigned', 'Ticket reassigned', $msg, $actor);
    }

    public function ticketReturned(RiskTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $this->emitReporter($ticket, 'ticket_returned', 'Ticket returned for revision', "{$ref} was returned for revision.", $actor);
        $this->emitRole(Roles::RM_OFFICER, 'ticket_returned', 'Ticket returned', "{$ref} was returned to the reporter.", $ticket, $actor);
    }

    public function commentAdded(RiskTicket $ticket, User $actor, string $preview = ''): void
    {
        $ref = (string) $ticket->reference;
        $snip = $preview !== '' ? ': '.mb_substr($preview, 0, 120) : '.';
        $title = 'New comment on '.$ref;
        $msg = ($actor->name ?: $actor->username).' commented'.$snip;
        if ($ticket->submitted_by && $ticket->submitted_by !== $actor->username) {
            $this->emitUsername($ticket->submitted_by, 'thread_comment', $title, $msg, $ticket, $actor);
        }
        if ($actor->role !== Roles::RM_OFFICER) {
            $this->emitRole(Roles::RM_OFFICER, 'thread_comment', $title, $msg, $ticket, $actor);
        }
    }

    public function actionPlanSaved(RiskTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $this->emitRole(Roles::RM_OFFICER, 'action_plan', 'Action plan updated', "{$ref} has an updated action plan.", $ticket, $actor);
        if ((string) $ticket->status === 'pending_president') {
            $this->emitRole(Roles::PRESIDENT, 'action_plan', 'Action plan awaiting President', "{$ref} needs presidential review.", $ticket, $actor);
        }
    }

    public function presidentDecision(RiskTicket $ticket, string $decision, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $msg = "President recorded \"{$decision}\" on {$ref}.";
        $this->emitRole(Roles::DEPT_HEAD, 'president_decision', 'President decision', $msg, $ticket, $actor);
        $this->emitRole(Roles::RM_OFFICER, 'president_decision', 'President decision', $msg, $ticket, $actor);
        $this->emitReporter($ticket, 'president_decision', 'President decision', $msg, $actor);
    }

    public function ticketClosed(RiskTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $msg = "{$ref} was closed.";
        $this->emitReporter($ticket, 'ticket_closed', 'Ticket closed', $msg, $actor);
        $this->emitRole(Roles::RM_OFFICER, 'ticket_closed', 'Ticket closed', $msg, $ticket, $actor);
    }

    public function ticketReopened(RiskTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $msg = "{$ref} was reopened.";
        $this->emitRole(Roles::DEPT_HEAD, 'ticket_reopened', 'Ticket reopened', $msg, $ticket, $actor);
        $this->emitReporter($ticket, 'ticket_reopened', 'Ticket reopened', $msg, $actor);
    }

    public function accomplishmentSubmitted(RiskTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $status = (string) $ticket->status;
        if ($status === 'under_audit' || $status === 'pending_president_final') {
            $this->emitRole(Roles::RM_OFFICER, 'accomplishment_submitted', 'Accomplishment needs validation', "{$ref} accomplishment awaits RMU/Compliance validation.", $ticket, $actor);
            $this->emitRole(Roles::COMPLIANCE_OFFICER, 'accomplishment_submitted', 'Accomplishment needs validation', "{$ref} accomplishment awaits compliance validation.", $ticket, $actor);
        } else {
            $this->emitRole(Roles::DEPT_HEAD, 'accomplishment_submitted', 'Accomplishment submitted', "{$ref} has an accomplishment report ready for closure review.", $ticket, $actor);
            $this->emitRole(Roles::RM_OFFICER, 'accomplishment_submitted', 'Accomplishment submitted', "{$ref} has an accomplishment report.", $ticket, $actor);
        }
    }

    public function pendingPresidentFinal(RiskTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $this->emitRole(Roles::PRESIDENT, 'pending_president_final', 'Final presidential review', "{$ref} awaits final presidential closure.", $ticket, $actor);
        $this->emitRole(Roles::RM_OFFICER, 'pending_president_final', 'Sent to President (final)', "{$ref} was escalated for final presidential review.", $ticket, $actor);
    }

    public function reviewRecommended(RiskTicket $ticket, string $note, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $msg = "RMO recommendation on {$ref}: ".mb_substr($note, 0, 160);
        $this->emitRole(Roles::DEPT_HEAD, 'rmo_recommend', 'RMO recommendation', $msg, $ticket, $actor);
        $this->emitReporter($ticket, 'rmo_recommend', 'RMO recommendation', $msg, $actor);
    }

    public function reviewEscalated(RiskTicket $ticket, string $note, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $msg = "{$ref} escalated to President: ".mb_substr($note, 0, 160);
        $this->emitRole(Roles::PRESIDENT, 'rmo_escalate', 'Ticket escalated', $msg, $ticket, $actor);
        $this->emitRole(Roles::DEPT_HEAD, 'rmo_escalate', 'Ticket escalated', $msg, $ticket, $actor);
        $this->emitReporter($ticket, 'rmo_escalate', 'Ticket escalated', $msg, $actor);
    }

    public function ownershipAccepted(RiskTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) $ticket->reference;
        $this->emitRole(Roles::RM_OFFICER, 'ownership_accepted', 'Ownership accepted', "{$ref} was accepted by the department.", $ticket, $actor);
        $this->emitReporter($ticket, 'ownership_accepted', 'Department accepted', "{$ref} was accepted by the responsible department.", $actor);
    }

    public function slaApproaching(RiskTicket $ticket): void
    {
        $ref = (string) $ticket->reference;
        $this->emitRole(Roles::RM_OFFICER, 'sla_approaching', 'SLA approaching', "{$ref} is approaching its response or mitigation due date.", $ticket, null);
        $this->emitRole(Roles::DEPT_HEAD, 'sla_approaching', 'SLA approaching', "{$ref} is approaching its response or mitigation due date.", $ticket, null);
    }

    public function slaOverdue(RiskTicket $ticket): void
    {
        $ref = (string) $ticket->reference;
        $this->emitRole(Roles::RM_OFFICER, 'sla_overdue', 'SLA overdue', "{$ref} is past its response/mitigation SLA due date.", $ticket, null);
        $this->emitRole(Roles::DEPT_HEAD, 'sla_overdue', 'SLA overdue', "{$ref} is past its response/mitigation SLA due date.", $ticket, null);
    }

    private function emitReporter(RiskTicket $ticket, string $type, string $title, string $message, ?User $actor): void
    {
        $username = trim((string) $ticket->submitted_by);
        if ($username === '') {
            return;
        }
        $this->emitUsername($username, $type, $title, $message, $ticket, $actor);
    }

    private function emitRole(string $role, string $type, string $title, string $message, RiskTicket $ticket, ?User $actor): void
    {
        $this->safeCreate([
            'recipientRole' => $role,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'ticketRef' => $ticket->reference,
            'fromUsername' => $actor?->username,
            'fromName' => $actor ? ($actor->name ?: $actor->username) : 'System',
            'fromRole' => $actor?->role ?? 'system',
        ]);
    }

    private function emitUsername(string $username, string $type, string $title, string $message, RiskTicket $ticket, ?User $actor): void
    {
        $this->safeCreate([
            'recipientUsername' => $username,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'ticketRef' => $ticket->reference,
            'fromUsername' => $actor?->username,
            'fromName' => $actor ? ($actor->name ?: $actor->username) : 'System',
            'fromRole' => $actor?->role ?? 'system',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function safeCreate(array $payload): void
    {
        try {
            $this->notifications->create($payload);
        } catch (\Throwable $e) {
            Log::warning('workflow notification failed: '.$e->getMessage());
        }
    }
}
