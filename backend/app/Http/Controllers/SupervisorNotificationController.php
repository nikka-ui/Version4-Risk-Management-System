<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 10: Ticket Reporter notifications (Blade).
 */
class SupervisorNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $items = collect($this->notifications->listForUser($user, 50))
            ->map(fn ($n) => $n->toExpressArray())
            ->map(function (array $n) {
                $n['href'] = $this->resolveHref($n);

                return $n;
            })
            ->values()
            ->all();

        $unread = collect($items)->where('read', false)->count();

        return view('supervisor.notifications', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'notifications',
            'title' => 'Notifications',
            'notifications' => $items,
            'unread' => $unread,
            'flash' => $request->query('flash'),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return redirect()->away('/laravel/supervisor/notifications?flash=notifications_read');
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        try {
            $notification = $this->notifications->markRead($request->user(), $id);
        } catch (\Illuminate\Validation\ValidationException) {
            return redirect()->away('/laravel/supervisor/notifications?flash=not_found');
        }

        $href = $this->resolveHref($notification->toExpressArray());

        return redirect()->away($href ?: '/laravel/supervisor');
    }

    /**
     * @param  array<string, mixed>  $n
     */
    private function resolveHref(array $n): string
    {
        $ref = trim((string) ($n['ticketRef'] ?? ''));
        if ($ref !== '') {
            return '/laravel/supervisor/tickets/'.rawurlencode($ref);
        }

        $href = trim((string) ($n['href'] ?? ''));
        if ($href !== '' && str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return $href;
        }

        return '/laravel/supervisor/notifications';
    }
}
