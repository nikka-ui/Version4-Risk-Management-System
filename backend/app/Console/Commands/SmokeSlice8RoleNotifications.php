<?php

namespace App\Console\Commands;

use App\Http\Controllers\RoleNotificationController;
use App\Models\Notification;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 8 slice 6: smoke Department Head / RMO / Executive / President mark-all-read POSTs.
 */
class SmokeSlice8RoleNotifications extends Command
{
    protected $signature = 'rms:smoke-slice8-role-notifications';

    protected $description = 'Smoke Laravel mark-all-read POSTs for other console roles';

    public function handle(RoleNotificationController $controller): int
    {
        $roles = [
            [Roles::DEPT_HEAD, 'Information Technology', '/dept/notifications/read-all', '/dept?flash=notifications_read'],
            [Roles::RM_OFFICER, null, '/officer/notifications/read-all', '/officer?flash=notifications_read'],
            [Roles::EXECUTIVE, null, '/executive/notifications/read-all', '/executive?flash=notifications_read'],
            [Roles::PRESIDENT, null, '/president/notifications/read-all', '/president?flash=notifications_read'],
        ];

        foreach ($roles as [$role, $department, $uri, $expect]) {
            $user = User::query()->create([
                'username' => 'smoke_nra_'.bin2hex(random_bytes(2)),
                'name' => 'Smoke Read All '.$role,
                'email' => 'smoke_nra_'.bin2hex(random_bytes(2)).'@rms.local',
                'password' => 'SmokeNra1!',
                'role' => $role,
                'role_label' => Roles::label($role),
                'department' => $department,
                'position' => Roles::label($role),
                'active' => true,
                'status' => 'active',
                'deleted' => false,
            ]);
            $note = Notification::query()->create([
                'id' => 'notif-smoke-'.bin2hex(random_bytes(4)),
                'recipient_username' => $user->username,
                'type' => 'ping',
                'title' => 'Smoke unread',
                'message' => 'Mark me',
                'created_at' => now(),
            ]);

            Auth::login($user);
            try {
                $request = Request::create($uri, 'POST');
                $request->setUserResolver(fn () => Auth::user());
                $redirect = $controller->markAllRead($request);
                $note->refresh();
                if ($note->read_at === null || ! str_contains($redirect->getTargetUrl(), $expect)) {
                    $this->error($role.' mark-all-read failed');

                    return self::FAILURE;
                }
                $this->info($role.' mark-all-read OK');
            } finally {
                Auth::logout();
                $note->delete();
                $user->delete();
            }
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
