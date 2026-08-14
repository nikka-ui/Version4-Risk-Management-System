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
use App\Http\Controllers\ExecutiveTicketDetailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InternalOrgMirrorController;
use App\Http\Controllers\InternalTicketMirrorController;
use App\Http\Controllers\OfficerDashboardController;
use App\Http\Controllers\OfficerQueueController;
use App\Http\Controllers\OfficerTicketDetailController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PresidentDashboardController;
use App\Http\Controllers\PresidentQueueController;
use App\Http\Controllers\PresidentTicketDetailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleAttachmentController;
use App\Http\Controllers\RoleNotificationController;
use App\Http\Controllers\SupervisorAccomplishmentController;
use App\Http\Controllers\SupervisorActionController;
use App\Http\Controllers\SupervisorDashboardController;
use App\Http\Controllers\SupervisorNotificationController;
use App\Http\Controllers\SupervisorTicketController;
use App\Http\Controllers\SupervisorTicketFormController;
use Illuminate\Support\Facades\Route;

/*
| Phase 6 slice 2: edge `/` redirects to unprefixed `/login` or `/{role}`.
| JSON identity remains on /v1 and /v1/health. POSTs stay on Express.
*/
Route::get('/', HomeController::class)->name('home');

/*
| Phase 6 slice 6: Employee stub /dashboard; other roles redirect to their console.
*/
Route::middleware(['auth'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

/*
| Phase 6 slice 2: Blade login at /login ( /laravel/login still rewritten).
*/
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::get('/auth/bridge', [LoginController::class, 'bridge'])->name('auth.bridge');
Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout'); // Phase 9 slice 3: edge /logout

/*
| Phase 9 slice 5–6: dual-write internals (service token; CSRF exempt).
*/
Route::middleware('rms.service_token')->prefix('internal/tickets')->group(function () {
    Route::post('/upsert', [InternalTicketMirrorController::class, 'upsert']);
    Route::post('/soft-delete', [InternalTicketMirrorController::class, 'softDelete']);
    Route::post('/delete-draft', [InternalTicketMirrorController::class, 'deleteDraft']);
});

Route::middleware('rms.service_token')->prefix('internal/org')->group(function () {
    Route::post('/departments', [InternalOrgMirrorController::class, 'departments']);
    Route::post('/positions', [InternalOrgMirrorController::class, 'positions']);
    Route::post('/users', [InternalOrgMirrorController::class, 'users']);
    Route::post('/settings', [InternalOrgMirrorController::class, 'settings']);
});

/*
| Phase 5 slice 5: authenticated Blade pages (edge /laravel/admin/...).
*/
Route::middleware(['auth', 'rms.web_admin'])->group(function () {
    Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{username}/edit', [AdminUserController::class, 'edit'])
        ->where('username', '[A-Za-z0-9._-]+')
        ->name('admin.users.edit');
    Route::post('/admin/users/{username}/edit', [AdminUserController::class, 'update'])
        ->where('username', '[A-Za-z0-9._-]+')
        ->name('admin.users.update');
    Route::post('/admin/users/{username}/delete', [AdminUserController::class, 'destroy'])
        ->where('username', '[A-Za-z0-9._-]+')
        ->name('admin.users.destroy');
    Route::post('/admin/users/{username}/activate', [AdminUserController::class, 'activate'])
        ->where('username', '[A-Za-z0-9._-]+')
        ->name('admin.users.activate');
    Route::post('/admin/users/{username}/deactivate', [AdminUserController::class, 'deactivate'])
        ->where('username', '[A-Za-z0-9._-]+')
        ->name('admin.users.deactivate');
    Route::get('/admin/users/{username}/reset-password', [AdminUserController::class, 'showResetPassword'])
        ->where('username', '[A-Za-z0-9._-]+')
        ->name('admin.users.reset');
    Route::post('/admin/users/{username}/reset-password', [AdminUserController::class, 'resetPassword'])
        ->where('username', '[A-Za-z0-9._-]+')
        ->name('admin.users.reset.store');
    Route::get('/admin/departments', [AdminDepartmentController::class, 'index'])->name('admin.departments');
    Route::post('/admin/departments', [AdminDepartmentController::class, 'store'])->name('admin.departments.store');
    Route::get('/admin/departments/{id}/edit', [AdminDepartmentController::class, 'edit'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('admin.departments.edit');
    Route::post('/admin/departments/{id}/edit', [AdminDepartmentController::class, 'update'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('admin.departments.update');
    Route::post('/admin/departments/{id}/delete', [AdminDepartmentController::class, 'destroy'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('admin.departments.destroy');
    Route::get('/admin/positions', [AdminPositionController::class, 'index'])->name('admin.positions');
    Route::post('/admin/positions', [AdminPositionController::class, 'store'])->name('admin.positions.store');
    Route::get('/admin/positions/{id}/edit', [AdminPositionController::class, 'edit'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('admin.positions.edit');
    Route::post('/admin/positions/{id}/edit', [AdminPositionController::class, 'update'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('admin.positions.update');
    Route::post('/admin/positions/{id}/delete', [AdminPositionController::class, 'destroy'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('admin.positions.destroy');
    Route::get('/admin/tickets', [AdminTicketController::class, 'index'])->name('admin.tickets');
    Route::get('/admin/tickets/{ref}', [AdminTicketDetailController::class, 'index'])
        ->where('ref', 'RISK-[A-Za-z0-9\\-]+')
        ->name('admin.tickets.detail');
    Route::post('/admin/tickets/{ref}/delete', [AdminTicketController::class, 'destroy'])
        ->where('ref', 'RISK-[A-Za-z0-9\\-]+')
        ->name('admin.tickets.destroy');
    Route::get('/admin/audit-logs', [AdminAuditLogsController::class, 'index'])
        ->name('admin.audit.logs');
    Route::get('/admin/audit-logs/export', [AdminAuditLogsController::class, 'export'])
        ->name('admin.audit.logs.export');
    Route::get('/admin/settings', [AdminSettingsController::class, 'index'])
        ->name('admin.settings');
    Route::post('/admin/settings', [AdminSettingsController::class, 'update'])
        ->name('admin.settings.update');
    Route::post('/admin/settings/reset-landing', [AdminSettingsController::class, 'resetLanding'])
        ->name('admin.settings.reset-landing');
    Route::post('/admin/settings/reset-ai', [AdminSettingsController::class, 'resetAi'])
        ->name('admin.settings.reset-ai');
    Route::get('/admin/profile', [ProfileController::class, 'admin'])->name('admin.profile');
});

/*
| Phase 5 slice 22–24 + Phase 7 slice 6 + slice 13 + Phase 8 slice 3–5: Department Head Blade dashboard + queues + ticket detail + workflow + comment + document + personnel/resolution + comment edit/react POSTs.
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
    Route::get('/dept/attachments/{id}', [RoleAttachmentController::class, 'download'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('dept.attachments.download');
    Route::post('/dept/tickets/{reference}/accept', [DeptTicketDetailController::class, 'accept'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.accept');
    Route::post('/dept/tickets/{reference}/reject', [DeptTicketDetailController::class, 'reject'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.reject');
    Route::post('/dept/tickets/{reference}/return', [DeptTicketDetailController::class, 'returnForRevision'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.return');
    Route::post('/dept/tickets/{reference}/reassign', [DeptTicketDetailController::class, 'reassign'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.reassign');
    Route::post('/dept/tickets/{reference}/action-plan', [DeptTicketDetailController::class, 'saveActionPlan'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.action-plan');
    Route::post('/dept/tickets/{reference}/close', [DeptTicketDetailController::class, 'close'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.close');
    Route::post('/dept/tickets/{reference}/personnel', [DeptTicketDetailController::class, 'assignPersonnel'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.personnel');
    Route::post('/dept/tickets/{reference}/resolution', [DeptTicketDetailController::class, 'resolution'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.resolution');
    Route::post('/dept/tickets/{reference}/comment', [DeptTicketDetailController::class, 'comment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.comment');
    Route::post('/dept/tickets/{reference}/comment/edit', [DeptTicketDetailController::class, 'editComment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.comment.edit');
    Route::post('/dept/tickets/{reference}/comment/react', [DeptTicketDetailController::class, 'reactComment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.comment.react');
    Route::post('/dept/tickets/{reference}/documents', [DeptTicketDetailController::class, 'uploadDocuments'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('dept.tickets.documents');
    Route::post('/dept/notifications/read-all', [RoleNotificationController::class, 'markAllRead'])
        ->name('dept.notifications.read-all');
    Route::get('/dept/notifications/open/{id}', [RoleNotificationController::class, 'open'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('dept.notifications.open');
});

/*
| Phase 5 slice 25–27 + Phase 6 slice 5 + Phase 7 slice 8 + slice 11: RMO dashboard + queues + ticket detail + reopen + thread-comment POSTs.
*/
Route::middleware(['auth', 'rms.web_officer'])->group(function () {
    Route::get('/officer', [OfficerDashboardController::class, 'index'])->name('officer.dashboard');
    Route::get('/officer/tickets', [OfficerQueueController::class, 'tickets'])->name('officer.tickets');
    Route::get('/officer/overdue', [OfficerQueueController::class, 'overdue'])->name('officer.overdue');
    Route::get('/officer/monitoring', [OfficerQueueController::class, 'monitoring'])->name('officer.monitoring');
    Route::get('/officer/action-plans', [OfficerQueueController::class, 'actionPlans'])->name('officer.action-plans');
    Route::get('/officer/ai-review', [OfficerQueueController::class, 'aiReview'])->name('officer.ai-review');
    Route::get('/officer/review', [OfficerQueueController::class, 'review'])->name('officer.review');
    Route::get('/officer/final-validation', [OfficerQueueController::class, 'finalValidation'])->name('officer.final-validation');
    Route::get('/officer/tickets/{reference}', [OfficerTicketDetailController::class, 'show'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('officer.tickets.show');
    Route::get('/officer/attachments/{id}', [RoleAttachmentController::class, 'download'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('officer.attachments.download');
    Route::post('/officer/tickets/{reference}/reopen', [OfficerTicketDetailController::class, 'reopen'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('officer.tickets.reopen');
    Route::post('/officer/tickets/{reference}/thread-comment', [OfficerTicketDetailController::class, 'comment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('officer.tickets.thread-comment');
    Route::post('/officer/notifications/read-all', [RoleNotificationController::class, 'markAllRead'])
        ->name('officer.notifications.read-all');
    Route::get('/officer/notifications/open/{id}', [RoleNotificationController::class, 'open'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('officer.notifications.open');
});

/*
| Phase 5 slice 28 + Phase 6 slice 3–4 + Phase 7 slice 10: Executive dashboard + oversight + ticket detail + comment POST.
*/
Route::middleware(['auth', 'rms.web_executive'])->group(function () {
    Route::get('/executive', [ExecutiveDashboardController::class, 'index'])->name('executive.dashboard');
    Route::get('/executive/heatmap', [ExecutiveDashboardController::class, 'heatmap'])->name('executive.heatmap');
    Route::get('/executive/reports', [ExecutiveDashboardController::class, 'reports'])->name('executive.reports');
    Route::get('/executive/trends', [ExecutiveDashboardController::class, 'trends'])->name('executive.trends');
    Route::get('/executive/statistics', [ExecutiveDashboardController::class, 'statistics'])->name('executive.statistics');
    Route::get('/executive/departments', [ExecutiveDashboardController::class, 'departments'])->name('executive.departments');
    Route::get('/executive/register', [ExecutiveDashboardController::class, 'register'])->name('executive.register');
    Route::get('/executive/critical', [ExecutiveDashboardController::class, 'critical'])->name('executive.critical');
    Route::get('/executive/tickets', [ExecutiveDashboardController::class, 'ticketsIndex'])->name('executive.tickets');
    Route::get('/executive/tickets/{reference}', [ExecutiveTicketDetailController::class, 'show'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('executive.tickets.show');
    Route::get('/executive/attachments/{id}', [RoleAttachmentController::class, 'download'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('executive.attachments.download');
    Route::post('/executive/tickets/{reference}/comment', [ExecutiveTicketDetailController::class, 'comment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('executive.tickets.comment');
    Route::post('/executive/notifications/read-all', [RoleNotificationController::class, 'markAllRead'])
        ->name('executive.notifications.read-all');
    Route::get('/executive/notifications/open/{id}', [RoleNotificationController::class, 'open'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('executive.notifications.open');
});

/*
| Phase 5 slice 29–31 + Phase 7 slice 9 + slice 12: President dashboard + queues + ticket detail + decision + comment POSTs.
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
    Route::get('/president/attachments/{id}', [RoleAttachmentController::class, 'download'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('president.attachments.download');
    Route::post('/president/tickets/{reference}/decision', [PresidentTicketDetailController::class, 'decide'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('president.tickets.decision');
    Route::post('/president/tickets/{reference}/comment', [PresidentTicketDetailController::class, 'comment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('president.tickets.comment');
    Route::post('/president/notifications/read-all', [RoleNotificationController::class, 'markAllRead'])
        ->name('president.notifications.read-all');
    Route::get('/president/notifications/open/{id}', [RoleNotificationController::class, 'open'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('president.notifications.open');
});

/*
| Phase 5 slice 6–13 + Phase 7 slice 7 + Phase 8 slice 1–2: Ticket Reporter Blade pages + preview save/submit +
| draft delete + create/edit + evidence/accomplishment uploads. Comment POSTs remain on Express.
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
    Route::post('/supervisor/tickets/new/preview', [SupervisorTicketFormController::class, 'storePreview'])
        ->name('supervisor.tickets.preview.create');
    Route::get('/supervisor/tickets/new/preview/{reference}', [SupervisorTicketFormController::class, 'preview'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.preview');
    Route::post('/supervisor/tickets/new/preview/{reference}/save', [SupervisorTicketFormController::class, 'saveDraft'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.preview.save');
    Route::post('/supervisor/tickets/new/preview/{reference}/submit', [SupervisorTicketFormController::class, 'submit'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.preview.submit');
    Route::get('/supervisor/tickets/{reference}/edit', [SupervisorTicketFormController::class, 'edit'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.edit');
    Route::post('/supervisor/tickets/{reference}/edit', [SupervisorTicketFormController::class, 'updateEdit'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.edit.update');
    Route::post('/supervisor/tickets/{reference}/delete', [SupervisorTicketController::class, 'destroy'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.delete');
    Route::post('/supervisor/tickets/{reference}/evidence', [SupervisorTicketController::class, 'addEvidence'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.evidence');
    Route::post('/supervisor/tickets/{reference}/accomplishment', [SupervisorTicketController::class, 'submitAccomplishment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.accomplishment');
    Route::post('/supervisor/tickets/{reference}/comment', [SupervisorTicketController::class, 'comment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.comment');
    Route::post('/supervisor/tickets/{reference}/comment/edit', [SupervisorTicketController::class, 'editComment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.comment.edit');
    Route::post('/supervisor/tickets/{reference}/comment/react', [SupervisorTicketController::class, 'reactComment'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.comment.react');
    Route::get('/supervisor/tickets/{reference}', [SupervisorTicketController::class, 'show'])
        ->where('reference', 'RISK-[A-Za-z0-9\-]+')
        ->name('supervisor.tickets.show');
    Route::get('/supervisor/attachments/{id}', [RoleAttachmentController::class, 'download'])
        ->where('id', '[A-Za-z0-9._-]+')
        ->name('supervisor.attachments.download');

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
