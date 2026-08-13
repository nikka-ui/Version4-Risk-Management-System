<?php

namespace App\Http\Controllers;

use App\Services\DraftTicketService;
use App\Services\ExpressOrgMirrorService;
use App\Services\ReporterEvidenceMutationService;
use App\Services\SupervisorDashboardService;
use App\Services\SupervisorTicketDetailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Phase 5 slice 8–9 + Phase 7 slice 7 + Phase 8 slice 2: Ticket Reporter list/detail + draft delete + evidence/accomplishment POSTs.
 */
class SupervisorTicketController extends Controller
{
    public function __construct(
        private readonly SupervisorDashboardService $tickets,
        private readonly SupervisorTicketDetailService $detail,
        private readonly DraftTicketService $drafts,
        private readonly ExpressOrgMirrorService $orgMirror,
        private readonly ReporterEvidenceMutationService $evidence,
    ) {}

    public function index(Request $request): View
    {
        return $this->render($request, $request->query('filter'));
    }

    public function drafts(Request $request): View
    {
        return $this->render($request, 'draft');
    }

    public function submitted(Request $request): View
    {
        return $this->render($request, 'submitted');
    }

    public function returned(Request $request): View
    {
        return $this->render($request, 'returned');
    }

    public function overdue(Request $request): View
    {
        return $this->render($request, 'overdue');
    }

    public function show(Request $request, string $reference): View|RedirectResponse
    {
        $user = $request->user();
        $payload = $this->detail->forUsername($user->username, $reference);

        if (! $payload) {
            return redirect()->away('/supervisor/tickets?flash=not_found');
        }

        if (! empty($payload['redirect_edit'])) {
            return redirect()->away('/supervisor/tickets/'.rawurlencode($payload['reference']).'/edit');
        }

        return view('supervisor.ticket-show', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'tickets',
            'title' => $payload['ticket']['reference'],
            'ticket' => $payload['ticket'],
            'attachments' => $payload['attachments'],
            'fiveW1H' => $payload['fiveW1H'],
            'timeline' => $payload['timeline'],
            'accomplishment' => $payload['accomplishment'],
            'capabilities' => $payload['capabilities'],
            'implementationEvidence' => $payload['implementationEvidence'],
            'actionPlanSummary' => $payload['actionPlanSummary'],
            'error' => $request->query('error'),
            'flash' => $request->query('flash'),
        ]);
    }

    public function addEvidence(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        try {
            $ticket = $this->evidence->addEvidence($user, $reference, $this->uploadedFiles($request));
        } catch (HttpException $e) {
            return redirect()->away('/supervisor/tickets?flash=not_found');
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to upload evidence.';

            return redirect()->away('/supervisor/tickets/'.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away('/supervisor/tickets/'.rawurlencode($ticket->reference).'?flash=evidence_added');
    }

    public function submitAccomplishment(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        try {
            $ticket = $this->evidence->submitAccomplishment($user, $reference, $request->all(), $this->uploadedFiles($request));
        } catch (HttpException $e) {
            return redirect()->away('/supervisor/tickets?flash=not_found');
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Unable to submit accomplishment.';

            return redirect()->away('/supervisor/tickets/'.rawurlencode($reference).'?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicket($ticket->toExpressArray());

        return redirect()->away('/supervisor/accomplishments?flash=accomplishment_submitted');
    }

    public function destroy(Request $request, string $reference): RedirectResponse
    {
        $user = $request->user();
        $ticket = $this->drafts->findOwnedDraft($reference, $user);
        if (! $ticket) {
            return redirect()->away('/supervisor/tickets?error='.rawurlencode('Ticket not found.'));
        }

        try {
            $this->drafts->deleteDraft($ticket, $user);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Only draft tickets can be deleted.';

            return redirect()->away('/supervisor/tickets?error='.rawurlencode((string) $msg));
        }

        $this->orgMirror->syncTicketDeleteDraft($reference);

        return redirect()->away('/supervisor/tickets?flash=draft_deleted');
    }

    private function render(Request $request, ?string $filter): View
    {
        $user = $request->user();
        $payload = $this->tickets->listForUsername($user->username, $filter);

        return view('supervisor.tickets', [
            'user' => $user->toIdentityArray(),
            'activeNav' => $payload['activeNav'],
            'title' => $payload['title'],
            'pageDesc' => $payload['desc'],
            'filter' => $payload['filter'],
            'counts' => $payload['counts'],
            'tickets' => $payload['tickets'],
            'showDueColumn' => $payload['showDueColumn'],
            'error' => $request->query('error'),
            'flash' => $request->query('flash'),
        ]);
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
