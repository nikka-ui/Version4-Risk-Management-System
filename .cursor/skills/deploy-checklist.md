---
name: deploy-checklist
description: Pre-deployment checklist for the RMS Docker/Laravel stack — lint, tests, secrets, env vars, migrations, and rollback. Use when preparing a release, deploying, or verifying production/staging readiness.
---

# Deploy Checklist

Complete before deploying Version 4 RMS (Docker + Laravel API/UI, PostgreSQL, optional Next.js profile).

## Pre-deploy

Copy and track progress:

```
Deploy progress:
- [ ] 1. Lint / static checks
- [ ] 2. Full test suite
- [ ] 3. Secrets scan
- [ ] 4. Environment variables
- [ ] 5. Database migrations
- [ ] 6. Rollback plan documented
- [ ] 7. Post-deploy smoke checks
```

### 1. Lint / static checks

- Backend PHP: `cd backend && vendor/bin/pint --test` (or run Pint and commit only intentional style fixes).
- Frontend (if shipping the Next profile): `cd frontend && npm run lint` and `npm run build`.
- Confirm Docker images build: compose build for target environment (`docs/DOCKER.md`).

### 2. Full test suite

```powershell
cd backend
php artisan test
```

- Do not deploy if tests fail. Investigate with the debugging playbook skill.

### 3. Exposed secrets

- Confirm `.env`, `.env.staging`, and `docker/secrets/*.txt` are **not** in the release commit.
- No hardcoded API keys, DB passwords, or `APP_KEY` in images, compose overrides, or CI logs.
- Rotate any secret that may have been exposed; use example templates only in git.

### 4. Environment variables

- Verify target env has all required vars from `.env.example` / `docs/ENVIRONMENT.md`.
- Especially: `APP_KEY`, `APP_URL`, DB_*, Redis, `AI_SERVICE_URL`, Sanctum/session settings, S3/MinIO if used, `RMS_INTERNAL_SERVICE_TOKEN`.
- `APP_DEBUG=false` and strong secrets for staging/production.
- Docker secrets files present on the host (`db_password.txt`, `app_key.txt`, etc.).

### 5. Database migrations

- Review pending migrations: `php artisan migrate:status` (in the API container/context).
- Apply with a maintained window: `php artisan migrate --force` only on the target env after backup.
- Prefer backward-compatible migrations; avoid destructive column drops without a expand/contract plan.
- PostgreSQL backup taken before migrate (see `docs/OPERATIONS.md`).

### 6. Rollback plan

Document before cutover:

- **App:** previous image/tag or git SHA to redeploy; `docker compose` pull/up previous revision.
- **DB:** restore from pre-migrate backup if migration is unsafe to reverse; only run `migrate:rollback` when rollbacks are tested and non-destructive.
- **Config:** prior `.env`/secrets snapshot (stored securely, not in git).
- **Traffic:** how to drain or point nginx back; health endpoints (`/health`, API checks).

### 7. Post-deploy smoke

- `GET /health` (and API health as documented).
- Login for one account per critical role; submit or view a ticket path appropriate to the release.
- Check logs for boot errors (api, nginx, postgres, ai-service).

## References

- `docs/DOCKER.md`, `docs/OPERATIONS.md`, `docs/ENVIRONMENT.md`, `docs/CONTAINER_SECURITY.md`
