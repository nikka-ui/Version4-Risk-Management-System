---
name: debugging-playbook
description: Step-by-step debugging for the RMS Laravel/Next.js stack — reproduce, isolate, write a failing test, fix, and verify. Use when investigating bugs, test failures, 500s, workflow errors, or unexpected API/UI behavior.
---

# Debugging Playbook

Use this process for bugs in the AI Risk Management System (Laravel backend primary; Next.js frontend optional).

## 1. Reproduce

- Capture exact steps, role (Reporter / Dept Head / RMO / President / Executive / Admin), URL/route, and request payload.
- Note environment: local Docker (`docs/DOCKER.md`), `APP_ENV`, and whether AI/MinIO/mail are required.
- Reproduce consistently before changing code. If intermittent, record frequency and any race (queues, concurrent edits).

## 2. Check logs and error messages

- Browser network tab + response body for API/Blade errors.
- Laravel logs: `backend/storage/logs/`, `php artisan pail`, or container logs (`docker compose ... logs api`).
- Nginx/edge and AI service logs if the failure is upstream of Laravel.
- Read the exception class, message, and stack frame in `app/` — ignore vendor noise until the app frame is clear.

## 3. Isolate the failing component

- Narrow: route → middleware (role) → controller → service → model/DB → external (AI, S3, mail).
- Confirm auth/role first (many “bugs” are `403`/`302` from `Ensure*Role`).
- Prefer a minimal reproduction (single Feature test or one `curl`/HTTP call) over clicking the full UI.

## 4. Write a failing test before fixing

- Add or extend a test under `backend/tests/Feature` or `tests/Unit` that **fails for the bug**.
- Cover the edge case (wrong role, missing attachment, null field, stale state), not only the happy path.
- Run: `cd backend && php artisan test --filter=YourTestName`.

## 5. Fix and verify

- Implement the smallest fix that makes the new test pass.
- Re-run the new test, then related suite areas (or full `php artisan test`).
- Confirm no regressions in neighboring workflows (submit ticket, dept/officer/president mutations, auth).
- Do not claim fixed until the reproduction steps pass and tests are green.

## Quick commands

```powershell
cd backend
php artisan test --filter=NameOfTest
php artisan pail
```

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml logs -f api
```
