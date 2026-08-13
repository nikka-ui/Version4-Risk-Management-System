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
| `RMS_INTERNAL_SERVICE_TOKEN` | `.env` / compose | Phase 7 slice 1: Laravel→Express org mirror (`X-RMS-Service-Token`). Compose default **`rms-dev-internal`**. Empty disables the mirror. **Not used for browser login.** |
| `EXPRESS_WEB_INTERNAL_URL` | api env | Phase 7 slice 1: Express base URL for org mirror. Default `http://web:3000`. |
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
| `USE_LARAVEL_REPORTER_TICKET_FORM_UI` | web env | Phase 5 slice 13: defaults **`true`**. Express GET create/edit/preview form routes redirect to Blade. Preview save/submit + draft delete moved in Phase 7 slice 7; create/edit uploads moved in Phase 8 slice 1. |
| `USE_LARAVEL_REPORTER_TICKET_MUTATIONS` | web env | Phase 7 slice 7: defaults **`true`**. Blade preview save/submit + draft delete POSTs; Express `store.json` is mirrored via `/internal/tickets/upsert` and `/internal/tickets/delete-draft`. |
| `USE_LARAVEL_REPORTER_UPLOAD_MUTATIONS` | web env | Phase 8 slice 1–2: defaults **`true`**. Blade create/edit + evidence + accomplishment multipart POSTs; Express `store.json` is mirrored via `/internal/tickets/upsert`. |
| `USE_LARAVEL_ADMIN_DASHBOARD_UI` | web env | Phase 5 slice 14: defaults **`true`**. Express GET `/admin` redirects to Blade dashboard; management POSTs stay on Express. |
| `USE_LARAVEL_ADMIN_USERS_UI` | web env | Phase 5 slice 15: defaults **`true`**. Express GET `/admin/users` (+ edit) redirects to Blade. |
| `USE_LARAVEL_ADMIN_USER_MUTATIONS` | web env | Phase 7 slice 3: defaults **`true`**. Blade create/edit/activate/deactivate/delete/reset-password POSTs; Express `store.json` is mirrored via `/internal/org/users`. |
| `USE_LARAVEL_ADMIN_DEPARTMENTS_UI` | web env | Phase 5 slice 16: defaults **`true`**. Express GET `/admin/departments` (+ edit) redirects to Blade. |
| `USE_LARAVEL_ADMIN_DEPT_MUTATIONS` | web + api env | Phase 7 slice 1: defaults **`true`**. Blade create/edit/delete POSTs; Express `store.json` is mirrored via `/internal/org/departments`. |
| `USE_LARAVEL_ADMIN_POSITIONS_UI` | web env | Phase 5 slice 17: defaults **`true`**. Express GET `/admin/positions` (+ edit) redirects to Blade. |
| `USE_LARAVEL_ADMIN_POS_MUTATIONS` | web env | Phase 7 slice 2: defaults **`true`**. Blade create/edit/delete POSTs; Express `store.json` is mirrored via `/internal/org/positions`. |
| `USE_LARAVEL_ADMIN_TICKETS_UI` | web env | Phase 5 slice 18: defaults **`true`**. Express GET `/admin/tickets` redirects to Blade. |
| `USE_LARAVEL_ADMIN_TICKET_MUTATIONS` | web env | Phase 7 slice 5: defaults **`true`**. Blade soft-delete POST `/admin/tickets/:ref/delete`; Express `store.json` is mirrored via `/internal/tickets/soft-delete`. |
| `USE_LARAVEL_DEPT_TICKET_MUTATIONS` | web env | Phase 7 slice 6 + slice 13 + Phase 8 slice 3: defaults **`true`**. Blade dept workflow + comment + document POSTs; Express `store.json` is mirrored via `/internal/tickets/upsert`. Personnel/resolution stay Express. |
| `USE_LARAVEL_ADMIN_TICKET_DETAIL_UI` | web env | Phase 5 slice 19: defaults **`true`**. Express GET `/admin/tickets/:ref` redirects to Blade; detail view now read-only on Laravel. |
| `USE_LARAVEL_ADMIN_AUDIT_LOGS_UI` | web env | Phase 5 slice 20: defaults **`true`**. Express GET `/admin/audit-logs` redirects to Blade; export remains on Express. |
| `USE_LARAVEL_ADMIN_SETTINGS_UI` | web env | Phase 5 slice 21: defaults **`true`**. Express GET `/admin/settings` redirects to Blade. |
| `USE_LARAVEL_ADMIN_SETTINGS_MUTATIONS` | web env | Phase 7 slice 4: defaults **`true`**. Blade save/reset-landing/reset-ai POSTs; Express `store.json` is mirrored via `/internal/org/settings`. |
| `USE_LARAVEL_DEPT_DASHBOARD_UI` | web env | Phase 5 slice 22: defaults **`true`**. Express GET `/dept` redirects to Blade; queues/detail remain on Express. |
| `USE_LARAVEL_DEPT_QUEUES_UI` | web env | Phase 5 slice 23: defaults **`true`**. Express GET `/dept/{inbox,active,drafts,returned,overdue,closure,tickets}` redirects to Blade; ticket detail remains on Express. |
| `USE_LARAVEL_DEPT_TICKET_DETAIL_UI` | web env | Phase 5 slice 24: defaults **`true`**. Express GET `/dept/tickets/:ref` redirects to Blade. Workflow POSTs moved in Phase 7 slice 6. |
| `USE_LARAVEL_OFFICER_DASHBOARD_UI` | web env | Phase 5 slice 25: defaults **`true`**. Express GET `/officer` redirects to Blade; queues/detail remain on Express. |
| `USE_LARAVEL_OFFICER_QUEUES_UI` | web env | Phase 5 slice 26: defaults **`true`**. Express GET `/officer/{tickets,overdue,monitoring,action-plans}` redirects to Blade; ticket detail remains on Express. |
| `USE_LARAVEL_OFFICER_TICKET_DETAIL_UI` | web env | Phase 5 slice 27: defaults **`true`**. Express GET `/officer/tickets/:ref` redirects to Blade. Reopen moved in Phase 7 slice 8; thread-comment in Phase 7 slice 11. |
| `USE_LARAVEL_OFFICER_TICKET_MUTATIONS` | web env | Phase 7 slice 8 + slice 11: defaults **`true`**. Blade reopen + thread-comment POSTs; Express `store.json` is mirrored via `/internal/tickets/upsert`. |
| `USE_LARAVEL_OFFICER_ALIASES_UI` | web env | Phase 6 slice 5: defaults **`true`**. Express GET `/officer/{ai-review,review,final-validation}` redirect to Laravel aliases (`/officer/tickets` or `/officer/action-plans`). |
| `USE_LARAVEL_EXECUTIVE_DASHBOARD_UI` | web env | Phase 5 slice 28: defaults **`true`**. Express GET `/executive` redirects to Blade. |
| `USE_LARAVEL_EXECUTIVE_PAGES_UI` | web env | Phase 6 slice 3: defaults **`true`**. Express GET `/executive/{heatmap,reports,trends,statistics,departments,register,critical}` redirect to Blade. |
| `USE_LARAVEL_EXECUTIVE_TICKET_DETAIL_UI` | web env | Phase 6 slice 4: defaults **`true`**. Express GET `/executive/tickets/:ref` redirects to Blade. Comment moved in Phase 7 slice 10. |
| `USE_LARAVEL_EXECUTIVE_TICKET_MUTATIONS` | web env | Phase 7 slice 10: defaults **`true`**. Blade comment POST `/executive/tickets/:ref/comment`; Express `store.json` is mirrored via `/internal/tickets/upsert`. |
| `USE_LARAVEL_PRESIDENT_DASHBOARD_UI` | web env | Phase 5 slice 29: defaults **`true`**. Express GET `/president` redirects to Blade. |
| `USE_LARAVEL_PRESIDENT_QUEUES_UI` | web env | Phase 5 slice 30: defaults **`true`**. Express GET `/president/{pending,high,critical,trends}` redirect to Blade. |
| `USE_LARAVEL_PRESIDENT_TICKET_DETAIL_UI` | web env | Phase 5 slice 31: defaults **`true`**. Express GET `/president/tickets/:ref` redirects to Blade. Decision moved in Phase 7 slice 9; comment in Phase 7 slice 12. |
| `USE_LARAVEL_PRESIDENT_TICKET_MUTATIONS` | web env | Phase 7 slice 9 + slice 12: defaults **`true`**. Blade decision + comment POSTs; Express `store.json` is mirrored via `/internal/tickets/upsert`. |
| `USE_LARAVEL_EDGE_ROOT` | api + web env | Phase 6 slice 1: defaults **`true`**. Edge nginx exact `/` → Laravel; guest redirects to `/login`. Soak sets **`false`**. |
| `USE_LARAVEL_EDGE_UI` | api + web env | Phase 6 slice 2: defaults **`true`**. Unprefixed Blade GETs (`/login`, `/admin`, …) via nginx. Soak nginx omits those locations. |
| `USE_LARAVEL_DASHBOARD_UI` | web env | Phase 6 slice 6: defaults **`true`**. Express GET `/dashboard` redirects to Blade; console roles hop to `/{role}`, employees see the stub. |
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
