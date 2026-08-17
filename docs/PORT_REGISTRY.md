# Port Registry

Authoritative port assignments for the AI Risk Management System (RMS) Docker stack. All host bindings must match [`docker/compose.yml`](../docker/compose.yml) and override files.

## Principles

1. **Single public entry** — Only `nginx` exposes HTTP/HTTPS to the host in production.
2. **Data services are internal** — PostgreSQL and Redis are not published to `0.0.0.0`.
3. **Dev overrides use localhost** — When a data service must be reachable from the host (debugging), bind to `127.0.0.1` only.
4. **Document before change** — Update this file and run `docker compose config` before changing any `ports:` mapping.

## Reserved port ranges

| Range | Purpose |
|-------|---------|
| 3000–3099 | Frontend applications (Next.js, etc.) |
| 5000–5099 | AI / ML HTTP services |
| 8000–8099 | Reverse proxy and public HTTP APIs |
| 5400–5499 | PostgreSQL host overrides (development only) |
| 6379 | Redis (container port only; never publish to `0.0.0.0`) |
| 9000–9099 | Object storage (MinIO dev) |

## Service port map

| Service | Container port(s) | Host (development) | Host (production) | Docker network(s) | Published to internet |
|---------|-------------------|--------------------|-------------------|-------------------|----------------------|
| `nginx` | 80, 443 | `8080` → 80 | `80`, `443` | `rms_edge`, `rms_app` | Yes (edge only) |
| `api` | 8080 (internal nginx) | — | — | `rms_app` | No |
| `api-php` (php-fpm) | 9000 | — | — | `rms_app` | No (localhost only) |
| `ai-service` | 5000 | `127.0.0.1:5001` (profile `ai-debug`) | — | `rms_app` | No |
| `postgres` | 5432 | `127.0.0.1:5433` (profile `dev-db`) | — | `rms_data` | No |
| `redis` | 6379 | — | — | `rms_data` | No |
| `minio` | 9000 (API), 9001 (console) | `127.0.0.1:9000`, `127.0.0.1:9001` (profile `dev`) | — | `rms_data` | No |
| `mailpit` | 1025 (SMTP), 8025 (UI) | `127.0.0.1:8025` (profile `dev`) | — | `rms_app` | No |

## URL routing (via nginx)

