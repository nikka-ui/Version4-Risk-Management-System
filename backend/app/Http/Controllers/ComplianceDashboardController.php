<?php

namespace App\Http\Controllers;

use App\Models\RiskTicket;
use App\Services\OfficerDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComplianceDashboardController extends Controller
{
    public function __construct(
        private readonly OfficerDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tickets = $this->dashboard->tickets()
            ->filter(fn (RiskTicket $t) => (string) $t->status === 'under_audit')
            ->values()
            ->map(fn (RiskTicket $t) => [
                'reference' => $t->reference,
                'title' => $t->title ?: '—',
                'department' => $t->department ?: '—',
                'status' => $t->status,
                'updatedAt' => optional($t->source_updated_at)?->toIso8601String(),
            ])
            ->all();

        $stats = [
            'pendingValidation' => count($tickets),
            'total' => $this->dashboard->tickets()->count(),
        ];

        return view('compliance.dashboard', [
            'user' => $user->toIdentityArray(),
            'title' => 'Compliance validation',
            'activeNav' => 'validation',
            'stats' => $stats,
            'tickets' => $tickets,
            'flash' => $request->query('flash'),
        ]);
    }
}
