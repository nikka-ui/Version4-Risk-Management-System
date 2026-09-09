---
description: Clean code conventions for the RMS Laravel + Next.js stack
alwaysApply: true
---

# Coding Standards

Stack: **Laravel 11 (PHP 8.2+)** in `backend/`, optional **Next.js 14 / React 18 / TypeScript** in `frontend/`, **PostgreSQL**, Docker Compose.

## Naming

- PHP: `PascalCase` classes, `camelCase` methods/properties, `snake_case` DB columns and migration names.
- Controllers/Services: noun + role (`AdminTicketController`, `DeptTicketService`).
- Middleware: `Ensure*Role` pattern already used in `backend/app/Http/Middleware/`.
- TypeScript/React: `PascalCase` components, `camelCase` functions/vars, `kebab-case` or route-segment folders under `frontend/app/`.
- Env vars: `SCREAMING_SNAKE_CASE` (see `.env.example` / `docs/ENVIRONMENT.md`).

## Folder structure

- Backend: `app/Http/Controllers`, `app/Services`, `app/Models`, `app/Http/Middleware`, `routes/`, `database/migrations`, `tests/Unit`, `tests/Feature`.
- Frontend: App Router under `frontend/app/` (`page.tsx`, `layout.tsx`, `components/`).
- Ops/docs: `docker/`, `docs/` — do not bury app logic there.

## Imports & style

- PHP: follow PSR-4 (`App\`), prefer explicit `use` imports; run **Laravel Pint** before finishing PHP changes.
- TypeScript: prefer absolute/`@/` imports if configured; otherwise relative. Group: std/lib → third-party → local.
- Prefer Eloquent / Query Builder over ad-hoc SQL; keep controllers thin — put business logic in Services.

## Clean code

- Comments only for non-obvious intent (why), not what the code already says.
- No dead code, unused imports, or commented-out blocks left behind.
- No magic numbers/strings — use named constants, enums, config (`config/`), or `SystemSetting` where appropriate.
- Match existing patterns in neighboring files; do not introduce a second style in the same module.
