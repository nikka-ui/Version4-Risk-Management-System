# Hardened staging (controlled use)

This is the **staging / controlled-use** mode for the Risk Management System: security and governance controls are on, but it still runs on local Docker ports for internal UAT—not public internet production.

## What “hardened staging” means

| Control | Staging setting |
|---------|-----------------|
| AI auto-route | **Off** — tickets go to `pending_ai_review` until RMO approves |
| APP_DEBUG | **false** |
| Dual-write to store.json | **Off** |
| Internal service token | Empty unless you set one |
| Sanctum token TTL | 12 hours |
| Workflow email | On → [Mailpit](http://127.0.0.1:8025) |
| SLA cron | `rms-scheduler` runs `schedule:work` (hourly escalate) |
| Compliance role | Seeded via `DemoUserSeeder` / import |

Not included: TLS termination, public DNS, Postgres RLS, full schema normalization.

## Start

From repo root (PowerShell):

```powershell
docker compose -f docker/compose.yml -f docker/compose.staging.yml --env-file .env.staging up -d --build
```

Optional Next.js `/app` UI:

```powershell
docker compose -f docker/compose.yml -f docker/compose.staging.yml --env-file .env.staging --profile frontend up -d --build
```

**Do not** also pass `compose.override.yml` — that file turns AI auto-route back on for local dev.

## Seed / rotate staging passwords

1. Copy the example env and set your own password (do not commit `.env.staging`):

```powershell
copy .env.staging.example .env.staging
# Edit .env.staging → set RMS_SEED_PASSWORD=...
```

2. Apply to demo users:

```powershell
$pw = (Get-Content .env.staging | Where-Object { $_ -match '^RMS_SEED_PASSWORD=' }) -replace '^RMS_SEED_PASSWORD=',''
docker compose -f docker/compose.yml -f docker/compose.staging.yml --env-file .env.staging exec -T -e "RMS_SEED_PASSWORD=$pw" api php artisan db:seed --class=DemoUserSeeder --force
```

Or merge missing built-ins:

```powershell
docker compose -f docker/compose.yml -f docker/compose.staging.yml --env-file .env.staging exec -T -e "RMS_SEED_PASSWORD=$pw" api php artisan rms:import-users
```

Until you set `RMS_SEED_PASSWORD`, existing demo users keep their current hashes (see `docs/LOGIN.md`).
## URLs

| Surface | URL |
|---------|-----|
| Login | http://localhost:8080/login |
| Health | http://localhost:8080/health |
| Mailpit | http://127.0.0.1:8025 |
| MinIO console | http://127.0.0.1:9001 |
| Next `/app` (if profile on) | http://localhost:8080/app |

## Controlled-use smoke checklist

1. Login as `reporter` → create ticket with real evidence → submit → status **`pending_ai_review`**
2. Login as `rmo` → approve AI routing → status **`assigned`**
3. Login as `dephead` → accept → action plan (Low/Mod path) or High/Critical → President
4. Accomplishment + evidence → H/C goes **`under_audit`** → `compliance` validates → President final
5. Confirm reporter cannot `GET /api/v1/tickets` for another user’s tickets
6. Check Mailpit for workflow emails when events fire
7. `php artisan rms:escalate-overdue --dry-run` inside `api` (or wait for scheduler)

## Stop / return to local dev

```powershell
docker compose -f docker/compose.yml -f docker/compose.staging.yml --env-file .env.staging down
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d
```

## Production

Use `docker/compose.prod.yml` (HTTPS, resource limits, `APP_ENV=production`) plus real secrets—not this staging overlay alone.
