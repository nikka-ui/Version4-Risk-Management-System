<?php

namespace App\Console\Commands;

use App\Models\Position;
use App\Models\User;
use App\Services\AdminPositionService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 17: smoke System Administrator positions Blade + Postgres list.
 */
class SmokeSlice5AdminPositions extends Command
{
    protected $signature = 'rms:smoke-slice5-admin-positions';

    protected $description = 'Smoke Laravel admin position management Blade page';

    public function handle(AdminPositionService $positions): int
    {
        $username = 'smoke_pos_'.bin2hex(random_bytes(3));
        $password = 'SmokePos1!';
        $extId = 'pos-smoke-'.bin2hex(random_bytes(3));

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Admin Positions',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $pos = Position::query()->create([
            'external_id' => $extId,
            'name' => 'Smoke Position '.$extId,
            'active' => true,
        ]);

        $this->info("created {$username} + {$extId}");

        Auth::login($user);
        $payload = $positions->list();
        $found = collect($payload['positions'])->contains(fn ($p) => ($p['id'] ?? '') === $extId);
        if (! $found) {
            Auth::logout();
            $pos->delete();
            $user->delete();
            $this->error('position service missing created position');

            return self::FAILURE;
        }

        $html = view('admin.positions', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'positions',
            'title' => 'Position Management',
            'positions' => $payload['positions'],
            'editPos' => null,
            'showForm' => false,
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'Position Management') || ! str_contains($html, $pos->name)) {
            Auth::logout();
            $pos->delete();
            $user->delete();
            $this->error('admin positions Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('admin positions Blade OK');

        Auth::logout();
        $pos->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
