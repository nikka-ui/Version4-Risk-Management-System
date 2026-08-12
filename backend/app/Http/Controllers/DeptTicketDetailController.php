<?php

namespace App\Http\Controllers;

use App\Services\DeptTicketDetailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 24: Department Head ticket detail (Blade GET).
 * Ownership / action-plan / close POSTs stay on Express.
 */
class DeptTicketDetailController extends Controller
{
    public function __construct(
        private readonly DeptTicketDetailService $detail,
    ) {}

    public function show(Request $request, string $reference): View|RedirectResponse
    {
        $user = $request->user();
        $payload = $this->detail->forUser($user, $reference);

        if (! $payload) {
            return redirect()->away('/laravel/dept/tickets?flash=not_found');
        }

        return view('dept.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['ticket']['reference'],
            'stats' => $payload['stats'],
            'ticket' => $payload['ticket'],
            'fiveW1H' => $payload['fiveW1H'],
            'attachments' => $payload['attachments'],
            'actionPlan' => $payload['actionPlan'],
            'accomplishment' => $payload['accomplishment'],
            'timeline' => $payload['timeline'],
            'reassignments' => $payload['reassignments'],
            'departments' => $payload['departments'],
            'capabilities' => $payload['capabilities'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }
}
