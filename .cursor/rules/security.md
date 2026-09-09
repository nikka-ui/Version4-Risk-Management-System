---
description: Security standards — secrets, input validation, SQL, least privilege
alwaysApply: true
---

# Security

## Secrets & credentials

- **Never** hardcode secrets, API keys, tokens, DB passwords, or `APP_KEY` in source.
- Use environment variables and Docker secrets (`docker/secrets/*.txt`); document new vars in `.env.example` and `docs/ENVIRONMENT.md`.
- Never commit `.env`, `.env.staging`, or real secret files (see `.gitignore` / README security notice).
- Seed/login examples in docs are **dev only** — rotate before shared or production deploy.

## Input validation & sanitization

- Validate all user/API input (Laravel Form Requests or `$request->validate(...)`).
- Sanitize/escape output in Blade; do not render untrusted HTML as raw without a deliberate, documented exception.
- Enforce file upload constraints (type, size) for attachments; store via configured disk (S3/MinIO), not ad-hoc paths.

## Database access

- Use **Eloquent** or the **Query Builder** with bindings — **no** raw SQL built with string concatenation.
- If `DB::raw` / `whereRaw` is unavoidable, bind parameters; never interpolate request data into SQL strings.

## Auth & least privilege

- Auth: **Laravel Sanctum** and existing role middleware (`Ensure*Role`, governance/admin/dept/president, etc.).
- Check authorization on every mutating route; do not trust client-supplied roles or IDs alone.
- Internal service routes must require the configured internal token (`RMS_INTERNAL_SERVICE_TOKEN` / related env) — never leave them open.
- Follow least privilege: grant only the minimum role/ability needed for the action.
- Prefer parameterized config over embedding credentials in Dockerfiles or CI logs.
