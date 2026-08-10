<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Departments;
use App\Support\Roles;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 slice 9: notifications mirror of Express store.notifications.
 * Phase 5 slice 10: Ticket Reporter Blade UI reads/writes via this service.
 */
class NotificationService
{
    /**
     * @return list<Notification>
     */
    public function listForUser(User $user, int $limit = 50): array
    {
        $limit = max(1, min($limit, 300));

        $matches = Notification::query()
            ->where(function ($q) use ($user) {
                $q->where('recipient_username', $user->username)
                    ->orWhere('recipient_role', $user->role);
            })
            ->orderByDesc('created_at')
            ->limit($limit * 2)
            ->get();

        $oversightOnly = in_array($user->role, [Roles::PRESIDENT, Roles::EXECUTIVE], true);
        $refs = $matches->pluck('ticket_ref')->filter()->unique()->values()->all();
        $tickets = RiskTicket::query()->whereIn('reference', $refs)->get()->keyBy('reference');

        $visible = $matches->filter(function (Notification $n) use ($user, $oversightOnly, $tickets) {
            if ($n->recipient_role === $user->role && $user->role === Roles::DEPT_HEAD && $n->ticket_ref) {
                $ticket = $tickets->get($n->ticket_ref);
                if ($ticket) {
                    $ownership = is_array($ticket->ownership) ? $ticket->ownership : [];
                    if (($ownership['ownerUsername'] ?? null) !== $user->username
                        && ! Departments::match($user->department, $ticket->department)) {
                        return false;
                    }
                }
            }

            if (! $n->ticket_ref) {
                return true;
            }
            $ticket = $tickets->get($n->ticket_ref);
            if (! $ticket) {
                return true;
            }
            if ($ticket->deleted) {
                return false;
            }
            if ($oversightOnly) {
                $level = Departments::riskLevelId(
                    is_array($ticket->ai) ? $ticket->ai : null,
                    $ticket->likelihood,
                    $ticket->impact,
                );
                if (! in_array($level, ['high', 'critical'], true)) {
                    return false;
                }
            }

            return true;
        });

        return $visible->take($limit)->values()->all();
    }

    public function unreadCount(User $user): int
    {
        return collect($this->listForUser($user, 100))
            ->filter(fn (Notification $n) => $n->read_at === null)
            ->count();
    }

    public function create(array $input): Notification
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => ['A notification title is required.'],
            ]);
        }

        $recipientUsername = trim((string) ($input['recipientUsername'] ?? '')) ?: null;
        $recipientRole = trim((string) ($input['recipientRole'] ?? '')) ?: null;
        if ($recipientUsername === null && $recipientRole === null) {
            throw ValidationException::withMessages([
                'recipient' => ['A recipientUsername or recipientRole is required.'],
            ]);
        }

        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') {
            $id = 'notif-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3));
        }

        $createdAt = now();
        if (! empty($input['at'])) {
            try {
                $createdAt = Carbon::parse((string) $input['at']);
            } catch (\Throwable) {
                $createdAt = now();
            }
        }

        $readAt = null;
        if (($input['read'] ?? false) === true || ($input['read'] ?? null) === 'true') {
            $readAt = $createdAt;
        }

        return Notification::query()->updateOrCreate(
            ['id' => $id],
            [
                'recipient_username' => $recipientUsername,
                'recipient_role' => $recipientRole,
                'type' => trim((string) ($input['type'] ?? 'notification')) ?: 'notification',
                'title' => $title,
                'message' => (string) ($input['message'] ?? ''),
                'ticket_ref' => trim((string) ($input['ticketRef'] ?? '')) ?: null,
                'href' => trim((string) ($input['href'] ?? '')) ?: null,
                'from_username' => trim((string) ($input['fromUsername'] ?? '')) ?: null,
                'from_name' => trim((string) ($input['fromName'] ?? '')) ?: null,
                'from_role' => trim((string) ($input['fromRole'] ?? '')) ?: null,
                'read_at' => $readAt,
                'created_at' => $createdAt,
            ],
        )->fresh();
    }

    public function markAllRead(User $user, ?string $ticketRef = null): int
    {
        $now = now();
        $ids = collect($this->listForUser($user, 300))
            ->filter(fn (Notification $n) => $n->read_at === null)
            ->when($ticketRef, fn ($c) => $c->filter(fn (Notification $n) => $n->ticket_ref === $ticketRef))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        return Notification::query()->whereIn('id', $ids)->update(['read_at' => $now]);
    }

    public function markRead(User $user, string $id): Notification
    {
        $notification = Notification::query()
            ->where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('recipient_username', $user->username)
                    ->orWhere('recipient_role', $user->role);
            })
            ->first();

        if (! $notification) {
            throw ValidationException::withMessages([
                'id' => ['Notification not found.'],
            ]);
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return $notification->fresh();
    }
}
