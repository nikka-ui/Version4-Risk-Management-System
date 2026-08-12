<?php

use App\Http\Controllers\AdminAuditLogsController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminDepartmentController;
use App\Http\Controllers\AdminPositionController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminTicketController;
use App\Http\Controllers\AdminTicketDetailController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\DeptDashboardController;
use App\Http\Controllers\DeptQueueController;
use App\Http\Controllers\DeptTicketDetailController;
use App\Http\Controllers\ExecutiveDashboardController;
use App\Http\Controllers\OfficerDashboardController;
use App\Http\Controllers\OfficerQueueController;
use App\Http\Controllers\OfficerTicketDetailController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PresidentDashboardController;
use App\Http\Controllers\PresidentQueueController;
use App\Http\Controllers\PresidentTicketDetailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupervisorAccomplishmentController;
use App\Http\Controllers\SupervisorActionController;
use App\Http\Controllers\SupervisorDashboardController;
use App\Http\Controllers\SupervisorNotificationController;
use App\Http\Controllers\SupervisorTicketController;
use App\Http\Controllers\SupervisorTicketFormController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'rms-api',
        'status' => 'ok',
        'framework' => 'laravel',
        'message' => 'API root. Versioned routes live under /v1 (public URL /api/v1). Browser UI under /laravel/*.',
        'version' => 'v1',
        'phase' => 5,
        'slice' => 31,
    ]);
});

/*
| Phase 5 slice 4: Blade login (public edge path /laravel/login via nginx rewrite).
*/
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout');

