<?php

namespace App\Console\Commands;

use App\Http\Controllers\LoginController;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 9 slice 3: smoke GET/POST /logout on Laravel.
 */
class SmokeSlice9Logout extends Command
{
    protected $signature = 'rms:smoke-slice9-logout';

    protected $description = 'Smoke Laravel GET/POST /logout session clear';

    public function handle(LoginController $login): int
    {
        $user = User::query()->create([
            'username' => 'smoke_lo_'.bin2hex(random_bytes(3)),
            'name' => 'Smoke Logout',
            'email' => 'smoke_lo_'.bin2hex(random_bytes(3)).'@rms.local',
            'password' => 'SmokeLo1!',
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Information Technology',
            'position' => Roles::label(Roles::SUPERVISOR),
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($user);
        $request = $this->logoutRequest('/logout', 'POST');
        $redirect = $login->logout($request);
        if (! str_contains($redirect->getTargetUrl(), '/login') || Auth::check()) {
            Auth::logout();
            $user->delete();
            $this->error('logout did not clear session or redirect to login');

            return self::FAILURE;
        }
        $this->info('authenticated logout OK');

        $guest = $login->logout($this->logoutRequest('/logout', 'GET'));
        if (! str_contains($guest->getTargetUrl(), '/login')) {
            $user->delete();
            $this->error('guest logout should go to login');

            return self::FAILURE;
        }
        $this->info('guest logout OK');

        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    private function logoutRequest(string $uri, string $method): Request
    {
        $request = Request::create($uri, $method);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }
}
