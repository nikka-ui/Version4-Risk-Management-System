# RMS Laravel 11 API (Phase 1)

Source for the Docker `api` service. Edge nginx proxies `/api/*` to this app after stripping the `/api` prefix, so versioned routes are registered as `/v1/...` (public URL `/api/v1/...`).

**Not** the live product UI — tickets, login, and RBAC still run in `docker/web` (Express + `store.json`). See [`docs/LARAVEL_MIGRATION.md`](../docs/LARAVEL_MIGRATION.md).

## Phase 1 surface

| Route / command | Purpose |
|-----------------|--------|
| `GET /v1/health` | API health |
| `POST /v1/auth/token` | Sanctum personal access token |
| `GET /v1/users/me` | Current user (Bearer) |
| `php artisan rms:import-users` | Import users from Express `store.json` |

## Local notes

- Build via `docker/api/Dockerfile` (copies this tree, `composer install --no-dev`).
- Secrets: `APP_KEY_FILE` / `DB_PASSWORD_FILE` from Compose (`docker/secrets/`).
- Health: container nginx serves `GET /health` (not Laravel); Laravel also exposes `/v1/health`.
- `STORE_JSON_PATH` defaults to `/import/store.json` (compose mounts `docker/web/data/store.json` read-only).
- Sanctum tokens are for API clients; Express session auth is unchanged.
