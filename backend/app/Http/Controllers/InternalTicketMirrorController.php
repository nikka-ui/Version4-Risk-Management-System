<?php

namespace App\Http\Controllers;

use App\Services\StoreJsonTicketMirror;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 9 slice 5: Laravel /internal/tickets/* dual-write onto store.json.
 */
class InternalTicketMirrorController extends Controller
{
    public function __construct(private readonly StoreJsonTicketMirror $mirror) {}

    public function upsert(Request $request): JsonResponse
    {
        $ticket = $request->input('ticket', []);
        $result = $this->mirror->upsert(is_array($ticket) ? $ticket : []);
        if (! empty($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['status' => 'ok']);
    }

    public function softDelete(Request $request): JsonResponse
    {
        $ticket = $request->input('ticket', []);
        $audit = $request->input('audit');
        $notification = $request->input('notification');
        $result = $this->mirror->softDelete(
            is_array($ticket) ? $ticket : [],
            is_array($audit) ? $audit : null,
            is_array($notification) ? $notification : null,
        );
        if (! empty($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['status' => 'ok']);
    }

    public function deleteDraft(Request $request): JsonResponse
    {
        $ticket = $request->input('ticket', []);
        $reference = is_array($ticket) ? (string) ($ticket['reference'] ?? '') : '';
        $result = $this->mirror->deleteDraft($reference);
        if (! empty($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['status' => 'ok']);
    }
}