| Path | Upstream | Notes |
|------|----------|-------|
| `/` (exact) | `api:8080` | Phase 6 slice 1: Laravel home redirect (login / role console) |
| `/login` | `api:8080` | Phase 6 slice 2: Blade Sign In (GET+POST) |
| `/dashboard` (GET) | `api:8080` | Phase 6 slice 6: Employee stub; other roles redirect to their console |
| `/admin/departments*` (GET+POST) | `api:8080` | Phase 7 slice 1: Blade department list + create/edit/delete |
| `/admin/positions*` (GET+POST) | `api:8080` | Phase 7 slice 2: Blade position list + create/edit/delete |
| `/admin/users*` (GET+POST) | `api:8080` | Phase 7 slice 3: Blade user list + create/edit/status/reset/delete |
| `/admin/settings*` (GET+POST) | `api:8080` | Phase 7 slice 4: Blade settings save + reset-landing + reset-ai |
| `/admin/tickets/:ref/delete` (POST) | `api:8080` | Phase 7 slice 5: Blade admin ticket soft-delete |
| `/dept/tickets/:ref/{accept,reject,return,reassign,action-plan,close,comment,documents,personnel,resolution,comment/edit,comment/react}` (POST) | `api:8080` | Phase 7 slice 6 + slice 13 + Phase 8 slice 3–5: Blade dept workflow + comment + documents + personnel/resolution + comment edit/react |
| `/supervisor/tickets/new/preview/:ref/{save,submit}` + `/supervisor/tickets/:ref/{delete,comment,comment/edit,comment/react}` (POST) | `api:8080` | Phase 7 slice 7 + Phase 8 slice 5: Blade reporter preview save/submit + draft delete + comment add/edit/react |
| `/supervisor/tickets/new/preview` + `/supervisor/tickets/:ref/edit` (POST) | `api:8080` | Phase 8 slice 1: Blade reporter create/edit multipart |
| `/supervisor/tickets/:ref/{evidence,accomplishment}` (POST) | `api:8080` | Phase 8 slice 2: Blade reporter evidence + accomplishment |
| `/logout` (GET+POST) | `api:8080` | Phase 9 slice 3: Blade session logout |
| `/auth/bridge` (GET) | `api:8080` | Phase 9 slice 2: Blade login handoff |
| `/css/`, `/img/` | `api:8080` | Phase 9 slice 1: Blade static CSS and images |
| `/internal/tickets/` | `api:8080` | Optional ticket dual-write to `store.json` (off by default, Phase 10 slice 3) |
| `/internal/` | `api:8080` | Optional org dual-write to `store.json` (off by default, Phase 10 slice 3) |
| `/{supervisor,dept,officer,executive,president}/attachments/:id` (GET) | `api:8080` | Phase 8 slice 9: Blade role attachment download |
| `/{dept,officer,executive,president}/notifications/open/:id` (GET) | `api:8080` | Phase 8 slice 8: Blade mark-read + ticket/home redirect |
| `/admin/tickets/:ref/reclassify` (POST) | `api:8080` | Phase 11 slice 6: Blade admin re-run AI classify |
| `/admin/ai-analysis` (GET) | `api:8080` | Phase 11 slice 3: Blade admin AI classify history |
| `/admin/audit-logs/export` (GET) | `api:8080` | Phase 8 slice 7: Blade admin audit CSV |
| `/{dept,officer,executive,president}/notifications/read-all` (POST) | `api:8080` | Phase 8 slice 6: Blade mark-all-read for other consoles |
| `/officer/tickets/:ref/reopen` (POST) | `api:8080` | Phase 7 slice 8: Blade RMO reopen + reassign |
| `/officer/tickets/:ref/thread-comment` (POST) | `api:8080` | Phase 7 slice 11: Blade RMO governance thread comment |
| `/president/tickets/:ref/decision` (POST) | `api:8080` | Phase 7 slice 9: Blade president action-plan + final decision |
| `/president/tickets/:ref/comment` (POST) | `api:8080` | Phase 7 slice 12: Blade president thread comment |
| `/executive/tickets/:ref/comment` (POST) | `api:8080` | Phase 7 slice 10: Blade executive thread comment |
| `/admin`, `/supervisor`, `/dept`, `/officer`, `/president` (GET) | `api:8080` | Phase 6 slice 2: Blade consoles |
| `/executive` (GET) | `api:8080` | Phase 6 slice 4: Blade executive dashboard + oversight + ticket detail; comment POST is Laravel |
| `/` (other paths) | `api:8080` | Phase 9 slice 4: unmatched fallback (favicon, 404s); `/internal/` is Laravel via blade-roots |
| `/laravel/` | `api:8080` | Blade UI compatibility rewrite |
| `/api/` | `api:8080` | Laravel 11; rewrite strips `/api` so app routes are `/v1/...` |
| `/health` | nginx local | Stack health check |
| `/ai-health` | `ai-service:5000/health` | Phase 11 slice 5: NLP hybrid classify (`mode: nlp-hybrid`) |

## Environment-driven URLs

Containers communicate using **service names**, not `localhost`:

| Variable | Default (compose) | Consumer |
|----------|-------------------|----------|
| `APP_URL` | `http://localhost:8080` | API, frontend (browser-facing) |
| `DB_HOST` | `postgres` | API, AI service |
| `DB_PORT` | `5432` | API, AI service |
| `REDIS_HOST` | `redis` | API |
| `REDIS_PORT` | `6379` | API |
| `AI_SERVICE_URL` | `http://ai-service:5000` | API |
| `S3_ENDPOINT` | `http://minio:9000` | API (dev profile only) |

## Changing ports

1. Edit compose file(s) under `docker/`.
2. Update this registry and [`ENVIRONMENT.md`](ENVIRONMENT.md).
3. Update [`DOCKER.md`](DOCKER.md) quick-start examples.
4. Run: `docker compose -f docker/compose.yml config`

## Firewall guidance (production host)

- Allow inbound: **80**, **443** (nginx only).
- Deny inbound: 5432, 6379, 5000, 3000, 9000 from non-admin networks.
- Restrict SSH/admin access separately from application ports.
