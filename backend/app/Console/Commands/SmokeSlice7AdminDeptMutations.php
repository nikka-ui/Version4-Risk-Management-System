<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdminDepartmentController;
use App\Models\Department;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 7 slice 1: smoke admin department Blade mutations.
 */
class SmokeSlice7AdminDeptMutations extends Command
{
    protected $signature = 'rms:smoke-slice7-admin-dept-mutations';

    protected $description = 'Smoke Laravel admin department create/edit/delete POSTs';

    public function handle(AdminDepartmentController $departments): int
    {
        $admin = User::query()->create([
            'username' => 'smoke_dmut_'.bin2hex(random_bytes(3)),
            'name' => 'Smoke Dept Mutations',
            'email' => 'smoke_dmut_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeDmut1!',
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
        $code = 'SM'.strtoupper(bin2hex(random_bytes(2)));
        $createRequest = Request::create('/admin/departments', 'POST', [
            'name' => 'Smoke Mutation Dept',
            'code' => $code,
            'description' => 'Phase 7 slice 1 smoke',
            'head' => '',
            'status' => 'active',
        ]);
        $createRequest->setUserResolver(fn () => Auth::user());
        $created = $departments->store($createRequest);
        $dept = Department::query()->where('code', $code)->where('active', true)->first();
        if (! $dept || ! str_contains($created->getTargetUrl(), 'flash=created')) {
            Auth::logout();
            $dept?->delete();
            $admin->delete();
            $this->error('department create did not persist');

            return self::FAILURE;
        }
        $this->info('department create OK');

        $updateRequest = Request::create('/admin/departments/'.$dept->external_id.'/edit', 'POST', [
            'name' => 'Smoke Mutation Dept Updated',
            'code' => $code,
            'description' => 'updated',
            'head' => 'Smoke Head',
            'status' => 'active',
        ]);
        $updateRequest->setUserResolver(fn () => Auth::user());
        $updated = $departments->update($updateRequest, $dept->external_id);
        $dept->refresh();
        if ($dept->name !== 'Smoke Mutation Dept Updated' || ! str_contains($updated->getTargetUrl(), 'flash=updated')) {
            Auth::logout();
            $dept->delete();
            $admin->delete();
            $this->error('department update did not persist');

            return self::FAILURE;
        }
        $this->info('department update OK');

        $deleteRequest = Request::create('/admin/departments/'.$dept->external_id.'/delete', 'POST');
        $deleteRequest->setUserResolver(fn () => Auth::user());
        $deleted = $departments->destroy($deleteRequest, $dept->external_id);
        $dept->refresh();
        if ($dept->active || ! str_contains($deleted->getTargetUrl(), 'flash=deleted')) {
            Auth::logout();
            $dept->delete();
            $admin->delete();
            $this->error('department delete did not soft-delete');

            return self::FAILURE;
        }
        $this->info('department delete OK');

        Auth::logout();
        $dept->delete();
        $admin->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
