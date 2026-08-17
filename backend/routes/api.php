<?php

use App\Http\Controllers\AiAnalysisController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ReportLogController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (served under /v1 after nginx strips the public /api prefix)
|--------------------------------------------------------------------------
|
| Phase 12 slice 1: GitHub Actions CI (PHPUnit + ai-service tests); health gate.
|
*/

Route::get('/', function () {
    return response()->json([
        'service' => 'rms-api',
        'status' => 'ok',
        'framework' => 'laravel',
        'version' => 'v1',
        'phase' => 12,
        'slice' => 1,
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'rms-api',
        'framework' => 'laravel',
        'version' => 'v1',
        'phase' => 12,
        'slice' => 1,
    ]);
});

Route::post('/auth/token', [AuthController::class, 'token']);
Route::post('/auth/verify', [AuthController::class, 'verify']);
Route::post('/auth/bridge-exchange', [LoginController::class, 'exchange']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/me', [UserController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('rms.admin')->group(function () {
        Route::post('/users/sync', [UserController::class, 'sync']);
    });

    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/{externalId}', [DepartmentController::class, 'show']);
    Route::get('/positions', [PositionController::class, 'index']);
    Route::get('/positions/{externalId}', [PositionController::class, 'show']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{reference}', [TicketController::class, 'show']);
    Route::get('/tickets/{reference}/ai-analysis', [AiAnalysisController::class, 'index']);
    Route::middleware('rms.admin')->post('/tickets/{reference}/ai/reclassify', [AiAnalysisController::class, 'reclassify']);
    Route::patch('/tickets/{reference}', [TicketController::class, 'update']);
    Route::delete('/tickets/{reference}', [TicketController::class, 'destroy']);
    Route::post('/tickets/{reference}/submit', [TicketController::class, 'submit']);
    Route::get('/tickets/{reference}/accomplishment', [TicketController::class, 'accomplishment']);
    Route::post('/tickets/{reference}/comments', [TicketController::class, 'addComment']);

    Route::get('/tickets/{reference}/attachments', [AttachmentController::class, 'index']);
    Route::post('/tickets/{reference}/attachments', [AttachmentController::class, 'store']);
    Route::post('/tickets/{reference}/attachments/sync', [AttachmentController::class, 'sync']);
    Route::post('/tickets/{reference}/attachments/upload', [AttachmentController::class, 'upload']);
    Route::get('/attachments/{id}', [AttachmentController::class, 'show']);
    Route::get('/attachments/{id}/download', [AttachmentController::class, 'download']);
    Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    Route::get('/report-logs', [ReportLogController::class, 'index']);
    Route::post('/report-logs', [ReportLogController::class, 'store']);

    Route::middleware('rms.dept_head')->group(function () {
        Route::post('/tickets/{reference}/accept', [TicketController::class, 'accept']);
        Route::post('/tickets/{reference}/reject', [TicketController::class, 'reject']);
        Route::put('/tickets/{reference}/action-plan', [TicketController::class, 'saveActionPlan']);
        Route::post('/tickets/{reference}/return', [TicketController::class, 'returnForRevision']);
        Route::post('/tickets/{reference}/reassign', [TicketController::class, 'reassign']);
        Route::post('/tickets/{reference}/close', [TicketController::class, 'close']);
        Route::post('/tickets/{reference}/personnel', [TicketController::class, 'assignPersonnel']);
        Route::post('/tickets/{reference}/documents', [TicketController::class, 'recordDocuments']);
    });

    Route::middleware('rms.president')->group(function () {
        Route::post('/tickets/{reference}/president-decision', [TicketController::class, 'presidentDecision']);
    });

    Route::middleware('rms.officer')->group(function () {
        Route::post('/tickets/{reference}/reopen', [TicketController::class, 'reopen']);
    });

    Route::middleware('rms.admin')->group(function () {
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::patch('/departments/{externalId}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{externalId}', [DepartmentController::class, 'destroy']);

        Route::post('/positions', [PositionController::class, 'store']);
        Route::patch('/positions/{externalId}', [PositionController::class, 'update']);
        Route::delete('/positions/{externalId}', [PositionController::class, 'destroy']);
    });
});
