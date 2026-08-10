<?php

namespace App\Http\Controllers;

use App\Services\SupervisorDashboardService;
use App\Services\SupervisorTicketDetailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 8–9: Ticket Reporter ticket list + read-only detail (Blade).
 */
class SupervisorTicketController extends Controller
{
    public function __construct(
        private readonly SupervisorDashboardService $tickets,
        private readonly SupervisorTicketDetailService $detail,
    ) {}

    public function index(Request $request): View
    {
        return $this->render($request, $request->query('filter'));
    }

    public function drafts(Request $request): View
    {
        return $this->render($request, 'draft');
    }

    public function submitted(Request $request): View
    {
        return $this->render($request, 'submitted');
    }

    public function returned(Request $request): View
    {
        return $this->render($request, 'returned');
    }

    public function overdue(Request $request): View
    {
        return $this->render($request, 'overdue');
    }

    public function show(Request $request, string $reference): View|RedirectResponse
    {
        $user = $request->user();
        $payload = $this->detail->forUsername($user->username, $reference);

        if (! $payload) {
            return redirect()->away('/laravel/supervisor/tickets?flash=not_found');
        }

        if (! empty($payload['redirect_edit'])) {
            return redirect()->away('/supervisor/tickets/'.rawurlencode($payload['reference']).'/edit');
        }

        return view('supervisor.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'tickets',
            'title' => $payload['ticket']['reference'],
            'ticket' => $payload['ticket'],
            'attachments' => $payload['attachments'],
            'fiveW1H' => $payload['fiveW1H'],
            'timeline' => $payload['timeline'],
            'accomplishment' => $payload['accomplishment'],
            'error' => $request->query('error'),
            'flash' => $request->query('flash'),
        ]);
    }

    private function render(Request $request, ?string $filter): View
    {
        $user = $request->user();
        $payload = $this->tickets->listForUsername($user->username, $filter);

        return view('supervisor.tickets', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['title'],
            'pageDesc' => $payload['desc'],
            'filter' => $payload['filter'],
            'counts' => $payload['counts'],
            'tickets' => $payload['tickets'],
            'showDueColumn' => $payload['showDueColumn'],
            'error' => $request->query('error'),
        ]);
    }
}
