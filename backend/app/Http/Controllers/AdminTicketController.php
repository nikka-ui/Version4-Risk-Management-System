<?php

namespace App\Http\Controllers;

use App\Services\AdminTicketService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 18: System Administrator ticket management (Blade GET).
 * Delete POST stays on Express.
 */
class AdminTicketController extends Controller
{
    public function __construct(
        private readonly AdminTicketService $tickets,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        if ($status === 'open') {
            $status = 'open';
        } elseif ($status === 'closed') {
            $status = 'closed';
        }

        $payload = $this->tickets->list(
            $request->query('q'),
            $request->query('department') ?: null,
            $request->query('level') ?: null,
            $status ?: null,
            $request->query('deleted') === '1',
        );

        $user = $request->user();

        return view('admin.tickets', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'tickets',
            'title' => 'Ticket Management',
            'tickets' => $payload['tickets'],
            'departments' => $payload['departments'],
            'statusOptions' => $payload['statusOptions'],
            'filters' => $payload['filters'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }
}
