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
| Browser:  GET http://localhost:8080/api/v1/...
| Upstream: GET /v1/...  (rms-api nginx on :8080)
|
| Phase 3 slice 1: ticket import + read-only APIs.
| Express still owns browser login, admin UI, roles, and all ticket workflow.
|
*/

Route::get('/', function () {
    return response()->json([
        'service' => 'rms-api',
        'status' => 'ok',
        'framework' => 'laravel',
        'version' => 'v1',
        'phase' => 3,
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'rms-api',
        'framework' => 'laravel',
        'version' => 'v1',
        'phase' => 3,
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
    Route::get('/tickets/{reference}', [TicketController::class, 'show']);
    Route::get('/tickets/{reference}/accomplishment', [TicketController::class, 'accomplishment']);

    Route::middleware('rms.admin')->group(function () {
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::patch('/departments/{externalId}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{externalId}', [DepartmentController::class, 'destroy']);

        Route::post('/positions', [PositionController::class, 'store']);
        Route::patch('/positions/{externalId}', [PositionController::class, 'update']);
        Route::delete('/positions/{externalId}', [PositionController::class, 'destroy']);
    });
});
