<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 6: smoke Ticket Reporter profile Blade view.
 */
class SmokeSlice5ReporterProfile extends Command
{
    protected $signature = 'rms:smoke-slice5-reporter-profile';

    protected $description = 'Smoke Laravel Auth + Ticket Reporter profile Blade page';

    public function handle(): int
    {
        $username = 'smoke_rep_'.bin2hex(random_bytes(3));
        $password = 'SmokeRep1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Reporter',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'employee_id' => 'EMP-SMOKE',
            'department' => 'Operations',
            'position' => 'Risk Reporter',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);
        $this->info("created {$username}");

        Auth::login($user);
        $html = view('supervisor.profile', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'profile',
            'title' => 'Profile',
        ])->render();

        if (! str_contains($html, $username) || ! str_contains($html, 'Ticket Reporter')) {
            Auth::logout();
            $user->delete();
            $this->error('reporter profile Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('supervisor profile Blade OK');

        Auth::logout();
        $user->delete();
        $this->info('cleaned up');
        $this->line('Flag USE_LARAVEL_REPORTER_PROFILE_UI: Express /supervisor/profile → /laravel/supervisor/profile');

        return self::SUCCESS;
    }
}
