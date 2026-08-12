<?php

namespace App\Http\Controllers;

use App\Services\OfficerDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 25: Risk Management Officer dashboard (Blade GET).
 * Queues / detail remain on Express for now.
 */
class OfficerDashboardController extends Controller
{
    public function __construct(
        private readonly OfficerDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $payload = $this->dashboard->data();

        return view('officer.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'dashboard',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'departments' => $payload['departments'],
            'matrix' => $payload['matrix'],
            'flash' => $request->query('flash'),
        ]);
    }
}
