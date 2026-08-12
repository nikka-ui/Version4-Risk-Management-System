<?php

namespace App\Http\Controllers;

use App\Services\OfficerTicketDetailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 27: Risk Management Officer ticket detail (Blade GET).
 * Thread-comment / reopen POSTs stay on Express.
 */
class OfficerTicketDetailController extends Controller
{
    public function __construct(
        private readonly OfficerTicketDetailService $detail,
    ) {}

    public function show(Request $request, string $reference): View|RedirectResponse
    {
        $user = $request->user();
        $payload = $this->detail->forReference($reference);

        if (! $payload) {
            return redirect()->away('/laravel/officer/tickets?flash=not_found');
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
}
