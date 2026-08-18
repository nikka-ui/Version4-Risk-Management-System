<?php

/**
 * Load Docker Compose file secrets into process env before Laravel boots.
 * Needed for `artisan` via `docker compose exec` (skips container entrypoint).
 */
foreach ([
    'APP_KEY' => 'APP_KEY_FILE',
    'DB_PASSWORD' => 'DB_PASSWORD_FILE',
] as $name => $fileEnv) {
    $current = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
    $file = $_ENV[$fileEnv] ?? $_SERVER[$fileEnv] ?? getenv($fileEnv);

    $missing = $current === false || $current === null || $current === '';
    $placeholder = is_string($current) && str_contains($current, 'CHANGE_ME');

    if (is_string($file) && $file !== '' && is_readable($file) && ($missing || $placeholder)) {
        $value = trim((string) file_get_contents($file));
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        $current = $value;
    }
}

// Never mint a new key per request: encrypted session cookies would fail MAC and
// Blade POSTs (/login, /forgot-password) would 419. Reuse a file-backed fallback.
$appKey = $_ENV['APP_KEY'] ?? $_SERVER['APP_KEY'] ?? getenv('APP_KEY');
$unusable = $appKey === false || $appKey === null || $appKey === ''
    || (is_string($appKey) && str_contains($appKey, 'CHANGE_ME'));

if ($unusable) {
    $cache = dirname(__DIR__).'/storage/framework/app_key_generated';
    $cached = is_readable($cache) ? trim((string) file_get_contents($cache)) : '';
    if ($cached !== '' && ! str_contains($cached, 'CHANGE_ME')) {
        $generated = $cached;
    } else {
        $generated = 'base64:'.base64_encode(random_bytes(32));
        @file_put_contents($cache, $generated, LOCK_EX);
    }
    putenv("APP_KEY={$generated}");
    $_ENV['APP_KEY'] = $generated;
    $_SERVER['APP_KEY'] = $generated;
}