/*
| Phase 5 slice 5: authenticated Blade pages (edge /laravel/admin/...).
*/
Route::middleware(['auth', 'rms.web_admin'])->group(function () {
    Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::get('/admin/users/{username}/edit', [AdminUserController::class, 'edit'])
        ->where('username', '[A-Za-z0-9._-]+')
        ->name('admin.users.edit');
    Route::get('/admin/departments', [AdminDepartmentController::class, 'index'])->name('admin.departments');
    Route::get('/admin/departments/{id}/edit', [AdminDepartmentController::class, 'edit'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('admin.departments.edit');
    Route::get('/admin/positions', [AdminPositionController::class, 'index'])->name('admin.positions');
    Route::get('/admin/positions/{id}/edit', [AdminPositionController::class, 'edit'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('admin.positions.edit');
    Route::get('/admin/tickets', [AdminTicketController::class, 'index'])->name('admin.tickets');
    Route::get('/admin/tickets/{ref}', [AdminTicketDetailController::class, 'index'])
        ->where('ref', 'RISK-[A-Za-z0-9\\-]+')
        ->name('admin.tickets.detail');
    Route::get('/admin/audit-logs', [AdminAuditLogsController::class, 'index'])
        ->name('admin.audit.logs');
    Route::get('/admin/settings', [AdminSettingsController::class, 'index'])
        ->name('admin.settings');
    Route::get('/admin/profile', [ProfileController::class, 'admin'])->name('admin.profile');
});

/*
| Phase 5 slice 22–24: Department Head Blade dashboard + queues + ticket detail.
| Ownership / action-plan / close POSTs remain on Express.
*/
Route::middleware(['auth', 'rms.web_dept_head'])->group(function () {
    Route::get('/dept', [DeptDashboardController::class, 'index'])->name('dept.dashboard');
    Route::get('/dept/inbox', [DeptQueueController::class, 'inbox'])->name('dept.inbox');
    Route::get('/dept/active', [DeptQueueController::class, 'active'])->name('dept.active');
    Route::get('/dept/drafts', [DeptQueueController::class, 'drafts'])->name('dept.drafts');
    Route::get('/dept/returned', [DeptQueueController::class, 'returned'])->name('dept.returned');
    Route::get('/dept/overdue', [DeptQueueController::class, 'overdue'])->name('dept.overdue');
    Route::get('/dept/closure', [DeptQueueController::class, 'closure'])->name('dept.closure');
    Route::get('/dept/tickets', [DeptQueueController::class, 'tickets'])->name('dept.tickets');
    Route::get('/dept/tickets/{reference}', [DeptTicketDetailController::class, 'show'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.show');
});

/*
| Phase 5 slice 25–27: Risk Management Officer Blade dashboard + queues + ticket detail.
| Thread-comment / reopen POSTs remain on Express.
*/
Route::middleware(['auth', 'rms.web_officer'])->group(function () {
    Route::get('/officer', [OfficerDashboardController::class, 'index'])->name('officer.dashboard');
    Route::get('/officer/tickets', [OfficerQueueController::class, 'tickets'])->name('officer.tickets');
    Route::get('/officer/overdue', [OfficerQueueController::class, 'overdue'])->name('officer.overdue');
    Route::get('/officer/monitoring', [OfficerQueueController::class, 'monitoring'])->name('officer.monitoring');
    Route::get('/officer/action-plans', [OfficerQueueController::class, 'actionPlans'])->name('officer.action-plans');
    Route::get('/officer/tickets/{reference}', [OfficerTicketDetailController::class, 'show'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('officer.tickets.show');
});

/*
| Phase 5 slice 28: Executive Committee Blade dashboard.
| Overview GET only for now; other executive screens stay on Express.
*/
Route::middleware(['auth', 'rms.web_executive'])->group(function () {
    Route::get('/executive', [ExecutiveDashboardController::class, 'index'])->name('executive.dashboard');
});

/*
| Phase 5 slice 29–31: President dashboard + queues + ticket detail (Blade GET).
| Decision / comment POSTs stay on Express.
*/
Route::middleware(['auth', 'rms.web_president'])->group(function () {
    Route::get('/president', [PresidentDashboardController::class, 'index'])->name('president.dashboard');
    Route::get('/president/pending', [PresidentQueueController::class, 'pending'])->name('president.pending');
    Route::get('/president/high', [PresidentQueueController::class, 'high'])->name('president.high');
    Route::get('/president/critical', [PresidentQueueController::class, 'critical'])->name('president.critical');
    Route::get('/president/trends', [PresidentQueueController::class, 'trends'])->name('president.trends');
    Route::get('/president/tickets/{reference}', [PresidentTicketDetailController::class, 'show'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('president.tickets.show');
});

/*
| Phase 5 slice 6–13: Ticket Reporter Blade pages.
*/
Route::middleware(['auth', 'rms.web_supervisor'])->group(function () {
    Route::get('/supervisor', [SupervisorDashboardController::class, 'index'])->name('supervisor.dashboard');
    Route::get('/supervisor/profile', [ProfileController::class, 'supervisor'])->name('supervisor.profile');

    Route::get('/supervisor/tickets', [SupervisorTicketController::class, 'index'])->name('supervisor.tickets');
    Route::get('/supervisor/drafts', [SupervisorTicketController::class, 'drafts'])->name('supervisor.drafts');
    Route::get('/supervisor/submitted', [SupervisorTicketController::class, 'submitted'])->name('supervisor.submitted');
    Route::get('/supervisor/returned', [SupervisorTicketController::class, 'returned'])->name('supervisor.returned');
    Route::get('/supervisor/overdue', [SupervisorTicketController::class, 'overdue'])->name('supervisor.overdue');

    Route::get('/supervisor/tickets/new', [SupervisorTicketFormController::class, 'create'])->name('supervisor.tickets.new');
    Route::get('/supervisor/tickets/new/preview/{reference}', [SupervisorTicketFormController::class, 'preview'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.preview');
    Route::get('/supervisor/tickets/{reference}/edit', [SupervisorTicketFormController::class, 'edit'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.edit');
    Route::get('/supervisor/tickets/{reference}', [SupervisorTicketController::class, 'show'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.show');

    Route::get('/supervisor/actions', [SupervisorActionController::class, 'index'])
        ->name('supervisor.actions');

    Route::get('/supervisor/accomplishments', [SupervisorAccomplishmentController::class, 'index'])
        ->name('supervisor.accomplishments');

    Route::get('/supervisor/notifications', [SupervisorNotificationController::class, 'index'])
        ->name('supervisor.notifications');
    Route::post('/supervisor/notifications/read-all', [SupervisorNotificationController::class, 'markAllRead'])
        ->name('supervisor.notifications.readAll');
    Route::get('/supervisor/notifications/open/{id}', [SupervisorNotificationController::class, 'open'])
        ->name('supervisor.notifications.open');
});
