<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 5: smoke Laravel web session + admin profile Blade view.
 */
class SmokeSlice5Profile extends Command
{
    protected $signature = 'rms:smoke-slice5-profile';

    protected $description = 'Smoke Laravel Auth::login + admin profile Blade page';

    public function handle(): int
    {
        $username = 'smoke_prof_'.bin2hex(random_bytes(3));
        $password = 'SmokeProf1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Profile Admin',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'can_manage_users' => true,
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);
        $this->info("created {$username}");

        Auth::login($user);
        if (! Auth::check() || Auth::user()->username !== $username) {
            $user->delete();
            $this->error('Auth::login failed');

            return self::FAILURE;
        }
        $this->info('Auth::login OK');

        $html = view('admin.profile', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'profile',
            'title' => 'Profile',
        ])->render();

        if (! str_contains($html, $username) || ! str_contains($html, 'Profile')) {
            Auth::logout();
            $user->delete();
            $this->error('profile Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('admin profile Blade OK');

        Auth::logout();
        $user->delete();
        $this->info('cleaned up');
        $this->line('Flag USE_LARAVEL_PROFILE_UI: Express /admin/profile → /laravel/admin/profile');

        return self::SUCCESS;
    }
}
