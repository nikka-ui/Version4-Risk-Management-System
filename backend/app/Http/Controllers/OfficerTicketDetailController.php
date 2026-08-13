<?php

namespace App\Http\Controllers;

use App\Services\ExpressOrgMirrorService;
use App\Services\OfficerTicketDetailService;
use App\Services\OfficerTicketService;
use App\Services\ThreadCommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phase 5 slice 27 + Phase 7 slice 8 + slice 11: RMO ticket detail (Blade GET + reopen + thread-comment POSTs).
 */
class OfficerTicketDetailController extends Controller
{
    public function __construct(
        private readonly OfficerTicketDetailService $detail,
        private readonly OfficerTicketService $officerTickets,
        private readonly ThreadCommentService $threadComments,
        private readonly ExpressOrgMirrorService $orgMirror,
    ) {}

    public function show(Request $request, string $reference): View|RedirectResponse
    {
        $user = $request->user();
        $payload = $this->detail->forReference($reference);

        if (! $payload) {
            return redirect()->away('/officer/tickets?flash=not_found');
        }

        return view('officer.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['ticket']['reference'],
            'stats' => $payload['stats'],
            'ticket' => $payload['ticket'],
            'fiveW1H' => $payload['fiveW1H'],
            'attachments' => $payload['attachments'],
            'actionPlan' => $payload['actionPlan'],
            'accomplishment' => $payload['accomplishment'],
            'closure' => $payload['closure'],
            'threadComments' => $payload['threadComments'],
            'departments' => $payload['departments'],
            'capabilities' => $payload['capabilities'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }

    public function reopen(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        $ticket = $this->officerTickets->findForOfficer($reference);
        if (! $ticket) {
            return redirect()->away('/officer/tickets?flash=not_found');
        }

        try {
            $ticket = $this->officerTickets->reopen($ticket, $user, $request->all());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to reopen ticket.';

            return redirect()->away('/officer/tickets/'.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away('/officer/tickets/'.rawurlencode($reference).'?flash=ticket_reopened');
    }

    public function comment(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        $ticket = $this->threadComments->findAccessible($reference, $user);
        if (! $ticket) {
            return redirect()->away('/officer/tickets?flash=not_found');
        }

        try {
            $ticket = $this->threadComments->add($ticket, $user, $request->all());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to post comment.';

            return redirect()->away('/officer/tickets/'.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away('/officer/tickets/'.rawurlencode($reference).'?flash=rmu_thread_comment');
    }
}
