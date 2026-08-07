<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (served under /v1 after nginx strips the public /api prefix)
|--------------------------------------------------------------------------
|
| Phase 3 slice 6: dept return/reassign/close + president decision;
| USE_LARAVEL_API defaults OFF. Express owns browser login and live workflow.
|
*/

Route::get('/', function () {
    return response()->json([
        'service' => 'rms-api',
        'status' => 'ok',
        'framework' => 'laravel',
        'version' => 'v1',
        'phase' => 3,
        'slice' => 6,
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'rms-api',
        'framework' => 'laravel',
        'version' => 'v1',
        'phase' => 3,
        'slice' => 6,
    ]);
});

Route::post('/auth/token', [AuthController::class, 'token']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/me', [UserController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/{externalId}', [DepartmentController::class, 'show']);
    Route::get('/positions', [PositionController::class, 'index']);
    Route::get('/positions/{externalId}', [PositionController::class, 'show']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{reference}', [TicketController::class, 'show']);
    Route::patch('/tickets/{reference}', [TicketController::class, 'update']);
    Route::delete('/tickets/{reference}', [TicketController::class, 'destroy']);
    Route::post('/tickets/{reference}/submit', [TicketController::class, 'submit']);
    Route::get('/tickets/{reference}/accomplishment', [TicketController::class, 'accomplishment']);

    Route::middleware('rms.dept_head')->group(function () {
        Route::post('/tickets/{reference}/accept', [TicketController::class, 'accept']);
        Route::post('/tickets/{reference}/reject', [TicketController::class, 'reject']);
        Route::put('/tickets/{reference}/action-plan', [TicketController::class, 'saveActionPlan']);
        Route::post('/tickets/{reference}/return', [TicketController::class, 'returnForRevision']);
        Route::post('/tickets/{reference}/reassign', [TicketController::class, 'reassign']);
        Route::post('/tickets/{reference}/close', [TicketController::class, 'close']);
    });

    Route::middleware('rms.president')->group(function () {
        Route::post('/tickets/{reference}/president-decision', [TicketController::class, 'presidentDecision']);
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
