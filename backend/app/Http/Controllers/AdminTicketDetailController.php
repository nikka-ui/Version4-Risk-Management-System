<?php

namespace App\Http\Controllers;

use App\Services\AdminTicketDetailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTicketDetailController extends Controller
{
    public function __construct(
        private readonly AdminTicketDetailService $service,
    ) {}

    public function index(Request $request, string $ref): RedirectResponse|View
    {
        $ticket = $this->service->findByReference($ref);
        if (! $ticket) {
            return redirect('/admin/tickets?flash=not_found');
        }

        return view('admin.ticket-detail', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'tickets',
            'title' => 'Ticket Details',
            'ticket' => $ticket,
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }
}

