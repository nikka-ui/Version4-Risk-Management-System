<?php

namespace App\Http\Controllers;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\TicketAccessService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attachment APIs with ticket visibility checks (deny by default).
 */
class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly TicketAccessService $ticketAccess,
    ) {}

    public function index(Request $request, string $reference): JsonResponse
    {
        $ticket = $this->accessibleTicket($request, $reference);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $items = collect($this->attachments->listForTicket($reference))
            ->map(fn ($a) => $a->toPublicArray())
            ->values();

        return response()->json([
            'attachments' => $items,
            'count' => $items->count(),
            'evidenceCount' => (int) $ticket->evidence_count,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $attachment = $this->attachments->findById($id);
        if (! $attachment || ! $this->ticketAccess->canAccess($request->user(), $attachment->ticket_ref)) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $ticket = RiskTicket::query()->where('reference', $attachment->ticket_ref)->first();
        if ($ticket && $ticket->deleted) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return response()->json(['attachment' => $attachment->toPublicArray()]);
    }

    public function store(Request $request, string $reference): JsonResponse
    {
        // Metadata-only register is restricted to admin / internal service callers.
        /** @var User $user */
        $user = $request->user();
        if ($user->role !== Roles::ADMIN) {
            return response()->json([
                'message' => 'Metadata-only attachment registration is restricted. Use the upload endpoint with file bytes.',
            ], 403);
        }

        $ticket = $this->accessibleTicket($request, $reference);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $payload = $request->all();
        if (isset($payload['attachments']) && is_array($payload['attachments'])) {
            $items = $this->attachments->registerMany($reference, $payload['attachments']);
            $ticket = $ticket->fresh();

            return response()->json([
                'attachments' => collect($items)->map(fn ($a) => $a->toPublicArray())->values(),
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
            'attachment' => $attachment->toPublicArray(),
            'evidenceCount' => (int) $ticket->evidence_count,
        ], 201);
    }

    public function sync(Request $request, string $reference): JsonResponse
    {
        $ticket = $this->accessibleTicket($request, $reference);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $count = $this->attachments->syncEvidenceCount($reference);

        return response()->json([
            'reference' => $reference,
            'evidenceCount' => $count,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $attachment = $this->attachments->findById($id);
        if (! $attachment || ! $this->ticketAccess->canAccess($request->user(), $attachment->ticket_ref)) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $ticket = RiskTicket::query()->where('reference', $attachment->ticket_ref)->first();
        if ($ticket && $ticket->deleted) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        if (! $this->attachments->deleteWithStorage($id)) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return response()->json(['id' => $id]);
    }

    public function upload(Request $request, string $reference): JsonResponse
    {
        $ticket = $this->accessibleTicket($request, $reference);
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
            'attachments' => collect($saved)->map(fn ($a) => $a->toPublicArray())->values(),
            'count' => count($saved),
            'evidenceCount' => (int) $ticket->evidence_count,
        ], 201);
    }

    public function download(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $attachment = $this->attachments->findById($id);
        if (! $attachment || ! $this->ticketAccess->canAccess($request->user(), $attachment->ticket_ref)) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $ticket = RiskTicket::query()->where('reference', $attachment->ticket_ref)->first();
        if (! $ticket || $ticket->deleted) {
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

    private function accessibleTicket(Request $request, string $reference): ?RiskTicket
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ticketAccess->findAccessible($reference, $user);
    }
}
