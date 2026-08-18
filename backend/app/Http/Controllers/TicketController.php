<?php

namespace App\Http\Controllers;

use App\Models\Accomplishment;
use App\Models\RiskTicket;
use App\Models\User;
use App\Services\DeptTicketService;
use App\Services\DraftTicketService;
use App\Services\OfficerTicketService;
use App\Services\PresidentTicketService;
use App\Services\SubmitTicketService;
use App\Services\ThreadCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 3: ticket read/write APIs (Postgres). Express UI remains SoT until flag-on cutover.
 */
class TicketController extends Controller
{
    public function __construct(
        private readonly DraftTicketService $drafts,
        private readonly SubmitTicketService $submitter,
        private readonly DeptTicketService $deptTickets,
        private readonly PresidentTicketService $presidentTickets,
        private readonly OfficerTicketService $officerTickets,
        private readonly ThreadCommentService $threadComments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = RiskTicket::query()->orderByDesc('source_updated_at')->orderByDesc('id');

        if (! $request->boolean('include_deleted')) {
            $query->where('deleted', false);
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('department')) {
            $query->where('department', (string) $request->query('department'));
        }

        if ($request->filled('submittedBy')) {
            $query->where('submitted_by', (string) $request->query('submittedBy'));
        }

        if ($request->filled('mine') && $request->boolean('mine')) {
            /** @var User $user */
            $user = $request->user();
            $query->where('submitted_by', $user->username);
        }

        if ($request->filled('search')) {
            $needle = mb_strtolower((string) $request->query('search'));
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(reference) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(title, \'\')) LIKE ?', [$term]);
            });
        }

        $limit = min(max((int) $request->query('limit', 100), 1), 500);
        $tickets = $query->limit($limit)->get()->map(fn (RiskTicket $t) => $t->toListArray())->values();

        return response()->json([
            'tickets' => $tickets,
            'count' => $tickets->count(),
        ]);
    }

    public function show(string $reference): JsonResponse
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->first();

        if (! $ticket || ($ticket->deleted && ! request()->boolean('include_deleted'))) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->drafts->create($user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()], 201);
    }

    public function update(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->drafts->findOwnedDraft($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->drafts->updateEditable($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function destroy(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->drafts->findOwnedDraft($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if ($ticket->status !== 'draft') {
            return response()->json(['message' => 'Only draft tickets can be deleted.'], 422);
        }

        $ref = $this->drafts->deleteDraft($ticket, $user);

        return response()->json(['reference' => $ref]);
    }

    public function submit(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('submitted_by', $user->username)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->submitter->submit($ticket, $user);

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function accept(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->deptTickets->findForDeptHead($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->deptTickets->accept($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function reject(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->deptTickets->findForDeptHead($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->deptTickets->reject($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function saveActionPlan(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->deptTickets->findForDeptHead($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->deptTickets->saveActionPlan($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function returnForRevision(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->deptTickets->findForDeptHead($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->deptTickets->returnForRevision($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function reassign(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->deptTickets->findForDeptHead($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->deptTickets->reassign($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function close(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->deptTickets->findForDeptHead($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->deptTickets->close($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function presidentDecision(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->presidentTickets->findForPresident($reference);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found or outside presidential review scope (High/Critical only).'], 404);
        }

        $ticket = $this->presidentTickets->recordDecision($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function assignPersonnel(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->deptTickets->findForDeptHead($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->deptTickets->assignPersonnel($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function recordDocuments(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->deptTickets->findForDeptHead($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->deptTickets->recordDocuments($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function addComment(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->threadComments->findAccessible($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->threadComments->add($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function reopen(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->officerTickets->findForOfficer($reference);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket = $this->officerTickets->reopen($ticket, $user, $request->all());

        return response()->json(['ticket' => $ticket->toExpressArray()]);
    }

    public function accomplishment(string $reference): JsonResponse
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $acc = null;
        if ($ticket->accomplishment_external_id) {
            $acc = Accomplishment::query()
                ->where('external_id', $ticket->accomplishment_external_id)
                ->first();
        }
        $acc ??= Accomplishment::query()->where('ticket_ref', $reference)->first();

        if (! $acc) {
            return response()->json(['message' => 'Accomplishment not found.'], 404);
        }

        return response()->json(['accomplishment' => $acc->toExpressArray()]);
    }
}
