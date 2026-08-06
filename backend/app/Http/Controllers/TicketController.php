<?php

namespace App\Http\Controllers;

use App\Models\Accomplishment;
use App\Models\RiskTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 3 slice 1: read-only ticket APIs.
 * Express still owns create/update/workflow and browser UI.
 */
class TicketController extends Controller
{
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
