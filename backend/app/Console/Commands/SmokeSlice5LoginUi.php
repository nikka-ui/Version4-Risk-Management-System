<?php

namespace App\Console\Commands;

use App\Http\Controllers\LoginController;
use App\Models\User;
use App\Services\LoginBridgeService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Phase 5 slice 4: smoke Blade-login bridge code issue + exchange.
 */
class SmokeSlice5LoginUi extends Command
{
    protected $signature = 'rms:smoke-slice5-login-ui';

    protected $description = 'Smoke Laravel login bridge codes used by /laravel/login → Express /auth/bridge';

    public function handle(LoginBridgeService $bridge, LoginController $login): int
    {
        $username = 'smoke_ui_'.bin2hex(random_bytes(3));
        $password = 'SmokeUi1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Login UI',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);
        $this->info("created {$username}");

        $authed = $bridge->authenticate($username, $password);
        $code = $bridge->issueCode($authed);
        $this->info('issued bridge code');

        $response = $login->exchange(Request::create('/v1/auth/bridge-exchange', 'POST', [
            'code' => $code,
        ]));
        $data = $response->getData(true);
        if (($data['user']['username'] ?? null) !== $username) {
            $user->delete();
            $this->error('bridge exchange returned wrong user');

            return self::FAILURE;
        }
        $this->info('bridge exchange OK');

        try {
            $login->exchange(Request::create('/v1/auth/bridge-exchange', 'POST', [
                'code' => $code,
            ]));
            $user->delete();
            $this->error('bridge code should be one-time');

            return self::FAILURE;
        } catch (ValidationException) {
            $this->info('bridge code rejected on reuse');
        }

        $user->delete();
        $this->info('cleaned up');
        $this->line('Blade login UI: GET /laravel/login (flag USE_LARAVEL_LOGIN_UI). Express dashboards unchanged.');

        return self::SUCCESS;
    }
}
