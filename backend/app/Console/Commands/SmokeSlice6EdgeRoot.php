<?php

namespace App\Console\Commands;

use App\Http\Controllers\HomeController;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 6 slice 1–2: smoke edge `/` home redirect.
 */
class SmokeSlice6EdgeRoot extends Command
{
    protected $signature = 'rms:smoke-slice6-edge-root';

    protected $description = 'Smoke Laravel edge-root `/` redirects for Phase 6';

    public function handle(HomeController $home): int
    {
        config(['rms.edge_root' => true, 'rms.edge_ui' => true]);

        $guest = $home(Request::create('/', 'GET'));
        if ($guest->getTargetUrl() !== '/login' && ! (str_ends_with($guest->getTargetUrl(), '/login') && ! str_contains($guest->getTargetUrl(), '/laravel/'))) {
            $this->error('guest `/` should redirect to /login, got '.$guest->getTargetUrl());

            return self::FAILURE;
        }
        $this->info('guest `/` → /login OK');

        $username = 'smoke_p6_'.bin2hex(random_bytes(3));
        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Phase 6 Edge',
            'email' => "{$username}@rms.local",
            'password' => 'SmokeP6e1!',
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
            'department' => 'IT',
            'position' => 'President',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($user);
        $authedRequest = Request::create('/', 'GET');
        $authedRequest->setUserResolver(fn () => $user);
        $authed = $home($authedRequest);
        $target = $authed->getTargetUrl();
        if ($target !== '/president' && ! str_ends_with($target, '/president')) {
            Auth::logout();
            $user->delete();
            $this->error('president `/` should redirect to /president, got '.$target);

            return self::FAILURE;
        }
        $this->info('president `/` → /president OK');

        Auth::logout();
        config(['rms.edge_root' => false, 'rms.edge_ui' => false]);
        $optOut = $home(Request::create('/', 'GET'));
        $optTarget = $optOut->getTargetUrl();
        if ($optTarget !== '/login' && ! (str_ends_with($optTarget, '/login') && ! str_contains($optTarget, '/laravel/'))) {
            $user->delete();
            $this->error('edge-root off guest `/` should redirect to /login, got '.$optTarget);

            return self::FAILURE;
        }
        $this->info('soak guest `/` → /login OK');

        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
