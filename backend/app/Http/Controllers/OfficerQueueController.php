<?php

namespace App\Http\Controllers;

use App\Services\OfficerQueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 26 + Phase 6 slice 5: RMO queue lists + legacy path aliases (Blade GET).
 * Ticket detail + POSTs stay on Express.
 */
class OfficerQueueController extends Controller
{
    public function __construct(
        private readonly OfficerQueueService $queues,
    ) {}

    public function tickets(Request $request): View
    {
        return $this->render($request, 'tickets');
    }

    public function overdue(Request $request): View
    {
        return $this->render($request, 'overdue');
    }

    public function monitoring(Request $request): View
    {
        return $this->render($request, 'monitoring');
    }

    public function actionPlans(Request $request): View
    {
        return $this->render($request, 'action-plans');
    }

    public function aiReview(): RedirectResponse
    {
        return redirect()->away('/officer/tickets');
    }

    public function review(): RedirectResponse
    {
        return redirect()->away('/officer/tickets');
    }

    public function finalValidation(): RedirectResponse
    {
        return redirect()->away('/officer/action-plans');
    }

    private function render(Request $request, string $filter): View
    {
        $user = $request->user();
        $payload = $this->queues->listForFilter($filter);

        return view('officer.queue', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['title'],
            'pageDesc' => $payload['desc'],
            'emptyMessage' => $payload['emptyMessage'],
            'stats' => $payload['stats'],
            'tickets' => $payload['tickets'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }
}
