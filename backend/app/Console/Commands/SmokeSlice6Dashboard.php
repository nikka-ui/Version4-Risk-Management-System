<?php

namespace App\Console\Commands;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Phase 6 slice 6: smoke employee /dashboard Blade + role-console redirect.
 */
class SmokeSlice6Dashboard extends Command
{
    protected $signature = 'rms:smoke-slice6-dashboard';

    protected $description = 'Smoke Laravel /dashboard employee stub and console redirects';

    public function handle(DashboardController $dashboard): int
    {
        $employee = User::query()->create([
            'username' => 'smoke_emp_'.bin2hex(random_bytes(3)),
            'name' => 'Smoke Employee',
            'email' => 'smoke_emp_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeEmp1!',
            'role' => Roles::EMPLOYEE,
            'role_label' => Roles::label(Roles::EMPLOYEE),
            'department' => 'Administration',
            'position' => 'Employee',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($employee);
        $empRequest = Request::create('/dashboard', 'GET');
        $empRequest->setUserResolver(fn () => Auth::user());
        $empResponse = $dashboard->index($empRequest);
        if (! $empResponse instanceof View) {
            Auth::logout();
            $employee->delete();
            $this->error('employee /dashboard did not render a view');

            return self::FAILURE;
        }

        $html = $empResponse->render();
        if (
            ! str_contains($html, 'Welcome, Smoke Employee')
            || ! str_contains($html, 'Access assigned risk workflows')
            || ! str_contains($html, 'upcoming releases')
            || ! str_contains($html, 'href="/dashboard"')
            || str_contains($html, '/laravel/dashboard')
        ) {
            Auth::logout();
            $employee->delete();
            $this->error('employee dashboard Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('employee dashboard Blade OK');
        Auth::logout();

        $admin = User::query()->create([
            'username' => 'smoke_dba_'.bin2hex(random_bytes(3)),
            'name' => 'Smoke Dashboard Admin',
            'email' => 'smoke_dba_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeDba1!',
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
        $adminRequest = Request::create('/dashboard', 'GET');
        $adminRequest->setUserResolver(fn () => Auth::user());
        $adminResponse = $dashboard->index($adminRequest);
        $target = method_exists($adminResponse, 'getTargetUrl') ? $adminResponse->getTargetUrl() : '';
        if (! str_contains($target, '/admin') || str_contains($target, '/laravel/')) {
            Auth::logout();
            $admin->delete();
            $employee->delete();
            $this->error('admin /dashboard did not redirect to /admin');

            return self::FAILURE;
        }
        $this->info('admin /dashboard redirect OK');

        Auth::logout();
        $admin->delete();
        $employee->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
