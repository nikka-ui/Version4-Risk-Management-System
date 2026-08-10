# Environment Variables

Configuration contract for RMS Docker and application services. Copy [`.env.example`](../.env.example) to `.env` at the repository root.

## Variable reference

### Application

| Variable | Default | Used by | Description |
|----------|---------|---------|-------------|
| `APP_ENV` | `local` | api | `local`, `staging`, `production` |
| `APP_URL` | `http://localhost:8080` | api, web | Public URL (browser-facing) |
| `NODE_ENV` | `development` | web | Node runtime mode |
| `FLASK_ENV` | `development` | ai-service | Flask environment |

### Host ports (development)

| Variable | Default | Maps to |
|----------|---------|---------|
| `NGINX_HTTP_PORT` | `8080` | nginx → host |
| `POSTGRES_HOST_PORT` | `5433` | postgres → `127.0.0.1` |
| `AI_DEBUG_PORT` | `5001` | ai-service → `127.0.0.1` |
| `MINIO_API_PORT` | `9000` | minio API |
| `MINIO_CONSOLE_PORT` | `9001` | minio console |
| `MAILPIT_UI_PORT` | `8025` | mailpit UI |

### Database

| Variable | Default | Used by | Description |
|----------|---------|---------|-------------|
| `DB_HOST` | `postgres` | api, web, ai-service | Docker DNS name |
| `DB_PORT` | `5432` | api, web, ai-service | Container port |
| `DB_DATABASE` | `rms` | api, web, postgres | Database name |
| `DB_USERNAME` | `rms` | api, web, postgres | Database user |
| `DB_PASSWORD` | — | — | **Use secret file** `docker/secrets/db_password.txt` |

Postgres container reads `POSTGRES_PASSWORD_FILE=/run/secrets/db_password`.

### Redis

| Variable | Default | Used by |
|----------|---------|---------|
| `REDIS_HOST` | `redis` | api |
| `REDIS_PORT` | `6379` | api |

Connection URL (Laravel): `redis://redis:6379`

### AI service

| Variable | Default | Used by |
|----------|---------|---------|
| `AI_SERVICE_URL` | `http://ai-service:5000` | api |

Internal only — do not point browsers to this URL in production.

### File storage

| Variable | Default | Used by | Notes |
|----------|---------|---------|-------|
| `FILE_STORAGE_DRIVER` | `s3` | api | `s3` or `local` |
| `S3_ENDPOINT` | `http://minio:9000` | api | Dev MinIO only |
| `S3_BUCKET` | `rms-uploads` | api | Create bucket in MinIO console |
| `S3_ACCESS_KEY_ID` | — | api | Match MinIO root user in dev |
| `S3_SECRET_ACCESS_KEY` | — | api | Secret — use env or vault in prod |
| `S3_USE_PATH_STYLE_ENDPOINT` | `true` | api | Required for MinIO |

Production: use AWS S3 or Azure Blob with IAM-scoped credentials; do not run MinIO in prod compose.

### MinIO (dev profile)

| Variable | Default |
|----------|---------|
| `MINIO_ROOT_USER` | `rmsminio` |
| `MINIO_ROOT_PASSWORD` | `rmsminio-dev-change-me` |

Change before sharing dev environments.

### Authentication

