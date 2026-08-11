# Docker Guide

Run the RMS stack locally or in production using Docker Compose.

## Prerequisites

- Docker Engine 24+ and Docker Compose v2
- Git clone of this repository
- Ports available per [Port Registry](PORT_REGISTRY.md) (default: `8080`)

## First-time setup

### 1. Environment file

```powershell
cd C:\dev\Version4-Risk-Management-System
Copy-Item .env.example .env
```

### 2. Docker secrets

```powershell
New-Item -ItemType Directory -Force -Path docker\secrets
Copy-Item docker\secrets\db_password.txt.example docker\secrets\db_password.txt
Copy-Item docker\secrets\app_key.txt.example docker\secrets\app_key.txt
# Edit both files with strong random values before production
```

Secret files are gitignored. Compose reads them from `docker/secrets/`.

### 3. Start development stack

From repository root:

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d
```

Optional dev tools (MinIO, Mailpit):

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml --profile dev up -d
```

### 4. Verify health

```powershell
curl http://localhost:8080/health
curl http://localhost:8080/ai-health
curl http://localhost:8080/
```

Expected: JSON `{"status":"ok"}` from `/health` and `/ai-health`.

## Compose file layout

| File | Purpose |
|------|---------|
| [`docker/compose.yml`](../docker/compose.yml) | Base services, networks, volumes, secrets |
| [`docker/compose.override.yml`](../docker/compose.override.yml) | Dev: localhost DB/AI ports, optional profiles |
| [`docker/compose.soak.yml`](../docker/compose.soak.yml) | Phase 5 opt-out: forces Laravel API/auth/login/profile/reporter-profile flags off |
| [`docker/compose.prod.yml`](../docker/compose.prod.yml) | Prod: resource limits, read-only roots, 80/443 |

## Development vs production

| Mode | Command |
|------|---------|
| **Development** | `docker compose -f docker/compose.yml -f docker/compose.override.yml up -d` (dual-write ON by default) |
| **Bridge opt-out** | Add `-f docker/compose.soak.yml` to force API/auth/login-UI flags off |
| **Blade login** | Phase 5 slice 4: edge `/laravel/` → Laravel; Express `/login` redirects when `USE_LARAVEL_LOGIN_UI=true` |
| **Blade admin profile** | Phase 5 slice 5: `/admin/profile` → `/laravel/admin/profile` when `USE_LARAVEL_PROFILE_UI=true` |
| **Blade reporter profile** | Phase 5 slice 6: `/supervisor/profile` → `/laravel/supervisor/profile` when `USE_LARAVEL_REPORTER_PROFILE_UI=true` |
| **Blade reporter dashboard** | Phase 5 slice 7: `/supervisor` → `/laravel/supervisor` when `USE_LARAVEL_REPORTER_DASHBOARD_UI=true` |
| **Blade reporter ticket lists** | Phase 5 slice 8: `/supervisor/tickets` (+ drafts/submitted/returned/overdue) → Laravel when `USE_LARAVEL_REPORTER_TICKETS_UI=true` |
| **Blade reporter ticket detail** | Phase 5 slice 9: `/supervisor/tickets/:ref` → `/laravel/supervisor/tickets/:ref` when `USE_LARAVEL_REPORTER_TICKET_DETAIL_UI=true` |
| **Blade reporter notifications** | Phase 5 slice 10: `/supervisor/notifications` → `/laravel/supervisor/notifications` when `USE_LARAVEL_REPORTER_NOTIFICATIONS_UI=true` |
| **Blade reporter accomplishments** | Phase 5 slice 11: `/supervisor/accomplishments` → `/laravel/supervisor/accomplishments` when `USE_LARAVEL_REPORTER_ACCOMPLISHMENTS_UI=true` |
| **Blade reporter actions** | Phase 5 slice 12: `/supervisor/actions` → `/laravel/supervisor/actions` when `USE_LARAVEL_REPORTER_ACTIONS_UI=true` |
| **Blade reporter ticket forms** | Phase 5 slice 13: create/edit/preview GETs → `/laravel/supervisor/tickets/...` when `USE_LARAVEL_REPORTER_TICKET_FORM_UI=true` |
| **Blade admin dashboard** | Phase 5 slice 14: `GET /admin` → `/laravel/admin` when `USE_LARAVEL_ADMIN_DASHBOARD_UI=true` |
| **Blade admin users** | Phase 5 slice 15: `GET /admin/users` → `/laravel/admin/users` when `USE_LARAVEL_ADMIN_USERS_UI=true` |
| **Blade admin departments** | Phase 5 slice 16: `GET /admin/departments` → `/laravel/admin/departments` when `USE_LARAVEL_ADMIN_DEPARTMENTS_UI=true` |
| **Blade admin positions** | Phase 5 slice 17: `GET /admin/positions` → `/laravel/admin/positions` when `USE_LARAVEL_ADMIN_POSITIONS_UI=true` |
| **Blade admin tickets** | Phase 5 slice 18: `GET /admin/tickets` → `/laravel/admin/tickets` when `USE_LARAVEL_ADMIN_TICKETS_UI=true` |
| **Blade admin ticket detail** | Phase 5 slice 19: `GET /admin/tickets/:ref` → `/laravel/admin/tickets/:ref` when `USE_LARAVEL_ADMIN_TICKET_DETAIL_UI=true` |
| **Blade admin audit logs** | Phase 5 slice 20: `GET /admin/audit-logs` → `/laravel/admin/audit-logs` when `USE_LARAVEL_ADMIN_AUDIT_LOGS_UI=true` |
| **Production** | `docker compose -f docker/compose.yml -f docker/compose.prod.yml up -d` |

