<?php

namespace App\Http\Controllers;

use App\Services\OfficerQueueService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 26: Risk Management Officer queue lists (Blade GET).
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
