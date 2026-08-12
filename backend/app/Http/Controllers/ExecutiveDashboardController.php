<?php

namespace App\Http\Controllers;

use App\Services\ExecutiveDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 28: Executive Committee dashboard (Blade GET).
 */
class ExecutiveDashboardController extends Controller
{
    public function __construct(
        private readonly ExecutiveDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $payload = $this->dashboard->data();

        return view('executive.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'overview',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'departments' => $payload['departments'],
            'matrix' => $payload['matrix'],
            'flash' => $request->query('flash'),
        ]);
    }
}

