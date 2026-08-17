# RMS Laravel 11 app (`api` container)

Source for the Docker `api` service. Edge nginx proxies `/api/*` to this app after stripping the `/api` prefix, so versioned routes are registered as `/v1/...` (public URL `/api/v1/...`). Blade UI is served on `/login` and role consoles.

See [`docs/LARAVEL_MIGRATION.md`](../docs/LARAVEL_MIGRATION.md).

## Surface

| Route / command | Purpose |
|-----------------|--------|
| `GET /v1/health` | API health |
| `POST /v1/auth/token` | Sanctum personal access token |
| `GET /v1/users/me` | Current user (Bearer) |
| Blade `/login`, `/{role}` | Browser UI |
| `php artisan rms:import-users` | Import users from `store.json` |
| `php artisan rms:import-settings` | Import `systemSettings` from `store.json` into Postgres |
| `php artisan rms:import-audit-logs` | Import `auditLogs` from `store.json` into Postgres |
| `php artisan rms:smoke-slice10-no-dual-write` | Smoke dual-write off + Postgres audit path |
| `php artisan rms:smoke-slice11-ai` | Smoke ai-service classify + PHP stub fallback |
| `php artisan rms:smoke-slice11-ai-results` | Smoke `ai_analysis_results` persistence |
| `php artisan rms:smoke-slice11-ai-history` | Smoke admin AI history Blade |

## Local notes

- Build via `docker/api/Dockerfile` (copies this tree, `composer install --no-dev`).
- Secrets: `APP_KEY_FILE` / `DB_PASSWORD_FILE` from Compose (`docker/secrets/`).
- Health: container nginx serves `GET /health` (not Laravel); Laravel also exposes `/v1/health`.
- `STORE_JSON_PATH` defaults to `/import/store.json` (compose mounts `docker/data/store.json`).
- Sanctum tokens are for API clients; browser auth is the Laravel web session.
