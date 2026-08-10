<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 10: smoke Ticket Reporter notifications Blade page.
 */
class SmokeSlice5ReporterNotifications extends Command
{
    protected $signature = 'rms:smoke-slice5-reporter-notifications';

    protected $description = 'Smoke Laravel Ticket Reporter notifications Blade page';

    public function handle(NotificationService $notifications): int
    {
        $username = 'smoke_ntf_'.bin2hex(random_bytes(3));
        $password = 'SmokeNtf1!';
        $notifId = 'notif-smoke-'.bin2hex(random_bytes(4));
        $ticketRef = 'RISK-SMOKE-N-'.strtoupper(substr($username, -5));

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Notifications Reporter',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Operations',
            'position' => 'Risk Reporter',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $notifications->create([
            'id' => $notifId,
            'title' => 'Smoke ticket returned',
            'message' => 'Your smoke ticket needs revision.',
            'recipientUsername' => $username,
            'type' => 'ticket_returned',
            'ticketRef' => $ticketRef,
            'read' => false,
        ]);
        $this->info("created {$username} + notification {$notifId}");

        Auth::login($user);
        $items = collect($notifications->listForUser($user, 50))
            ->map(fn ($n) => $n->toExpressArray())
            ->all();

        if (! collect($items)->contains(fn ($n) => ($n['id'] ?? null) === $notifId)) {
            Auth::logout();
            Notification::query()->where('id', $notifId)->delete();
            $user->delete();
            $this->error('notification list missing smoke item');

            return self::FAILURE;
        }

        $html = view('supervisor.notifications', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'notifications',
            'title' => 'Notifications',
            'notifications' => collect($items)->map(function (array $n) use ($ticketRef) {
                $n['href'] = '/laravel/supervisor/tickets/'.rawurlencode((string) ($n['ticketRef'] ?? $ticketRef));

                return $n;
            })->all(),
            'unread' => 1,
            'flash' => null,
        ])->render();

        if (! str_contains($html, 'Smoke ticket returned') || ! str_contains($html, 'Mark all read')) {
            Auth::logout();
            Notification::query()->where('id', $notifId)->delete();
            $user->delete();
            $this->error('notifications Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('supervisor notifications Blade OK');

        $marked = $notifications->markAllRead($user);
        if ($marked < 1) {
            Auth::logout();
            Notification::query()->where('id', $notifId)->delete();
            $user->delete();
            $this->error('markAllRead did not update rows');

            return self::FAILURE;
        }
        $this->info('markAllRead OK');

        Auth::logout();
        Notification::query()->where('id', $notifId)->delete();
        $user->delete();
        $this->info('cleaned up');
        $this->line('Flag USE_LARAVEL_REPORTER_NOTIFICATIONS_UI: Express /supervisor/notifications → Blade');

        return self::SUCCESS;
    }
}
