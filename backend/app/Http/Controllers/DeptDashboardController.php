<?php

namespace App\Http\Controllers;

use App\Services\DeptDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 22: Department Head dashboard (Blade GET).
 * Queue/detail POSTs stay on Express.
 */
class DeptDashboardController extends Controller
{
    public function __construct(
        private readonly DeptDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $payload = $this->dashboard->forUser($user);

        return view('dept.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'dashboard',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'recent' => $payload['recent'],
            'flash' => $request->query('flash'),
        ]);
    }
}
