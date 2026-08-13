<?php

namespace App\Http\Controllers;

use App\Services\ExecutiveDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 28 + Phase 6 slice 3: Executive dashboard + oversight pages (Blade GET).
 * Ticket detail is Phase 6 slice 4; comment POSTs stay on Express.
 */
class ExecutiveDashboardController extends Controller
{
    public function __construct(
        private readonly ExecutiveDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $payload = $this->dashboard->data();

        return view('executive.dashboard', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'overview',
            'title' => 'Dashboard',
            'stats' => $payload['stats'],
            'departments' => $payload['departments'],
            'matrix' => $payload['matrix'],
            'flash' => $request->query('flash'),
        ]);
    }

    public function heatmap(Request $request): View
    {
        $payload = $this->dashboard->data();

        return view('executive.heatmap', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'heatmap',
            'title' => 'Heatmap',
            'stats' => $payload['stats'],
            'matrix' => $payload['matrix'],
            'flash' => $request->query('flash'),
        ]);
    }

    public function reports(Request $request): View
    {
        $payload = $this->dashboard->data();

        return view('executive.reports', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'reports',
            'title' => 'Reports',
            'stats' => $payload['stats'],
            'categories' => $payload['categories'],
            'highCriticalTickets' => array_slice($payload['highCriticalTickets'], 0, 15),
            'flash' => $request->query('flash'),
        ]);
    }

    public function trends(Request $request): View
    {
        $payload = $this->dashboard->data();

        return view('executive.trends', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'trends',
            'title' => 'Trends',
            'stats' => $payload['stats'],
            'trends' => $payload['trends'],
            'flash' => $request->query('flash'),
        ]);
    }

    public function statistics(Request $request): View
    {
        $payload = $this->dashboard->data();

        return view('executive.statistics', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'statistics',
            'title' => 'Statistics',
            'stats' => $payload['stats'],
            'byStatus' => $payload['byStatus'],
            'flash' => $request->query('flash'),
        ]);
    }

    public function departments(Request $request): View
    {
        $payload = $this->dashboard->data();

        return view('executive.departments', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'departments',
            'title' => 'Department Performance',
            'stats' => $payload['stats'],
            'departments' => $payload['departments'],
            'flash' => $request->query('flash'),
        ]);
    }

    public function register(Request $request): View
    {
        $level = (string) $request->query('level', '');
        $category = (string) $request->query('category', '');
        $payload = $this->dashboard->register($level !== '' ? $level : null, $category !== '' ? $category : null);

        return view('executive.register', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'register',
            'title' => $level === 'critical' && $category === '' ? 'Critical risks' : 'Risk Register',
            'pageDesc' => $level === 'critical' && $category === ''
                ? 'Extreme/Critical risk reports prioritized for executive oversight.'
                : 'Organization-wide risk register (view only). Sorted from Low to Critical — use filters to narrow by level or category.',
            'emptyMessage' => $level === 'critical' && $category === ''
                ? 'No critical risk reports at this time.'
                : 'No risk reports match your filters.',
            'stats' => $payload['stats'],
            'tickets' => $payload['tickets'],
            'filters' => $payload['filters'],
            'categories' => $payload['categories'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }

    public function critical(Request $request): RedirectResponse
    {
        return redirect()->away('/executive/register?level=critical');
    }

    public function ticketsIndex(Request $request): RedirectResponse
    {
        $qs = $request->getQueryString();

        return redirect()->away('/executive/register'.($qs ? '?'.$qs : ''));
    }
}
