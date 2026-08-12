<?php

namespace App\Http\Controllers;

use App\Services\PresidentQueueService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 30: President queue lists + trends (Blade GET).
 * Ticket detail + decision POSTs stay on Express.
 */
class PresidentQueueController extends Controller
{
    public function __construct(
        private readonly PresidentQueueService $queues,
    ) {}

    public function pending(Request $request): View
    {
        return $this->renderQueue($request, 'pending');
    }

    public function high(Request $request): View
    {
        return $this->renderQueue($request, 'high');
    }

    public function critical(Request $request): View
    {
        return $this->renderQueue($request, 'critical');
    }

    public function trends(Request $request): View
    {
        $user = $request->user();
        $payload = $this->queues->trendsData();

        return view('president.trends', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'trends',
            'title' => 'Trends',
            'stats' => $payload['stats'],
            'trends' => $payload['trends'],
            'flash' => $request->query('flash'),
        ]);
    }

    private function renderQueue(Request $request, string $filter): View
    {
        $user = $request->user();
        $payload = $this->queues->listForFilter($filter);

        return view('president.queue', [
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
