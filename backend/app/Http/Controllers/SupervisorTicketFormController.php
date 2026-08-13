<?php

namespace App\Http\Controllers;

use App\Models\RiskTicket;
use App\Services\ExpressOrgMirrorService;
use App\Services\ReporterTicketFormMutationService;
use App\Services\SubmitTicketService;
use App\Services\SupervisorTicketFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Phase 5 slice 13 + Phase 7 slice 7 + Phase 8 slice 1: Ticket Reporter create/edit/preview + multipart uploads.
 */
class SupervisorTicketFormController extends Controller
{
    public function __construct(
        private readonly SupervisorTicketFormService $forms,
        private readonly SubmitTicketService $submitter,
        private readonly ExpressOrgMirrorService $orgMirror,
        private readonly ReporterTicketFormMutationService $mutations,
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
            return redirect()->away('/supervisor/tickets?error='.rawurlencode('This ticket cannot be revised.'));
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
            return redirect()->away('/supervisor/tickets/new?flash=not_found');
        }

        return view('supervisor.ticket-preview', array_merge($form, [
            'user' => $user->toIdentityArray(),
            'title' => 'AI Summary Preview',
            'error' => $request->query('error'),
            'flash' => $request->query('flash'),
            'showUploadToast' => $request->query('flash') === 'evidence_uploaded',
        ]));
    }

    public function storePreview(Request $request): RedirectResponse
    {
        $user = $request->user();
        $files = $this->uploadedFiles($request);
        try {
            $ticket = $this->mutations->createPreview($user, $request->all(), $files);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to create ticket.';

            return redirect()->away('/supervisor/tickets/new?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away(
            '/supervisor/tickets/new/preview/'.rawurlencode($ticket->reference).'?flash=preview_generated'
        );
    }

    public function updateEdit(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        $files = $this->uploadedFiles($request);
        try {
            $ticket = $this->mutations->updateEdit($user, $reference, $request->all(), $files);
        } catch (HttpException $e) {
            return redirect()->away('/supervisor/tickets?error='.rawurlencode('This ticket cannot be revised.'));
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to update ticket.';

            return redirect()->away(
                '/supervisor/tickets/'.rawurlencode($reference).'/edit?error='.rawurlencode((string) $msg)
            );
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());
        $flash = count($files) > 0
            ? 'evidence_uploaded&count='.count($files)
            : 'draft_updated';

        return redirect()->away(
            '/supervisor/tickets/new/preview/'.rawurlencode($ticket->reference).'?flash='.$flash
        );
    }

    public function saveDraft(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('submitted_by', $user->username)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            return redirect()->away('/supervisor/tickets/new?flash=not_found');
        }

        return redirect()->away('/supervisor/tickets?flash=draft_saved');
    }

    public function submit(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        $confirmed = in_array($request->input('confirmBox'), [true, 1, '1', 'on'], true);
        if (! $confirmed) {
            return redirect()->away(
                '/supervisor/tickets/new/preview/'.rawurlencode($reference)
                .'?error='.rawurlencode('Please confirm the information is accurate.')
            );
        }

        $form = $this->forms->previewForm($user, $reference);
        if (! $form) {
            return redirect()->away('/supervisor/tickets/new?flash=not_found');
        }
        if (! empty($form['revisionBlocked'])) {
            return redirect()->away(
                '/supervisor/tickets/new/preview/'.rawurlencode($reference)
                .'?error='.rawurlencode('You must update the report details or evidence before resubmitting.')
            );
        }

        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('submitted_by', $user->username)
            ->where('deleted', false)
            ->first();
        if (! $ticket) {
            return redirect()->away('/supervisor/tickets/new?flash=not_found');
        }

        try {
            $ticket = $this->submitter->submit($ticket, $user);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to submit ticket.';

            return redirect()->away('/supervisor/tickets/'.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away('/supervisor/tickets/'.rawurlencode($reference).'?flash=submitted');
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(Request $request): array
    {
        $files = $request->file('attachments', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        return array_values(array_filter(is_array($files) ? $files : []));
    }
}
