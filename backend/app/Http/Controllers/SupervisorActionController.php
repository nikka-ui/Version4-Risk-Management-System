<?php

namespace App\Http\Controllers;

use App\Services\SupervisorDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 12: Ticket Reporter action-required queue (Blade).
 */
class SupervisorActionController extends Controller
{
    public function __construct(
        private readonly SupervisorDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('supervisor.actions', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'actions',
            'title' => 'Action required',
            'tickets' => $this->dashboard->actionsForUsername($user->username),
            'flash' => $request->query('flash'),
        ]);
    }
}
