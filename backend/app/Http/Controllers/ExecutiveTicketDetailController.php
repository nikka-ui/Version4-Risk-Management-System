<?php

namespace App\Http\Controllers;

use App\Services\ExecutiveTicketDetailService;
use App\Services\ExpressOrgMirrorService;
use App\Services\ThreadCommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phase 6 slice 4 + Phase 7 slice 10: Executive ticket detail (Blade GET + comment POST).
 * Uploads stay on Express.
 */
class ExecutiveTicketDetailController extends Controller
{
    public function __construct(
        private readonly ExecutiveTicketDetailService $detail,
        private readonly ThreadCommentService $threadComments,
        private readonly ExpressOrgMirrorService $orgMirror,
    ) {}

    public function show(Request $request, string $reference): View|RedirectResponse
    {
        $payload = $this->detail->forReference($reference);

        if (! $payload) {
            return redirect()->away('/executive/register?flash=not_found');
        }

        return view('executive.ticket-show', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['ticket']['reference'],
            'stats' => $payload['stats'],
            'ticket' => $payload['ticket'],
            'fiveW1H' => $payload['fiveW1H'],
            'attachments' => $payload['attachments'],
            'threadComments' => $payload['threadComments'],
            'capabilities' => $payload['capabilities'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }

    public function comment(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        $ticket = $this->threadComments->findAccessible($reference, $user);
        if (! $ticket) {
            return redirect()->away('/executive/register?flash=not_found');
        }

        try {
            $ticket = $this->threadComments->add($ticket, $user, $request->all());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to post comment.';

            return redirect()->away('/executive/tickets/'.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away('/executive/tickets/'.rawurlencode($reference).'?flash=executive_comment_added');
    }
}
