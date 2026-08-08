<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 slice 7: ticket thread comments (Postgres only).
 * Notifications remain Express-owned.
 */
class ThreadCommentService
{
    public function add(RiskTicket $ticket, User $user, array $input = [], ?string $kind = null): RiskTicket
    {
        $text = trim((string) ($input['comment'] ?? $input['body'] ?? ''));
        if ($text === '') {
            throw ValidationException::withMessages([
                'comment' => ['Comment cannot be empty.'],
            ]);
        }
        if (mb_strlen($text) > 2000) {
            throw ValidationException::withMessages([
                'comment' => ['Comment is too long (max 2000 characters).'],
            ]);
        }

        $parentId = trim((string) ($input['parentId'] ?? ''));
        $parentId = $parentId !== '' ? $parentId : null;
        $comments = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];

        if ($parentId !== null) {
            $parentExists = false;
            foreach ($comments as $comment) {
                if (($comment['id'] ?? null) === $parentId) {
                    $parentExists = true;
                    break;
                }
            }
            if (! $parentExists) {
                throw ValidationException::withMessages([
                    'parentId' => ['Parent comment not found.'],
                ]);
            }
        }

        $now = now();
        $resolvedKind = $kind ?? ($user->role === Roles::RM_OFFICER ? 'governance' : 'comment');

        $record = [
            'id' => 'thr-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'authorUsername' => $user->username,
            'authorName' => $user->name ?: $user->username,
            'authorRole' => $user->role,
            'roleLabel' => $user->position ?: ($user->role_label ?: Roles::label($user->role)),
            'authorPosition' => $user->position,
            'body' => $text,
            'at' => $now->toIso8601String(),
            'editedAt' => null,
            'parentId' => $parentId,
            'kind' => $resolvedKind,
            'mentions' => [],
            'reactions' => [],
            'attachments' => [],
        ];
        $comments[] = $record;

        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $action = $user->role === Roles::RM_OFFICER
            ? ($parentId ? 'RMO thread reply' : 'RMO governance comment')
            : 'Comment added';
        $audit[] = [
            'id' => 'aud-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $now->toIso8601String(),
            'action' => $action,
            'detail' => mb_strlen($text) > 120 ? mb_substr($text, 0, 120).'…' : $text,
            'actorUsername' => $user->username,
            'actorName' => $user->name ?: $user->username,
            'actorRole' => $user->role,
        ];

        $ticket->fill([
            'thread_comments' => $comments,
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function findAccessible(string $reference, User $user): ?RiskTicket
    {
        $ticket = RiskTicket::query()
            ->where('reference', $reference)
            ->where('deleted', false)
            ->where('status', '!=', 'draft')
            ->first();

        if (! $ticket) {
            return null;
        }

        return match ($user->role) {
            Roles::SUPERVISOR => $ticket->submitted_by === $user->username ? $ticket : null,
            Roles::DEPT_HEAD => app(DeptTicketService::class)->findForDeptHead($reference, $user),
            Roles::RM_OFFICER => $ticket,
            Roles::PRESIDENT => app(PresidentTicketService::class)->findForPresident($reference),
            Roles::EXECUTIVE, Roles::ADMIN => $ticket,
            default => null,
        };
    }
}
