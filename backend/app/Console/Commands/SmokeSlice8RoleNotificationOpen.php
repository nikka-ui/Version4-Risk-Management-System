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
 * Phase 8 slice 8: smoke Department Head / RMO / Executive / President notification open GETs.
 */
class SmokeSlice8RoleNotificationOpen extends Command
{
    protected $signature = 'rms:smoke-slice8-role-notification-open';

    protected $description = 'Smoke Laravel notification open GETs for other console roles';

    public function handle(RoleNotificationController $controller): int
    {
        $roles = [
            [Roles::DEPT_HEAD, 'Information Technology', '/dept/notifications/open/', '/dept/tickets/RISK-SMOKE-OPEN'],
            [Roles::RM_OFFICER, null, '/officer/notifications/open/', '/officer/tickets/RISK-SMOKE-OPEN'],
            [Roles::EXECUTIVE, null, '/executive/notifications/open/', '/executive/tickets/RISK-SMOKE-OPEN'],
            [Roles::PRESIDENT, null, '/president/notifications/open/', '/president/tickets/RISK-SMOKE-OPEN'],
        ];

        foreach ($roles as [$role, $department, $prefix, $expect]) {
            $user = User::query()->create([
                'username' => 'smoke_nopen_'.bin2hex(random_bytes(2)),
                'name' => 'Smoke Open '.$role,
                'email' => 'smoke_nopen_'.bin2hex(random_bytes(2)).'@rms.local',
                'password' => 'SmokeOpen1!',
                'role' => $role,
                'role_label' => Roles::label($role),
                'department' => $department,
                'position' => Roles::label($role),
                'active' => true,
                'status' => 'active',
                'deleted' => false,
            ]);
            $id = 'notif-smoke-open-'.bin2hex(random_bytes(4));
            $note = Notification::query()->create([
                'id' => $id,
                'recipient_username' => $user->username,
                'type' => 'ping',
                'title' => 'Smoke open',
                'message' => 'Open me',
                'ticket_ref' => 'RISK-SMOKE-OPEN',
                'created_at' => now(),
            ]);

            Auth::login($user);
            try {
                $uri = $prefix.$id;
                $request = Request::create($uri, 'GET');
                $request->setUserResolver(fn () => Auth::user());
                $redirect = $controller->open($request, $id);
                $note->refresh();
                if ($note->read_at === null || ! str_contains($redirect->getTargetUrl(), $expect)) {
                    $this->error($role.' notification open failed');

                    return self::FAILURE;
                }
                $this->info($role.' notification open OK');
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
