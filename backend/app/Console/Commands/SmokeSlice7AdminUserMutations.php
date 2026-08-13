<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdminUserController;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 3: smoke admin user Blade mutations.
 */
class SmokeSlice7AdminUserMutations extends Command
{
    protected $signature = 'rms:smoke-slice7-admin-user-mutations';

    protected $description = 'Smoke Laravel admin user create/edit/status/reset/delete POSTs';

    public function handle(AdminUserController $users): int
    {
        $admin = User::query()->create([
            'username' => 'smoke_umut_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke User Mutations',
            'email' => 'smoke_umut_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeUmut1!',
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'IT',
            'position' => 'System Administrator',
            'can_manage_users' => true,
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($admin);
        $username = 'smkusr'.bin2hex(random_bytes(2));
        $createRequest = Request::create('/admin/users', 'POST', [
            'username' => $username,
            'displayName' => 'Smoke Analyst',
            'email' => $username.'@rms.local',
            'department' => 'Information Technology',
            'position' => 'Analyst',
            'role' => Roles::SUPERVISOR,
            'password' => 'SmokeUser1!',
            'confirmPassword' => 'SmokeUser1!',
        ]);
        $createRequest->setUserResolver(fn () => Auth::user());
        $created = $users->store($createRequest);
        $target = User::query()->where('username', $username)->where('deleted', false)->first();
        if (! $target || ! str_contains($created->getTargetUrl(), 'flash=created')) {
            Auth::logout();
            $target?->delete();
            $admin->delete();
            $this->error('user create did not persist');

            return self::FAILURE;
        }
        $this->info('user create OK');

        $updateRequest = Request::create('/admin/users/'.$username.'/edit', 'POST', [
            'displayName' => 'Smoke Analyst Updated',
            'email' => $username.'@rms.local',
            'employeeId' => $target->employee_id,
            'department' => 'Information Technology',
            'position' => 'Senior Analyst',
            'role' => Roles::SUPERVISOR,
            'status' => 'active',
        ]);
        $updateRequest->setUserResolver(fn () => Auth::user());
        $updated = $users->update($updateRequest, $username);
        $target->refresh();
        if ($target->name !== 'Smoke Analyst Updated' || ! str_contains($updated->getTargetUrl(), 'flash=updated')) {
            $this->cleanup($admin, $target);
            $this->error('user update did not persist');

            return self::FAILURE;
        }
        $this->info('user update OK');

        $offRequest = Request::create('/admin/users/'.$username.'/deactivate', 'POST');
        $offRequest->setUserResolver(fn () => Auth::user());
        $off = $users->deactivate($offRequest, $username);
        $target->refresh();
        if ($target->active || ! str_contains($off->getTargetUrl(), 'flash=deactivated')) {
            $this->cleanup($admin, $target);
            $this->error('user deactivate did not persist');

            return self::FAILURE;
        }
        $this->info('user deactivate OK');

        $onRequest = Request::create('/admin/users/'.$username.'/activate', 'POST');
        $onRequest->setUserResolver(fn () => Auth::user());
        $on = $users->activate($onRequest, $username);
        $target->refresh();
        if (! $target->active || ! str_contains($on->getTargetUrl(), 'flash=activated')) {
            $this->cleanup($admin, $target);
            $this->error('user activate did not persist');

            return self::FAILURE;
        }
        $this->info('user activate OK');

        $resetRequest = Request::create('/admin/users/'.$username.'/reset-password', 'POST', [
            'password' => 'SmokeUser2!',
            'confirmPassword' => 'SmokeUser2!',
        ]);
        $resetRequest->setUserResolver(fn () => Auth::user());
        $reset = $users->resetPassword($resetRequest, $username);
        if (! str_contains($reset->getTargetUrl(), 'flash=password_reset')) {
            $this->cleanup($admin, $target);
            $this->error('user password reset did not redirect');

            return self::FAILURE;
        }
        $this->info('user password reset OK');

        $deleteRequest = Request::create('/admin/users/'.$username.'/delete', 'POST');
        $deleteRequest->setUserResolver(fn () => Auth::user());
        $deleted = $users->destroy($deleteRequest, $username);
        $target->refresh();
        if (! $target->deleted || ! str_contains($deleted->getTargetUrl(), 'flash=deleted')) {
            $this->cleanup($admin, $target);
            $this->error('user delete did not persist');

            return self::FAILURE;
        }
        $this->info('user delete OK');

        $this->cleanup($admin, $target);
        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    private function cleanup(User $admin, ?User $target): void
    {
        Auth::logout();
        $target?->delete();
        $admin->delete();
    }
}
