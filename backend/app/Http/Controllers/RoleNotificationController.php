<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NotificationService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Phase 8 slice 6–8: mark-all-read POSTs and open GETs for Department Head, RMO, Executive, and President.
 */
class RoleNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->notifications->markAllRead($user);

        return redirect()->away($this->homeFor($user).'?flash=notifications_read');
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        $home = $this->homeFor($user);
        try {
            $notification = $this->notifications->markRead($user, $id);
        } catch (ValidationException) {
            return redirect()->away($home.'?flash=not_found');
        }

        $href = $this->resolveHref($user, $notification->toExpressArray());

        return redirect()->away($href !== '' ? $href : $home);
    }

    private function homeFor(User $user): string
    {
        return match ($user->role) {
            Roles::DEPT_HEAD => '/dept',
            Roles::RM_OFFICER => '/officer',
            Roles::EXECUTIVE => '/executive',
            Roles::PRESIDENT => '/president',
            default => '/',
        };
    }

    /**
     * @param  array<string, mixed>  $n
     */
    private function resolveHref(User $user, array $n): string
    {
        $ref = trim((string) ($n['ticketRef'] ?? ''));
        $base = match ($user->role) {
            Roles::DEPT_HEAD => '/dept/tickets',
            Roles::RM_OFFICER => '/officer/tickets',
            Roles::EXECUTIVE => '/executive/tickets',
            Roles::PRESIDENT => '/president/tickets',
            default => '',
        };
        if ($ref !== '' && $base !== '') {
            return $base.'/'.rawurlencode($ref);
        }

        $href = trim((string) ($n['href'] ?? ''));
        if ($href !== '' && str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return $href;
        }

        return $this->homeFor($user);
    }
}
