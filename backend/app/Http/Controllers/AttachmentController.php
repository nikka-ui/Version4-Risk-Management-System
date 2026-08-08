<?php

namespace App\Http\Controllers;

use App\Models\RiskTicket;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 3 slice 8: attachment metadata APIs (shared risk_attachments table).
 * Phase 3 slice 10: file-byte upload/download over shared MinIO bucket.
 */
class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachments,
    ) {}

    public function index(string $reference): JsonResponse
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $items = collect($this->attachments->listForTicket($reference))
            ->map(fn ($a) => $a->toExpressArray())
            ->values();

        return response()->json([
            'attachments' => $items,
            'count' => $items->count(),
            'evidenceCount' => (int) $ticket->evidence_count,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $attachment = $this->attachments->findById($id);
        if (! $attachment) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return response()->json(['attachment' => $attachment->toExpressArray()]);
    }

    public function store(Request $request, string $reference): JsonResponse
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $payload = $request->all();
        if (isset($payload['attachments']) && is_array($payload['attachments'])) {
            $items = $this->attachments->registerMany($reference, $payload['attachments']);
            $ticket = $ticket->fresh();

            return response()->json([
                'attachments' => collect($items)->map(fn ($a) => $a->toExpressArray())->values(),
                'count' => count($items),
                'evidenceCount' => (int) $ticket->evidence_count,
            ], 201);
        }

        $input = $payload;
        if (empty($input['uploadedBy']) && $request->user()) {
            $input['uploadedBy'] = $request->user()->username;
        }

        $attachment = $this->attachments->register($reference, $input);
        $ticket = $ticket->fresh();

        return response()->json([
            'attachment' => $attachment->toExpressArray(),
            'evidenceCount' => (int) $ticket->evidence_count,
        ], 201);
    }

    public function sync(string $reference): JsonResponse
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $count = $this->attachments->syncEvidenceCount($reference);

        return response()->json([
            'reference' => $reference,
            'evidenceCount' => $count,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        if (! $this->attachments->deleteMetadata($id)) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return response()->json(['id' => $id]);
    }

    /**
     * Slice 10: store real file bytes to MinIO + register metadata.
     * Accepts multipart field `attachments[]` (or a single `file`).
     */
    public function upload(Request $request, string $reference): JsonResponse
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('deleted', false)
            ->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $files = $request->file('attachments');
        if ($files === null && $request->hasFile('file')) {
            $files = [$request->file('file')];
        }
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }
        if ($files === []) {
            return response()->json(['message' => 'No files provided.'], 422);
        }

        $uploadedBy = $request->user()?->username;
        $saved = $this->attachments->storeUploadedFiles($reference, $files, $uploadedBy);
        $ticket = $ticket->fresh();

        return response()->json([
            'attachments' => collect($saved)->map(fn ($a) => $a->toExpressArray())->values(),
            'count' => count($saved),
            'evidenceCount' => (int) $ticket->evidence_count,
        ], 201);
    }

    /** Slice 10: stream the stored file bytes back to the caller. */
    public function download(string $id): StreamedResponse|JsonResponse
    {
        $attachment = $this->attachments->findById($id);
        if (! $attachment) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $stream = $this->attachments->openReadStream($attachment);
        if ($stream === null) {
            return response()->json(['message' => 'File not found in object storage.'], 404);
        }

        $filename = $attachment->original_name ?: 'file';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.rawurlencode($filename).'"',
        ]);
    }
}
