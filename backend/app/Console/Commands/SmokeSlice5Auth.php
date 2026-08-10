<?php

namespace App\Console\Commands;

use App\Http\Controllers\AuthController;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Phase 5 slice 2: smoke credential verify used by Express USE_LARAVEL_AUTH bridge.
 */
class SmokeSlice5Auth extends Command
{
    protected $signature = 'rms:smoke-slice5-auth';

    protected $description = 'Smoke Laravel /v1/auth/verify (Express cookie session still owns browser login)';

    public function handle(AuthController $auth): int
    {
        $username = 'smoke_auth_'.bin2hex(random_bytes(3));
        $password = 'SmokeAuth1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Auth',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);
        $this->info("created temp user {$username}");

        $ok = $auth->verify(Request::create('/v1/auth/verify', 'POST', [
            'username' => $username,
            'password' => $password,
        ]));
        if ($ok->getStatusCode() !== 200 || ($ok->getData(true)['user']['username'] ?? null) !== $username) {
            $user->delete();
            $this->error('verify failed for good credentials');

            return self::FAILURE;
        }
        $this->info('verify OK for good credentials');

        try {
            $auth->verify(Request::create('/v1/auth/verify', 'POST', [
                'username' => $username,
                'password' => 'wrong-password',
            ]));
            $user->delete();
            $this->error('verify should reject bad password');

            return self::FAILURE;
        } catch (ValidationException) {
            $this->info('verify rejects bad password');
        }

        $user->active = false;
        $user->status = 'inactive';
        $user->save();
        try {
            $auth->verify(Request::create('/v1/auth/verify', 'POST', [
                'username' => $username,
                'password' => $password,
            ]));
            $user->delete();
            $this->error('verify should reject inactive account');

            return self::FAILURE;
        } catch (ValidationException) {
            $this->info('verify rejects inactive account');
        }

        $user->delete();
        $this->info('cleaned up temp user');
        $this->line('Express owns cookie session. USE_LARAVEL_AUTH defaults ON (Phase 5 slice 3).');
        $this->line('Opt out: USE_LARAVEL_AUTH=false or docker/compose.soak.yml.');

        return self::SUCCESS;
    }
}
