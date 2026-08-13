<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdminPositionController;
use App\Models\Position;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 2: smoke admin position Blade mutations.
 */
class SmokeSlice7AdminPosMutations extends Command
{
    protected $signature = 'rms:smoke-slice7-admin-pos-mutations';

    protected $description = 'Smoke Laravel admin position create/edit/delete POSTs';

    public function handle(AdminPositionController $positions): int
    {
        $admin = User::query()->create([
            'username' => 'smoke_pmut_'.bin2hex(random_bytes(3)),
            'name' => 'Smoke Pos Mutations',
            'email' => 'smoke_pmut_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokePmut1!',
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
        $name = 'Smoke Position '.bin2hex(random_bytes(2));
        $createRequest = Request::create('/admin/positions', 'POST', ['name' => $name]);
        $createRequest->setUserResolver(fn () => Auth::user());
        $created = $positions->store($createRequest);
        $pos = Position::query()->where('name', $name)->where('active', true)->first();
        if (! $pos || ! str_contains($created->getTargetUrl(), 'flash=created')) {
            Auth::logout();
            $pos?->delete();
            $admin->delete();
            $this->error('position create did not persist');

            return self::FAILURE;
        }
        $this->info('position create OK');

        $updatedName = $name.' Updated';
        $updateRequest = Request::create('/admin/positions/'.$pos->external_id.'/edit', 'POST', [
            'name' => $updatedName,
        ]);
        $updateRequest->setUserResolver(fn () => Auth::user());
        $updated = $positions->update($updateRequest, $pos->external_id);
        $pos->refresh();
        if ($pos->name !== $updatedName || ! str_contains($updated->getTargetUrl(), 'flash=updated')) {
            Auth::logout();
            $pos->delete();
            $admin->delete();
            $this->error('position update did not persist');

            return self::FAILURE;
        }
        $this->info('position update OK');

        $deleteRequest = Request::create('/admin/positions/'.$pos->external_id.'/delete', 'POST');
        $deleteRequest->setUserResolver(fn () => Auth::user());
        $deleted = $positions->destroy($deleteRequest, $pos->external_id);
        $pos->refresh();
        if ($pos->active || ! str_contains($deleted->getTargetUrl(), 'flash=deleted')) {
            Auth::logout();
            $pos->delete();
            $admin->delete();
            $this->error('position delete did not soft-delete');

            return self::FAILURE;
        }
        $this->info('position delete OK');

        Auth::logout();
        $pos->delete();
        $admin->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
