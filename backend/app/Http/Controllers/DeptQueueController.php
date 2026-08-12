<?php

namespace App\Http\Controllers;

use App\Services\DeptDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 23: Department Head queue lists (Blade GET).
 * Ticket detail + ownership POSTs stay on Express.
 */
class DeptQueueController extends Controller
{
    public function __construct(
        private readonly DeptDashboardService $queues,
    ) {}

    public function inbox(Request $request): View
    {
        return $this->render($request, 'inbox');
    }

    public function active(Request $request): View
    {
        return $this->render($request, 'active');
    }

    public function drafts(Request $request): View
    {
        return $this->render($request, 'drafts');
    }

    public function returned(Request $request): View
    {
        return $this->render($request, 'returned');
    }

    public function overdue(Request $request): View
    {
        return $this->render($request, 'overdue');
    }

    public function closure(Request $request): View
    {
        return $this->render($request, 'closure');
    }

    public function tickets(Request $request): View
    {
        return $this->render($request, 'tickets');
    }

    private function render(Request $request, string $filter): View
    {
        $user = $request->user();
        $payload = $this->queues->listForUser($user, $filter);
        $view = match ($payload['variant']) {
            'drafts' => 'dept.drafts',
            'returned' => 'dept.returned',
            default => 'dept.queue',
        };

        return view($view, [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['title'],
            'pageDesc' => $payload['desc'],
            'emptyMessage' => $payload['emptyMessage'],
            'stats' => $payload['stats'],
            'tickets' => $payload['tickets'],
            'showDueColumn' => $payload['showDueColumn'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }
}
