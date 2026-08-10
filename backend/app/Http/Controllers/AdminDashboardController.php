<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 14: System Administrator dashboard (Blade).
 */
class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $payload = $this->dashboard->data();

        return view('admin.dashboard', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'dashboard',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'recentUsers' => $payload['recentUsers'],
            'deletedTickets' => $payload['deletedTickets'],
            'auditLogs' => $payload['auditLogs'],
        ]);
    }
}