Production requires TLS certificates in `docker/nginx/certs/` (fullchain.pem, privkey.pem) and updated secrets.

## Service endpoints (development)

| URL | Service |
|-----|---------|
| http://localhost:8080/ | Express RMS web app (login + role consoles) |
| http://localhost:8080/login | Sign-in — see [LOGIN.md](LOGIN.md) |
| http://localhost:8080/api/ | Laravel 11 API (Phase 1 identity; routes under `/api/v1`) |
| http://localhost:8080/health | nginx health |
| http://localhost:8080/ai-health | AI health (proxied) |
| http://127.0.0.1:5433 | PostgreSQL (host only) |
| http://127.0.0.1:5001 | AI service (direct debug) |
| http://127.0.0.1:8025 | Mailpit UI (`--profile dev`) |
| http://127.0.0.1:9001 | MinIO console (`--profile dev`) |

## Common commands

```powershell
# Validate compose configuration
docker compose -f docker/compose.yml -f docker/compose.override.yml config

# View logs
docker compose -f docker/compose.yml -f docker/compose.override.yml logs -f nginx api

# Stop and remove containers
docker compose -f docker/compose.yml -f docker/compose.override.yml down

# Stop and remove volumes (destructive)
docker compose -f docker/compose.yml -f docker/compose.override.yml down -v
```

## Application images

- **web** — Express RMS application on port 3000 (tickets, RBAC consoles, sessions)
- **api** — Laravel 11 + PHP 8.3-FPM + nginx on port 8080 ([ADR 001](adr/001-backend-laravel.md)); source in `backend/`
- **ai-service** — Flask service on port 5000

### Evolving the stack

#### Laravel API (Phase 1 — identity foundation)

Application code lives in [`backend/`](../backend/). The `api` image copies it and runs `composer install`. Express still owns `/` (login + role consoles). Details: [LARAVEL_MIGRATION.md](LARAVEL_MIGRATION.md).

```powershell
# Rebuild API after backend changes
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d api

# Migrations (risk_attachments is create-if-missing; never dropped)
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan migrate --force

# Import users from mounted store.json (read-only) into Postgres
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:import-users

# Smoke checks (Express still owns /)
curl http://localhost:8080/health
curl http://localhost:8080/api/v1/health
curl http://localhost:8080/api/v1
curl http://localhost:8080/login
curl http://localhost:8080/
```

Nginx still strips `/api` before proxying: public `/api/v1` → container `/v1`. Product UI/login remain on Express (`/`).

`store.json` is mounted read-only into the API container at `/import/store.json` for import only.

#### Next.js frontend (optional future UI)

1. Scaffold Next.js 14 in `frontend/`.
2. Update `docker/web/Dockerfile` for `output: 'standalone'` build **or** introduce a separate UI service.
3. Set `NEXT_PUBLIC_API_URL` to `/api/v1`.

#### AI service

1. Expand NLP and classification in `docker/ai-service/`.
2. Expose `/classify` and `/summarize` per V2 API contract.

### Ticket data reset

See [Operations — Resetting ticket data](OPERATIONS.md#resetting-ticket-data). Scripts live under `docker/web/scripts/`.

## Troubleshooting

| Issue | Action |
|-------|--------|
| `secret file not found` | Create `docker/secrets/db_password.txt` and `app_key.txt` |
| Port 8080 in use | Set `NGINX_HTTP_PORT` in `.env` |
| `rms_data` network unreachable | Ensure `postgres` and `redis` are healthy: `docker compose ps` |
| API 502 via nginx | Wait for `api` healthcheck; check `docker compose logs api` |
| Login / app 502 after rebuild | nginx may have cached an old `web` IP. Config uses Docker DNS (`resolver 127.0.0.11`) with dynamic upstreams in `docker/nginx/conf.d/rms.conf`. Reload or restart nginx: `docker restart rms-nginx` |

## Related

- [Port Registry](PORT_REGISTRY.md)
- [Container Security](CONTAINER_SECURITY.md)
- [Environment Variables](ENVIRONMENT.md)
