<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 3 slice 9: notification mirror APIs (Laravel-owned notifications table).
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $limit = (int) $request->query('limit', 50);

        $items = collect($this->notifications->listForUser($user, $limit))
            ->map(fn ($n) => $n->toExpressArray())
            ->values();

        return response()->json([
            'notifications' => $items,
            'count' => $items->count(),
            'unread' => $items->where('read', false)->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['unread' => $this->notifications->unreadCount($user)]);
    }

    public function store(Request $request): JsonResponse
    {
        $notification = $this->notifications->create($request->all());

        return response()->json(['notification' => $notification->toExpressArray()], 201);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticketRef = trim((string) $request->input('ticketRef', '')) ?: null;

        $count = $this->notifications->markAllRead($user, $ticketRef);

        return response()->json(['updated' => $count]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = $this->notifications->markRead($user, $id);

        return response()->json([
            'notification' => $notification->toExpressArray(),
            'href' => $notification->href,
        ]);
    }
}
