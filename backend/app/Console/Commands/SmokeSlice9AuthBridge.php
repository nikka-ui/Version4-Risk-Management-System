<?php

namespace App\Console\Commands;

use App\Http\Controllers\LoginController;
use App\Models\User;
use App\Services\LoginBridgeService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 9 slice 2: smoke GET /auth/bridge on Laravel.
 */
class SmokeSlice9AuthBridge extends Command
{
    protected $signature = 'rms:smoke-slice9-auth-bridge';

    protected $description = 'Smoke Laravel GET /auth/bridge login handoff';

    public function handle(LoginBridgeService $bridge, LoginController $login): int
    {
        $user = User::query()->create([
            'username' => 'smoke_br_'.bin2hex(random_bytes(3)),
            'name' => 'Smoke Auth Bridge',
            'email' => 'smoke_br_'.bin2hex(random_bytes(3)).'@rms.local',
            'password' => 'SmokeBr1!',
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Information Technology',
            'position' => Roles::label(Roles::SUPERVISOR),
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $code = $bridge->issueCode($user);
        $request = $this->bridgeRequest('/auth/bridge?code='.urlencode($code).'&next=/supervisor');
        $redirect = $login->bridge($request);
        if (! str_contains($redirect->getTargetUrl(), '/supervisor') || Auth::id() !== $user->id) {
            Auth::logout();
            $user->delete();
            $this->error('auth bridge did not log in or redirect');

            return self::FAILURE;
        }
        $this->info('auth bridge OK');

        $reuse = $login->bridge($this->bridgeRequest('/auth/bridge?code='.urlencode($code)));
        if (! str_contains($reuse->getTargetUrl(), '/supervisor')) {
            Auth::logout();
            $user->delete();
            $this->error('logged-in reuse of spent code should still use session');

            return self::FAILURE;
        }
        $this->info('spent code with session OK');

        Auth::logout();
        $guest = $login->bridge($this->bridgeRequest('/auth/bridge'));
        if (! str_contains($guest->getTargetUrl(), '/login')) {
            $user->delete();
            $this->error('guest bridge without code should go to login');

            return self::FAILURE;
        }
        $this->info('guest without code OK');

        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    private function bridgeRequest(string $uri): Request
    {
        $request = Request::create($uri, 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }
}
