<?php

namespace App\Http\Controllers;

use App\Services\ExpressOrgMirrorService;
use App\Services\PresidentTicketDetailService;
use App\Services\PresidentTicketService;
use App\Services\ThreadCommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phase 5 slice 31 + Phase 7 slice 9 + slice 12: President ticket detail (Blade GET + decision + comment POSTs).
 */
class PresidentTicketDetailController extends Controller
{
    public function __construct(
        private readonly PresidentTicketDetailService $detail,
        private readonly PresidentTicketService $presidentTickets,
        private readonly ThreadCommentService $threadComments,
        private readonly ExpressOrgMirrorService $orgMirror,
    ) {}

    public function show(Request $request, string $reference): View|RedirectResponse
    {
        $user = $request->user();
        $payload = $this->detail->forReference($reference);

        if (! $payload) {
            return redirect()->away('/president/pending?flash=not_found');
        }

        return view('president.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['ticket']['reference'],
            'stats' => $payload['stats'],
            'ticket' => $payload['ticket'],
            'fiveW1H' => $payload['fiveW1H'],
            'attachments' => $payload['attachments'],
            'actionPlan' => $payload['actionPlan'],
            'finalResolution' => $payload['finalResolution'],
            'rmuRecommendations' => $payload['rmuRecommendations'],
            'compliance' => $payload['compliance'],
            'decisions' => $payload['decisions'],
            'threadComments' => $payload['threadComments'],
            'capabilities' => $payload['capabilities'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }

    public function decide(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        $ticket = $this->presidentTickets->findForPresident($reference);
        if (! $ticket) {
            return redirect()->away('/president/pending?flash=not_found');
        }

        try {
            $ticket = $this->presidentTickets->recordDecision($ticket, $user, $request->all());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to record decision.';

            return redirect()->away('/president/tickets/'.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());
        $decision = strtolower(trim((string) ($request->input('decision') ?? '')));
        $normalized = $decision === 'decline' ? 'reject' : $decision;
        $flash = in_array($normalized, ['approve', 'reject', 'return', 'close'], true)
            ? 'president_'.$normalized
            : 'president_approve';

        return redirect()->away('/president/tickets/'.rawurlencode($reference).'?flash='.$flash);
    }

    public function comment(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        $ticket = $this->threadComments->findAccessible($reference, $user);
        if (! $ticket) {
            return redirect()->away('/president/pending?flash=not_found');
        }

        try {
            $ticket = $this->threadComments->add($ticket, $user, $request->all());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to post comment.';

            return redirect()->away('/president/tickets/'.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away('/president/tickets/'.rawurlencode($reference).'?flash=president_comment');
    }
}
