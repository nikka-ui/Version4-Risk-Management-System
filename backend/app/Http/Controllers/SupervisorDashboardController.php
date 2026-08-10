<?php

namespace App\Http\Controllers;

use App\Services\SupervisorDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 7: Ticket Reporter dashboard (Blade).
 */
class SupervisorDashboardController extends Controller
{
    public function __construct(
        private readonly SupervisorDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $payload = $this->dashboard->forUsername($user->username);

        return view('supervisor.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'overview',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'recent' => $payload['recent'],
        ]);
    }
}
