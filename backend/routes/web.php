<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'rms-api',
        'status' => 'ok',
        'framework' => 'laravel',
        'message' => 'API root. Versioned routes live under /v1 (public URL /api/v1).',
        'version' => 'v1',
    ]);
});
