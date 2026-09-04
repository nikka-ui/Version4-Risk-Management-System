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
        $isCompliance = str_starts_with($request->path(), 'compliance/');
        $consolePrefix = $isCompliance ? '/compliance' : '/officer';

        if (! $payload) {
            return redirect()->away($consolePrefix.($isCompliance ? '?flash=not_found' : '/tickets?flash=not_found'));
        }

        $caps = $payload['capabilities'];
        if ($isCompliance) {
            $caps['canReopen'] = false;
            $caps['canApproveAiRoute'] = false;
            $caps['canReviewDecision'] = false;
        }

        return view('officer.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $isCompliance ? 'validation' : $payload['activeNav'],
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
            'capabilities' => $caps,
            'consolePrefix' => $consolePrefix,
            'layoutName' => $isCompliance ? 'layouts.compliance' : 'layouts.officer',
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
        $prefix = str_starts_with($request->path(), 'compliance/') ? '/compliance/tickets/' : '/officer/tickets/';
        $ticket = $this->threadComments->findAccessible($reference, $user);
        if (! $ticket) {
            return redirect()->away($prefix === '/compliance/tickets/' ? '/compliance?flash=not_found' : '/officer/tickets?flash=not_found');
        }

        try {
            $ticket = $this->threadComments->add($ticket, $user, $request->all());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to post comment.';

            return redirect()->away($prefix.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away($prefix.rawurlencode($reference).'?flash=rmu_thread_comment');
    }

    public function approveAiRoute(Request $request, string $reference): RedirectResponse
    {
        return $this->mutateOfficer($request, $reference, fn ($ticket, $user) => $this->officerTickets->approveAiRoute($ticket, $user, $request->all()), 'ai_route_approved');
    }

    public function reviewDecision(Request $request, string $reference): RedirectResponse
    {
        return $this->mutateOfficer($request, $reference, fn ($ticket, $user) => $this->officerTickets->recordReviewDecision($ticket, $user, $request->all()), 'review_recorded');
    }

    public function validateAccomplishment(Request $request, string $reference): RedirectResponse
    {
        $prefix = str_starts_with($request->path(), 'compliance/') ? '/compliance/tickets/' : '/officer/tickets/';

        return $this->mutateOfficer(
            $request,
            $reference,
            fn ($ticket, $user) => $this->officerTickets->validateAccomplishment($ticket, $user, $request->all()),
            'accomplishment_validated',
            $prefix,
        );
    }

    public function returnAccomplishment(Request $request, string $reference): RedirectResponse
    {
        $prefix = str_starts_with($request->path(), 'compliance/') ? '/compliance/tickets/' : '/officer/tickets/';

        return $this->mutateOfficer(
            $request,
            $reference,
            fn ($ticket, $user) => $this->officerTickets->returnAccomplishment($ticket, $user, $request->all()),
            'accomplishment_returned',
            $prefix,
        );
    }

    /**
     * @param  callable(\App\Models\RiskTicket, \App\Models\User): \App\Models\RiskTicket  $action
     */
    private function mutateOfficer(
        Request $request,
        string $reference,
        callable $action,
        string $flash,
        string $prefix = '/officer/tickets/',
    ): RedirectResponse {
        $user = $request->user();
        $ticket = $this->officerTickets->findForOfficer($reference);
        if (! $ticket) {
            return redirect()->away($prefix === '/compliance/tickets/' ? '/compliance?flash=not_found' : '/officer/tickets?flash=not_found');
        }

        try {
            $ticket = $action($ticket, $user);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to update ticket.';

            return redirect()->away($prefix.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away($prefix.rawurlencode($reference).'?flash='.$flash);
    }
}
