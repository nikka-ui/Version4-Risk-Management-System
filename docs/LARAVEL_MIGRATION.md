# Laravel Migration Notes

Incremental cutover from Express (`docker/web`) to Laravel (`backend/`). **Express remains the entry for form POST/uploads and remaining admin pages.**

## Phase 0–5 slice 17 (complete)

APIs, dual-write, Blade login, profiles, full Ticket Reporter GET console, admin dashboard + users + departments + positions.

## Phase 5 slice 18 (Admin tickets list — complete)

| Piece | Notes |
|-------|--------|
| `GET /laravel/admin/tickets` | Ticket list with search/filters + soft-delete dialog |
| `USE_LARAVEL_ADMIN_TICKETS_UI` | Compose default **`true`** |
| Delete POST | Still Express (`POST /admin/tickets/:ref/delete`); success redirects back to Blade list |
| Ticket detail | Still Express (`GET /admin/tickets/:ref`) — read-only view |
| Health | `phase: 5`, `slice: 18` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-admin-tickets
```

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d web
```

## Phase 5 slice 19 (Admin ticket detail — complete)

| Piece | Notes |
|-------|--------|
| `GET /laravel/admin/tickets/:ref` | Ticket detail read-only view |
| `USE_LARAVEL_ADMIN_TICKET_DETAIL_UI` | Compose default **`true`** |
| Express redirect | `GET /admin/tickets/:ref` redirects to Blade when enabled |
| Health | `phase: 5`, `slice: 19` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-admin-tickets-detail
```

## Phase 5 slice 20 (Admin audit logs — current)

| Piece | Notes |
|-------|--------|
| `GET /laravel/admin/audit-logs` | Audit log list (search/filter + details dialog) |
| `USE_LARAVEL_ADMIN_AUDIT_LOGS_UI` | Compose default **`true`** |
| Express redirect | `GET /admin/audit-logs` redirects to Blade when enabled |
| Export CSV | Still served from Express (`/admin/audit-logs/export`) for now |
| Health | `phase: 5`, `slice: 20` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-admin-audit-logs
```

## Remaining

1. Admin settings console, then dept/officer/executive/president consoles
2. nginx `/` → Laravel (Phase 6)

## Related

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DOCKER.md](DOCKER.md)
- [ENVIRONMENT.md](ENVIRONMENT.md)
- [LOGIN.md](LOGIN.md)
- [ADR 001](adr/001-backend-laravel.md)