| Secret / variable | Location | Used by |
|-------------------|----------|---------|
| `SESSION_SECRET` | `.env` | **Express web** (`docker/web`) — cookie session signing. Set a long random value for any shared/production deploy. |
| `APP_KEY` | `docker/secrets/app_key.txt` | Laravel `api` (via `APP_KEY_FILE`) |
| `SANCTUM_STATEFUL_DOMAINS` | `.env` | Laravel Sanctum stateful hosts — e.g. `localhost:8080` |
| `STORE_JSON_PATH` | compose / `.env` | Laravel import path (default `/import/store.json` in Docker) |
| `RMS_INTERNAL_SERVICE_TOKEN` | `.env` | Optional future Express→Laravel service header (`X-RMS-Service-Token`). Empty = disabled. **Not used for browser login.** |
| `USE_LARAVEL_AUTH` | web env | Phase 5 slice 3: defaults **`true`**. Express POST /login verifies via Laravel `/v1/auth/verify`, then sets Express cookie. Set `false` or use `compose.soak.yml` to opt out. |
| `USE_LARAVEL_LOGIN_UI` | web env | Phase 5 slice 4: defaults **`true`**. Express GET `/login` redirects to Laravel Blade `/laravel/login`; success bridges via `/auth/bridge`. |
| `USE_LARAVEL_PROFILE_UI` | web env | Phase 5 slice 5: defaults **`true`**. Express GET `/admin/profile` redirects to Laravel Blade `/laravel/admin/profile`. |
| `USE_LARAVEL_REPORTER_PROFILE_UI` | web env | Phase 5 slice 6: defaults **`true`**. Express GET `/supervisor/profile` redirects to Laravel Blade `/laravel/supervisor/profile`. |
| `USE_LARAVEL_REPORTER_DASHBOARD_UI` | web env | Phase 5 slice 7: defaults **`true`**. Express GET `/supervisor` redirects to Laravel Blade `/laravel/supervisor` (Postgres KPIs). |
| `USE_LARAVEL_REPORTER_TICKETS_UI` | web env | Phase 5 slice 8: defaults **`true`**. Express ticket list routes redirect to `/laravel/supervisor/tickets` (and drafts/submitted/returned/overdue). |
| `USE_LARAVEL_REPORTER_TICKET_DETAIL_UI` | web env | Phase 5 slice 9: defaults **`true`**. Express GET `/supervisor/tickets/:ref` redirects to Blade detail (drafts/returned still go to Express edit). |
| `USE_LARAVEL_REPORTER_NOTIFICATIONS_UI` | web env | Phase 5 slice 10: defaults **`true`**. Express GET `/supervisor/notifications` redirects to Blade notifications (mark-all-read / open use Laravel). |
| `USE_LARAVEL_REPORTER_ACCOMPLISHMENTS_UI` | web env | Phase 5 slice 11: defaults **`true`**. Express GET `/supervisor/accomplishments` redirects to Blade accomplishment history. |
| `USE_LARAVEL_REPORTER_ACTIONS_UI` | web env | Phase 5 slice 12: defaults **`true`**. Express GET `/supervisor/actions` redirects to Blade action-required queue. |
| `USE_LARAVEL_REPORTER_TICKET_FORM_UI` | web env | Phase 5 slice 13: defaults **`true`**. Express GET create/edit/preview form routes redirect to Blade; POST + uploads stay on Express. |
| `USE_LARAVEL_ADMIN_DASHBOARD_UI` | web env | Phase 5 slice 14: defaults **`true`**. Express GET `/admin` redirects to Blade dashboard; management POSTs stay on Express. |
| `USE_LARAVEL_AUTH_FALLBACK` | web env | If auth flag on and Laravel unreachable, fall back to store.json auth. Default **`false`** (fail closed). |
| `USE_LARAVEL_ORG` | web env | Unused (admin org UI stays Express). |
| `USE_LARAVEL_API` | web env | Phase 5 slice 1: defaults **`true`**. Dual-write + attachment routing through Laravel. Set `false` or use `docker/compose.soak.yml` to opt out. |

`APP_KEY` must be a Laravel key (`base64:...`). Generate with `php artisan key:generate --show` and place the value in `docker/secrets/app_key.txt`.

Browser authentication remains on Express. Laravel `POST /api/v1/auth/token` issues Sanctum tokens for API/clients only — see [LARAVEL_MIGRATION.md](LARAVEL_MIGRATION.md).

### Object storage (web attachments)

| Variable | Default | Used by |
|----------|---------|---------|
| `S3_ENDPOINT` | `http://minio:9000` | web |
| `S3_BUCKET` | `rms-uploads` | web |
| `S3_ACCESS_KEY_ID` / `S3_SECRET_ACCESS_KEY` | (dev defaults) | web |
| `S3_USE_PATH_STYLE_ENDPOINT` | `true` | web |

## Docker secrets (not in .env)

| File | Compose secret name |
|------|---------------------|
| `docker/secrets/db_password.txt` | `db_password` |
| `docker/secrets/app_key.txt` | `app_key` |

Application containers can read via `DB_PASSWORD_FILE` and `APP_KEY_FILE` environment variables pointing to `/run/secrets/`.

## Environment-specific values

| Environment | `APP_ENV` | `APP_URL` | Notes |
|-------------|-----------|-----------|-------|
| Local dev | `local` | `http://localhost:8080` | Override + dev ports |
| Staging | `staging` | `https://rms-staging.example.com` | TLS required |
| Production | `production` | `https://rms.example.com` | prod compose, no dev profiles |

## Related

- [`.env.example`](../.env.example)
- [Docker Guide](DOCKER.md)
- [Container Security](CONTAINER_SECURITY.md)
