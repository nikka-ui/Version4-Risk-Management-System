<?php

namespace App\Http\Controllers;

use App\Services\PresidentDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 29: President dashboard (Blade GET).
 */
class PresidentDashboardController extends Controller
{
    public function __construct(
        private readonly PresidentDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $payload = $this->dashboard->data();

        return view('president.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'overview',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'org' => $payload['org'],
            'matrix' => $payload['matrix'],
            'flash' => $request->query('flash'),
        ]);
    }
}

