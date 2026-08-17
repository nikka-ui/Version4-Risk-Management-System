<?php

namespace App\Http\Controllers;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\AiAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 11 slice 3 + 6: ticket-scoped AI history API and admin reclassify.
 */
class AiAnalysisController extends Controller
{
    public function __construct(
        private readonly AiAnalysisService $ai,
    ) {}

    public function index(string $reference): JsonResponse
    {
        $ticket = RiskTicket::query()->where('reference', $reference)->first();
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

        /** @var User $user */
        $user = $request->user();
        $ai = $this->ai->reclassifyTicket($ticket, $user);

        return response()->json([
            'ticketReference' => $reference,
            'ai' => $ai,
            'runCount' => count($this->ai->listForTicket($reference, 100)),
        ]);
    }
}
