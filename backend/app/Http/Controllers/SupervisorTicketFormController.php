<?php

namespace App\Http\Controllers;

use App\Services\SupervisorTicketFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 13: Ticket Reporter create/edit/preview forms (Blade GET).
 */
class SupervisorTicketFormController extends Controller
{
    public function __construct(
        private readonly SupervisorTicketFormService $forms,
    ) {}

    public function create(Request $request): View
    {
        $user = $request->user();
        $form = $this->forms->newForm($user);

        return view('supervisor.ticket-form', array_merge($form, [
            'user' => $user->toIdentityArray(),
            'title' => 'New report',
            'error' => $request->query('error'),
            'flash' => $request->query('flash'),
        ]));
    }

    public function edit(Request $request, string $reference): View|RedirectResponse
    {
        $user = $request->user();
        $form = $this->forms->editForm($user, $reference);

        if (! $form) {
            return redirect()->away('/laravel/supervisor/tickets?error='.rawurlencode('This ticket cannot be revised.'));
        }

        $pageTitle = $form['isRevise']
            ? ($form['isDeptReturn'] ? 'Revise returned report' : 'Revise report')
            : 'Edit draft';

        return view('supervisor.ticket-form', array_merge($form, [
            'user' => $user->toIdentityArray(),
            'title' => $pageTitle,
            'error' => $request->query('error'),
            'flash' => $request->query('flash'),
        ]));
    }

    public function preview(Request $request, string $reference): View|RedirectResponse
    {
        $user = $request->user();
        $form = $this->forms->previewForm($user, $reference);

        if (! $form) {
            return redirect()->away('/laravel/supervisor/tickets/new?flash=not_found');
        }

        return view('supervisor.ticket-preview', array_merge($form, [
            'user' => $user->toIdentityArray(),
            'title' => 'AI Summary Preview',
            'error' => $request->query('error'),
            'flash' => $request->query('flash'),
            'showUploadToast' => $request->query('flash') === 'evidence_uploaded',
        ]));
    }
}
