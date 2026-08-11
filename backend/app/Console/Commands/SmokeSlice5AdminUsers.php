<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminUserService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 15: smoke System Administrator users Blade + Postgres list.
 */
class SmokeSlice5AdminUsers extends Command
{
    protected $signature = 'rms:smoke-slice5-admin-users';

    protected $description = 'Smoke Laravel admin user management Blade page';

    public function handle(AdminUserService $users): int
    {
        $username = 'smoke_users_'.bin2hex(random_bytes(3));
        $password = 'SmokeUsers1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Admin Users',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'employee_id' => 'EMP-SMOKE',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $this->info("created {$username}");

        Auth::login($user);
        $payload = $users->list(null, null, null, null, null);
        $found = collect($payload['users'])->contains(fn ($u) => ($u['username'] ?? '') === $username);
        if (! $found) {
            Auth::logout();
            $user->delete();
            $this->error('user service missing created admin');

            return self::FAILURE;
        }

        $html = view('admin.users', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'users',
            'title' => 'User Management',
            'users' => $payload['users'],
            'departments' => $payload['departments'],
            'roles' => $payload['roles'],
            'filters' => $payload['filters'],
            'editUser' => null,
            'showForm' => false,
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'User Management') || ! str_contains($html, $username)) {
            Auth::logout();
            $user->delete();
            $this->error('admin users Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('admin users Blade OK');

        Auth::logout();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
