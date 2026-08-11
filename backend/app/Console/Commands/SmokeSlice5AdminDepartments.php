<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use App\Services\AdminDepartmentService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 16: smoke System Administrator departments Blade + Postgres list.
 */
class SmokeSlice5AdminDepartments extends Command
{
    protected $signature = 'rms:smoke-slice5-admin-departments';

    protected $description = 'Smoke Laravel admin department management Blade page';

    public function handle(AdminDepartmentService $departments): int
    {
        $username = 'smoke_dept_'.bin2hex(random_bytes(3));
        $password = 'SmokeDept1!';
        $extId = 'dept-smoke-'.bin2hex(random_bytes(3));

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Admin Departments',
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

        $dept = Department::query()->create([
            'external_id' => $extId,
            'name' => 'Smoke Department '.$extId,
            'code' => 'SMK'.strtoupper(substr($extId, -4)),
            'description' => 'Smoke test department',
            'head' => null,
            'status' => 'active',
            'active' => true,
        ]);

        $this->info("created {$username} + {$extId}");

        Auth::login($user);
        $payload = $departments->list();
        $found = collect($payload['departments'])->contains(fn ($d) => ($d['id'] ?? '') === $extId);
        if (! $found) {
            Auth::logout();
            $dept->delete();
            $user->delete();
            $this->error('department service missing created department');

            return self::FAILURE;
        }

        $html = view('admin.departments', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'departments',
            'title' => 'Department Management',
            'departments' => $payload['departments'],
            'editDept' => null,
            'showForm' => false,
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'Department Management') || ! str_contains($html, $dept->name)) {
            Auth::logout();
            $dept->delete();
            $user->delete();
            $this->error('admin departments Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('admin departments Blade OK');

        Auth::logout();
        $dept->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
