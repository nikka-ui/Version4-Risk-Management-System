<?php

namespace App\Http\Controllers;

use App\Services\AdminTicketService;
use App\Services\ExpressOrgMirrorService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 18 + Phase 7 slice 5: System Administrator tickets (Blade GET + soft-delete POST).
 */
class AdminTicketController extends Controller
{
    public function __construct(
        private readonly AdminTicketService $tickets,
        private readonly ExpressOrgMirrorService $orgMirror,
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

    public function destroy(Request $request, string $ref): RedirectResponse
    {
        $result = $this->tickets->softDelete($ref, $request->user(), (string) $request->input('reason', ''));
        if (! empty($result['error'])) {
            if ($result['error'] === 'Ticket not found.') {
                return redirect()->away('/admin/tickets?flash=not_found');
            }

            return redirect()->away('/admin/tickets?error='.rawurlencode((string) $result['error']));
        }

        $ticket = $result['ticket'];
        $this->orgMirror->syncTicketSoftDelete($ticket, $this->audit(
            $request,
            'ticket_deleted',
            'Soft-deleted ticket '.$ticket['reference'].': '.$ticket['deletionReason'],
        ), [
            'type' => 'ticket_deleted',
            'title' => 'Ticket deleted',
            'message' => 'Ticket '.$ticket['reference'].' was soft-deleted.',
        ]);

        return redirect()->away('/admin/tickets?flash=ticket_deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function audit(Request $request, string $action, string $description): array
    {
        $user = $request->user();

        return [
            'username' => $user->username,
            'role' => $user->role,
            'roleLabel' => $user->role_label ?: Roles::label($user->role),
            'action' => $action,
            'module' => 'Ticket Management',
            'description' => $description,
            'ip' => $request->ip(),
        ];
    }
}
