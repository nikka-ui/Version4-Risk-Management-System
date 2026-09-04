<?php

/*
| Laravel owns browser login, sessions, RBAC, and Postgres SoT.
| Express → Laravel migration is complete (Phase 15). store.json is import-only
| unless dual-write flags are re-enabled. Edge UI flags default on permanently.
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

    /*
    | When true, high-confidence matched AI routing may auto-assign to a department.
    | Low confidence / unmatched departments always require RMO approval (pending_ai_review).
    | Default false in production; local/dev may set RMS_AI_AUTO_ROUTE=true.
    */
    'ai_auto_route' => filter_var(
        env('RMS_AI_AUTO_ROUTE', env('APP_ENV', 'production') === 'local' || env('APP_ENV') === 'testing' ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | Minimum AI confidence required for auto-route when ai_auto_route is enabled.
    */
    'ai_auto_route_min_confidence' => (float) env('RMS_AI_AUTO_ROUTE_MIN_CONFIDENCE', 0.75),

    /*
    | Sanctum personal access token lifetime (minutes). Default 12 hours.
    */
    'sanctum_expiration_minutes' => (int) env('SANCTUM_EXPIRATION_MINUTES', 720),

    /*
    | Hours after assignment/routing for first department response (accept/reject).
    */
    'response_sla_hours' => (int) env('RMS_RESPONSE_SLA_HOURS', 24),

    /*
    | Hours before due date to emit "approaching" notifications.
    */
    'sla_approaching_hours' => (int) env('RMS_SLA_APPROACHING_HOURS', 8),

];
