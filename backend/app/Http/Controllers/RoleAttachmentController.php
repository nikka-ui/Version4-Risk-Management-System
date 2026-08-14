<?php

namespace App\Http\Controllers;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\DeptTicketDetailService;
use App\Services\OfficerTicketService;
use App\Services\PresidentTicketService;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 8 slice 9: role-console attachment downloads (same MinIO objects as Express).
 */
class RoleAttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly DeptTicketDetailService $deptTickets,
        private readonly OfficerTicketService $officerTickets,
        private readonly PresidentTicketService $presidentTickets,
    ) {}

    public function download(Request $request, string $id): StreamedResponse|Response
    {
        $attachment = $this->attachments->findById($id);
        if (! $attachment || ! $this->canAccess($request->user(), $attachment->ticket_ref)) {
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

    private function canAccess(User $user, string $reference): bool
    {
        return match ($user->role) {
            Roles::SUPERVISOR => RiskTicket::query()
                ->where('reference', $reference)
                ->where('submitted_by', $user->username)
                ->where('deleted', false)
                ->exists(),
            Roles::DEPT_HEAD => $this->deptTickets->forUser($user, $reference) !== null,
            Roles::RM_OFFICER => $this->officerTickets->findForOfficer($reference) !== null,
            Roles::EXECUTIVE => RiskTicket::query()
                ->where('reference', $reference)
                ->where('deleted', false)
                ->where('status', '!=', 'draft')
                ->exists(),
            Roles::PRESIDENT => $this->presidentTickets->findForPresident($reference) !== null,
            default => false,
        };
    }
}
