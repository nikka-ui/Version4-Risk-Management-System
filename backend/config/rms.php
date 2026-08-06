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

];
