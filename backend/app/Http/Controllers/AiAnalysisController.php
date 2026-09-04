<?php

namespace App\Http\Controllers;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\AiAnalysisService;
use App\Services\TicketAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Ticket-scoped AI history API and admin reclassify (gated confirm).
 */
class AiAnalysisController extends Controller
{
    public function __construct(
        private readonly AiAnalysisService $ai,
        private readonly TicketAccessService $ticketAccess,
    ) {}

    public function index(Request $request, string $reference): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->ticketAccess->findAccessible($reference, $user);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $runs = collect($this->ai->listForTicket($reference, 50))
            ->map(fn ($row) => $row->toListArray())
            ->values();

        return response()->json([
            'ticketReference' => $reference,
            'runs' => $runs,
            'count' => $runs->count(),
        ]);
    }

    public function reclassify(Request $request, string $reference): JsonResponse
    {
        $ticket = RiskTicket::query()->where('reference', $reference)->where('deleted', false)->first();
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $confirm = $request->boolean('confirm')
            || in_array($request->input('confirm'), [true, 1, '1', 'true', 'yes'], true);
        if (! $confirm) {
            throw ValidationException::withMessages([
                'confirm' => ['Reclassify requires confirm=true because it can overwrite assignment-critical AI fields.'],
            ]);
        }

        /** @var User $user */
        $user = $request->user();
        $applyAssignment = $request->boolean('applyAssignment');
        $ai = $this->ai->reclassifyTicket($ticket, $user, $applyAssignment);

        return response()->json([
            'ticketReference' => $reference,
            'ai' => $ai,
            'appliedAssignment' => $applyAssignment,
            'runCount' => count($this->ai->listForTicket($reference, 100)),
        ]);
    }
}
