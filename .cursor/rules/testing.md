---
description: Testing requirements — PHPUnit for Laravel; cover new code and edge cases
alwaysApply: true
---

# Testing

## Framework

- **Backend (required):** [PHPUnit 11](https://phpunit.de/) via Laravel — `cd backend && php artisan test` (or `composer test`).
- Suites: `backend/tests/Unit` (isolated units) and `backend/tests/Feature` (HTTP, auth, DB, workflows).
- Tests run against **SQLite in-memory** (`phpunit.xml`); production/dev DB is **PostgreSQL**.
- **Frontend:** no Jest/Vitest suite is configured yet. If you add frontend logic, introduce **Vitest** (or React Testing Library) and colocate tests; until then, prefer covering API contracts in Laravel Feature tests.

## Requirements

- Every new function, service method, controller action, or module must include a corresponding unit and/or feature test.
- Prefer Feature tests for HTTP/API/role workflows; Unit tests for pure services/helpers.
- Cover **edge cases**, not only the happy path: auth failures, validation errors, empty input, forbidden roles, missing records, idempotency.
- Do **not** mark a task complete until relevant tests pass locally (`php artisan test` for touched areas; full suite before merge/deploy).
- Mirror naming: `FooService` → `tests/Unit/FooServiceTest.php` or Feature coverage under `tests/Feature/`.

## Patterns

- Extend `Tests\TestCase`; use factories/seeders and existing Feature test patterns in `backend/tests/Feature/`.
- Assert status codes, JSON shape, DB side effects, and authorization boundaries.
- Do not rely on live external AI/S3 services in unit/feature tests — fake/mock (`Http::fake`, storage fakes, env stubs in `phpunit.xml`).
