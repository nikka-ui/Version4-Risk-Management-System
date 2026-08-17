<?php

namespace App\Http\Controllers;

use App\Models\RiskTicket;
use App\Services\AdminTicketDetailService;
use App\Services\AiAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTicketDetailController extends Controller
{
    public function __construct(
        private readonly AdminTicketDetailService $service,
        private readonly AiAnalysisService $ai,
    ) {}

    public function index(Request $request, string $ref): RedirectResponse|View
    {
        $ticket = $this->service->findByReference($ref);
        if (! $ticket) {
            return redirect('/admin/tickets?flash=not_found');
        }

        $aiRuns = collect($this->ai->listForTicket($ref, 8))
            ->map(fn ($row) => $row->toListArray())
            ->all();

        return view('admin.ticket-detail', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'tickets',
            'title' => 'Ticket Details',
            'ticket' => $ticket,
            'aiRuns' => $aiRuns,
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }

    public function reclassify(Request $request, string $ref): RedirectResponse
    {
        $ticket = RiskTicket::query()->where('reference', $ref)->where('deleted', false)->first();
        if (! $ticket) {
            return redirect('/admin/tickets?flash=not_found');
        }

        $this->ai->reclassifyTicket($ticket, $request->user());

        return redirect('/admin/tickets/'.$ref.'?flash=reclassified');
    }
}

