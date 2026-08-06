# Laravel Migration Notes

Incremental cutover from Express (`docker/web`) to Laravel (`backend/`). **Express remains the browser entry for login, roles, and all modules until a later phase.**

## Phase 0 (complete)

- Laravel 11 scaffold in `backend/`
- `docker/api` builds from `backend/`
- nginx: `/` → Express; `/api/` strips prefix → Laravel `/v1/...`
- Sanctum installed; `risk_attachments` safe migration (create-if-missing, never drop)

## Phase 1 (complete)

| Piece | Notes |
|-------|--------|
| Users + `rms:import-users` | Idempotent; store.json unchanged |
| Sanctum `POST /api/v1/auth/token`, `GET /api/v1/users/me` | API only — not browser login |
| `USE_LARAVEL_AUTH` | Default OFF, unused |

## Phase 2 (complete)

| Piece | Notes |
|-------|--------|
| Departments / positions + `rms:import-org` | Idempotent; store.json unchanged |
| Read + admin write org APIs | Admin UI still on Express |
| `USE_LARAVEL_ORG` | Default OFF, unused |

## Phase 3 slice 1 (ticket foundation — current)

**What exists**

| Piece | Location | Notes |
|-------|----------|--------|
| `risk_tickets` table | hybrid scalars + JSON columns | Mirrors Express `store.riskTickets` |
| `accomplishments` table | sibling of tickets by `ticket_ref` | Mirrors Express `store.accomplishments` |
| Import | `php artisan rms:import-tickets` | Idempotent upsert by `reference` / `external_id`; does **not** modify store.json |
| List tickets | `GET /api/v1/tickets` | Sanctum; filters: `status`, `department`, `submittedBy`, `search`, `include_deleted`, `limit` |
| Show ticket | `GET /api/v1/tickets/{reference}` | Full Express-shaped payload |
| Accomplishment | `GET /api/v1/tickets/{reference}/accomplishment` | Optional convenience |
| Flag | `USE_LARAVEL_API` | Default OFF — Express ticket UI/workflow untouched |

**What did NOT change**

- Express ticket create/submit/assign/close/reopen and all role consoles
- `RISK-{YEAR}-{#####}` generation still owned by Express
- Attachments still Express + MinIO + `risk_attachments`
- `store.json` remains live source of truth for tickets
- No workflow write APIs in Laravel yet

### Migrate + import + verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d api

docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan migrate --force
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:import-users
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:import-org
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:import-tickets

$body = @{ username = "admin"; password = "<from-store>" } | ConvertTo-Json
$tok = Invoke-RestMethod -Method POST -Uri http://localhost:8080/api/v1/auth/token -ContentType application/json -Body $body
Invoke-RestMethod -Uri http://localhost:8080/api/v1/tickets -Headers @{ Authorization = "Bearer $($tok.token)" }
Invoke-RestMethod -Uri http://localhost:8080/api/v1/health

# Express still owns the UI
Invoke-WebRequest http://localhost:8080/login -UseBasicParsing | Select-Object StatusCode
Invoke-WebRequest http://localhost:8080/supervisor -UseBasicParsing | Select-Object StatusCode
```

## Phase 3 later slices (not started)

1. Draft create/update write APIs behind `USE_LARAVEL_API`
2. Express adapter for one workflow slice at a time
3. Status transitions, notifications, attachment API ownership
4. Only then consider browser cutover

## Related

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DOCKER.md](DOCKER.md)
- [ENVIRONMENT.md](ENVIRONMENT.md)
- [ADR 001](adr/001-backend-laravel.md)
