<?php

namespace App\Http\Controllers;

use App\Services\PresidentTicketDetailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 31: President ticket detail (Blade GET).
 * Decision / comment POSTs stay on Express.
 */
class PresidentTicketDetailController extends Controller
{
    public function __construct(
        private readonly PresidentTicketDetailService $detail,
    ) {}

    public function show(Request $request, string $reference): View|RedirectResponse
    {
        $user = $request->user();
        $payload = $this->detail->forReference($reference);

        if (! $payload) {
            return redirect()->away('/laravel/president/pending?flash=not_found');
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
}
