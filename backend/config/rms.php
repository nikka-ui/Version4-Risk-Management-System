<?php

/*
|--------------------------------------------------------------------------
| RMS application settings (Phase 3 — identity + org + ticket read foundation)
|--------------------------------------------------------------------------
|
| Express (docker/web) still owns browser login, sessions, and RBAC.
| These settings support Laravel user import and future service-to-service
| calls only. Do not flip feature flags that cut over live auth.
|
*/

return [

    /*
    | Path to Express store.json for `php artisan rms:import-users`.
    | In Docker, compose mounts docker/web/data/store.json at /import/store.json.
    */
    'store_json_path' => env('STORE_JSON_PATH', storage_path('app/import/store.json')),

    /*
    | Optional shared secret for Express → Laravel service calls later.
    | Send as header: X-RMS-Service-Token: <value>
    | Empty = middleware rejects service-token auth (not used by live login).
    */
    'internal_service_token' => env('RMS_INTERNAL_SERVICE_TOKEN', ''),

    /*
    | Phase 7 slice 1: Express base URL for Laravel → store.json org mirror.
    | Empty disables the mirror (Postgres still updates).
    */
    'express_web_url' => env('EXPRESS_WEB_INTERNAL_URL', 'http://web:3000'),

    /*
    | Phase 9 slice 5: write ticket dual-write to store.json in Laravel (upsert/soft-delete).
    | Soak sets false so Express /internal/tickets/* remain the write path.
    */
    'store_json_ticket_mirror' => filter_var(env('USE_LARAVEL_INTERNAL_TICKETS', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Phase 9 slice 6: write org dual-write to store.json in Laravel (departments/positions/users/settings).
    | Soak sets false so Express /internal/org/* remain the write path.
    */
    'store_json_org_mirror' => filter_var(env('USE_LARAVEL_INTERNAL_ORG', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Phase 6 slice 1: edge nginx `location = /` proxies to Laravel.
    | When true (compose default), `/` redirects to Blade login or role console.
    | When false (soak), `/` redirects to Express `/login` or Express role path.
    */
    'edge_root' => filter_var(env('USE_LARAVEL_EDGE_ROOT', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Phase 6 slice 2: unprefixed Blade URLs (/login, /admin, /supervisor, …).
    | When true (compose default), nginx GET role consoles → Laravel and redirects omit /laravel.
    | When false (soak), keep /laravel/* rewrite paths.
    */
    'edge_ui' => filter_var(env('USE_LARAVEL_EDGE_UI', true), FILTER_VALIDATE_BOOLEAN),

];
