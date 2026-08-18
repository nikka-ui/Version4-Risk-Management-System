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
| `AI_SERVICE_URL` | `.env` / compose | Laravel → Flask base URL (default `http://ai-service:5000`); classify uses CPU transformer hybrid over taxonomy + TF-IDF (Phase 13 slice 1) |
| `AI_SERVICE_TIMEOUT` | `.env` / compose | Seconds for classify/summarize HTTP (default `3`); on failure Laravel uses PHP stub |

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
| `APP_KEY` | `docker/secrets/app_key.txt` | Laravel `api` (via `APP_KEY_FILE`) |
| `SANCTUM_STATEFUL_DOMAINS` | `.env` | Laravel Sanctum stateful hosts — e.g. `localhost:8080` |
| `STORE_JSON_PATH` | compose / `.env` | Laravel **import** path (default `/import/store.json` in Docker). Not a live SoT. |
| `RMS_INTERNAL_SERVICE_TOKEN` | `.env` / compose | Shared secret for optional Laravel `/internal/*` mirror routes (`X-RMS-Service-Token`). Compose default **`rms-dev-internal`**. **Not used for browser login.** |
| `USE_LARAVEL_EDGE_ROOT` | api env | Phase 6 slice 1: defaults **`true`**. Edge nginx exact `/` → Laravel; guest redirects to `/login`. |
| `USE_LARAVEL_EDGE_UI` | api env | Phase 6 slice 2+: defaults **`true`**. Unprefixed Blade GETs via nginx; unmatched `location /` → Laravel. |
| `USE_LARAVEL_INTERNAL_TICKETS` | api env | Phase 10 slice 3: defaults **`false`**. When **`true`**, Laravel dual-writes tickets to `store.json`. |
| `USE_LARAVEL_INTERNAL_ORG` | api env | Phase 10 slice 3: defaults **`false`**. When **`true`**, Laravel dual-writes org/settings/users to `store.json`. |

Obsolete Express cutover flags (`USE_LARAVEL_*_UI`, `USE_LARAVEL_*_MUTATIONS`, `USE_LARAVEL_API`, `USE_LARAVEL_AUTH`, …) are removed from `.env.example`; they are unused on the Laravel-only stack.

`APP_KEY` must be a Laravel key (`base64:...`). Generate with `php artisan key:generate --show` and place the value in `docker/secrets/app_key.txt`.

Browser authentication is Laravel Blade at `/login`. Laravel `POST /api/v1/auth/token` issues Sanctum tokens for API/clients — see [LARAVEL_MIGRATION.md](LARAVEL_MIGRATION.md).

### Object storage (attachments)

| Variable | Default | Used by |
|----------|---------|---------|
| `S3_ENDPOINT` | `http://minio:9000` | api |
| `S3_BUCKET` | `rms-uploads` | api |
| `S3_ACCESS_KEY_ID` / `S3_SECRET_ACCESS_KEY` | (dev defaults) | api |
| `S3_USE_PATH_STYLE_ENDPOINT` | `true` | api |

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
