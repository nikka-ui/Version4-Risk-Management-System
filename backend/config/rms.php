<?php

/*
|--------------------------------------------------------------------------
| RMS application settings
|--------------------------------------------------------------------------
|
| Laravel owns browser login, sessions, RBAC, and Postgres SoT.
| store.json is import-only unless dual-write flags are re-enabled.
|
*/

return [

    /*
    | Path to store.json for import (and optional dual-write).
    | In Docker, compose mounts docker/data/store.json at /import/store.json.
    */
    'store_json_path' => env('STORE_JSON_PATH', storage_path('app/import/store.json')),

    /*
    | Shared secret for /internal/* dual-write routes (optional; off by default).
    | Send as header: X-RMS-Service-Token: <value>
    */
    'internal_service_token' => env('RMS_INTERNAL_SERVICE_TOKEN', ''),

    /*
    | Phase 10 slice 3: ticket dual-write to store.json is OFF by default.
    | Set USE_LARAVEL_INTERNAL_TICKETS=true to re-enable the mirror.
    */
    'store_json_ticket_mirror' => filter_var(env('USE_LARAVEL_INTERNAL_TICKETS', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Phase 10 slice 3: org dual-write to store.json is OFF by default.
    | Set USE_LARAVEL_INTERNAL_ORG=true to re-enable the mirror.
    */
    'store_json_org_mirror' => filter_var(env('USE_LARAVEL_INTERNAL_ORG', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Phase 6 slice 1: edge nginx `location = /` proxies to Laravel.
    */
    'edge_root' => filter_var(env('USE_LARAVEL_EDGE_ROOT', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Phase 6 slice 2: unprefixed Blade URLs (/login, /admin, /supervisor, …).
    */
    'edge_ui' => filter_var(env('USE_LARAVEL_EDGE_UI', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Phase 11 slice 1: Flask ai-service base URL for /classify and /summarize.
    */
    'ai_service_url' => env('AI_SERVICE_URL', 'http://ai-service:5000'),

    /*
    | HTTP timeout (seconds) when calling ai-service. On failure, PHP stub is used.
    */
    'ai_service_timeout' => (int) env('AI_SERVICE_TIMEOUT', 3),

];
