<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttachmentService;
use App\Services\TicketAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Role-console attachment downloads (same MinIO objects as API).
 */
class RoleAttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly TicketAccessService $ticketAccess,
    ) {}

    public function download(Request $request, string $id): StreamedResponse|Response
    {
        $attachment = $this->attachments->findById($id);
        /** @var User|null $user */
        $user = $request->user();
        if (! $attachment || ! $user || ! $this->ticketAccess->canAccess($user, $attachment->ticket_ref)) {
            abort(404, 'Attachment not found.');
        }

        $ticket = \App\Models\RiskTicket::query()->where('reference', $attachment->ticket_ref)->first();
        if (! $ticket || $ticket->deleted) {
            abort(404, 'Attachment not found.');
        }

        $stream = $this->attachments->openReadStream($attachment);
        if ($stream === null) {
            abort(404, 'File not found in object storage.');
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
