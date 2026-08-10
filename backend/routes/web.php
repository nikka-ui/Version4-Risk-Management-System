<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\LoginController;
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
        'slice' => 14,
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
    Route::get('/admin/profile', [ProfileController::class, 'admin'])->name('admin.profile');
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
