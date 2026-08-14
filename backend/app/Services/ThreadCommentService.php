<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 slice 7 + Phase 8 slice 5: ticket thread comments, edits, and reactions (Postgres).
 * Notifications remain Express-owned.
 */
class ThreadCommentService
{
    /** @var list<string> */
    private const REACTION_OPTIONS = ['👍', '❤️', '🎉', '👀'];

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

        $executiveComments = is_array($ticket->executive_comments) ? $ticket->executive_comments : [];
        if (in_array($user->role, [Roles::EXECUTIVE, Roles::PRESIDENT], true)) {
            $already = false;
            foreach ($executiveComments as $existing) {
                if (($existing['id'] ?? null) === $record['id']) {
                    $already = true;
                    break;
                }
            }
            if (! $already) {
                $executiveComments[] = $record;
            }
        }

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
            'executive_comments' => $executiveComments,
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function edit(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $commentId = trim((string) ($input['commentId'] ?? ''));
        $text = trim((string) ($input['comment'] ?? $input['body'] ?? ''));
        if ($commentId === '') {
            throw ValidationException::withMessages([
                'commentId' => ['Comment not found.'],
            ]);
        }
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

        $comments = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];
        $idx = $this->indexOfComment($comments, $commentId);
        if ($idx === null) {
            throw ValidationException::withMessages([
                'commentId' => ['Comment not found.'],
            ]);
        }

        $comment = $comments[$idx];
        if (
            ($comment['authorUsername'] ?? null) !== $user->username
            || ($comment['kind'] ?? 'comment') !== 'comment'
        ) {
            throw ValidationException::withMessages([
                'commentId' => ['You can only edit your own comments.'],
            ]);
        }

        $now = now();
        $comments[$idx]['body'] = $text;
        $comments[$idx]['editedAt'] = $now->toIso8601String();

        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = [
            'id' => 'aud-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $now->toIso8601String(),
            'action' => 'Comment edited',
            'detail' => mb_strlen($text) > 120 ? mb_substr($text, 0, 120).'…' : $text,
            'actorUsername' => $user->username,
            'actorName' => $user->name ?: $user->username,
            'actorRole' => $user->role,
        ];

        $ticket->fill([
            'thread_comments' => $comments,
            'executive_comments' => $this->syncExecutiveComment($ticket, $comments[$idx]),
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    public function react(RiskTicket $ticket, User $user, array $input = []): RiskTicket
    {
        $commentId = trim((string) ($input['commentId'] ?? ''));
        $reaction = trim((string) ($input['reaction'] ?? ''));
        if ($commentId === '' || $reaction === '' || ! in_array($reaction, self::REACTION_OPTIONS, true)) {
            throw ValidationException::withMessages([
                'reaction' => ['Invalid reaction.'],
            ]);
        }

        $comments = is_array($ticket->thread_comments) ? $ticket->thread_comments : [];
        $idx = $this->indexOfComment($comments, $commentId);
        if ($idx === null) {
            throw ValidationException::withMessages([
                'commentId' => ['Comment not found.'],
            ]);
        }

        $reactions = is_array($comments[$idx]['reactions'] ?? null) ? $comments[$idx]['reactions'] : [];
        $users = array_values(array_filter(
            is_array($reactions[$reaction] ?? null) ? $reactions[$reaction] : [],
            fn ($name) => is_string($name) && $name !== '',
        ));
        $pos = array_search($user->username, $users, true);
        if ($pos === false) {
            $users[] = $user->username;
        } else {
            array_splice($users, (int) $pos, 1);
        }
        if ($users === []) {
            unset($reactions[$reaction]);
        } else {
            $reactions[$reaction] = $users;
        }
        $comments[$idx]['reactions'] = $reactions;

        $now = now();
        $ticket->fill([
            'thread_comments' => $comments,
            'executive_comments' => $this->syncExecutiveComment($ticket, $comments[$idx]),
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

    /**
     * @param  list<mixed>  $comments
     */
    private function indexOfComment(array $comments, string $commentId): ?int
    {
        foreach ($comments as $i => $comment) {
            if (is_array($comment) && ($comment['id'] ?? null) === $commentId) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $updated
     * @return list<array<string, mixed>>
     */
    private function syncExecutiveComment(RiskTicket $ticket, array $updated): array
    {
        $feed = is_array($ticket->executive_comments) ? $ticket->executive_comments : [];
        foreach ($feed as $i => $row) {
            if (is_array($row) && ($row['id'] ?? null) === ($updated['id'] ?? null)) {
                $feed[$i] = array_merge($row, $updated);

                return $feed;
            }
        }

        return $feed;
    }
}
