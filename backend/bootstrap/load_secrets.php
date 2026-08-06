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

// Ephemeral key when secret is still the example placeholder (local scaffold only).
$appKey = $_ENV['APP_KEY'] ?? $_SERVER['APP_KEY'] ?? getenv('APP_KEY');
if ($appKey === false || $appKey === null || $appKey === '' || (is_string($appKey) && str_contains($appKey, 'CHANGE_ME'))) {
    $generated = 'base64:'.base64_encode(random_bytes(32));
    putenv("APP_KEY={$generated}");
    $_ENV['APP_KEY'] = $generated;
    $_SERVER['APP_KEY'] = $generated;
}
