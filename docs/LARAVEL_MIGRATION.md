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

## Phase 5 slice 20 (Admin audit logs — complete)

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

## Phase 5 slice 21 (Admin system settings — complete)

| Piece | Notes |
|-------|--------|
| `GET /laravel/admin/settings` | System settings form (landing, AI, security, backup) |
| `USE_LARAVEL_ADMIN_SETTINGS_UI` | Compose default **`true`** |
| Express redirect | `GET /admin/settings` redirects to Blade when enabled |
| Save / reset POSTs | Still Express (`POST /admin/settings*`); success redirects back to Blade |
| Health | `phase: 5`, `slice: 21` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-admin-settings
```

## Phase 5 slice 22 (Department Head dashboard — complete)

| Piece | Notes |
|-------|--------|
| `GET /laravel/dept` | Department Head overview (KPIs + recent tickets) |
| `USE_LARAVEL_DEPT_DASHBOARD_UI` | Compose default **`true`** |
| Express redirect | `GET /dept` redirects to Blade when enabled |
| Queues / detail | Still Express (`/dept/inbox`, `/dept/tickets/:ref`, …) |
| Health | `phase: 5`, `slice: 22` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-dept-dashboard
```

## Phase 5 slice 23 (Department Head queues — complete)

| Piece | Notes |
|-------|--------|
| `GET /laravel/dept/{inbox,active,drafts,returned,overdue,closure,tickets}` | Queue lists from Postgres |
| `USE_LARAVEL_DEPT_QUEUES_UI` | Compose default **`true`** |
| Express redirect | Matching Express GETs redirect to Blade when enabled |
| Ticket detail | Still Express (`GET /dept/tickets/:ref`) — mutations stay Express |
| Health | `phase: 5`, `slice: 23` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-dept-queues
```

## Phase 5 slice 24 (Department Head ticket detail)

| Piece | Notes |
|-------|--------|
| `GET /laravel/dept/tickets/:ref` | Detail view with ownership / action-plan / close forms |
| `USE_LARAVEL_DEPT_TICKET_DETAIL_UI` | Compose default **`true`** |
| Express redirect | `GET /dept/tickets/:ref` redirects to Blade when enabled |
| Mutations | Still Express POSTs; success redirects back to Blade |
| Health | `phase: 5`, `slice: 24` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-dept-ticket-detail
```

## Phase 5 slice 25 (Risk Management Officer dashboard)

| Piece | Notes |
|-------|--------|
| `GET /laravel/officer` | RMO governance dashboard (stats, departments, 5×5 matrix) |
| `USE_LARAVEL_OFFICER_DASHBOARD_UI` | Compose default **`true`** |
| Express redirect | `GET /officer` redirects to Blade when enabled |
| Queues / detail | Still Express (`/officer/tickets`, overdue, monitoring, action-plans) |
| Health | `phase: 5`, `slice: 25` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-officer-dashboard
```

## Phase 5 slice 26 (Risk Management Officer queues)

| Piece | Notes |
|-------|--------|
| `GET /laravel/officer/{tickets,overdue,monitoring,action-plans}` | Queue list views |
| `USE_LARAVEL_OFFICER_QUEUES_UI` | Compose default **`true`** |
| Express redirect | Matching Express GETs redirect to Blade when enabled |
| Ticket detail | Still Express (`GET /officer/tickets/:ref`) — mutations stay Express |
| Health | `phase: 5`, `slice: 26` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-officer-queues
```

## Phase 5 slice 27 (Risk Management Officer ticket detail — current)

| Piece | Notes |
|-------|--------|
| `GET /laravel/officer/tickets/:ref` | Governance detail (read-only + reopen + discussion) |
| `USE_LARAVEL_OFFICER_TICKET_DETAIL_UI` | Compose default **`true`** |
| Express redirect | `GET /officer/tickets/:ref` redirects to Blade when enabled |
| Mutations | Thread-comment / reopen POSTs stay on Express; success redirects back to Blade |
| Health | `phase: 5`, `slice: 27` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-officer-ticket-detail
```

## Phase 5 slice 31 (President ticket detail — current)

| Piece | Notes |
|-------|--------|
| `GET /laravel/president/tickets/:ref` | Ticket detail with action-plan / final decision UI |
| `USE_LARAVEL_PRESIDENT_TICKET_DETAIL_UI` | Compose default **`true`** |
| Express redirect | `GET /president/tickets/:ref` redirects to Blade when enabled |
| Mutations | Decision / comment POSTs stay on Express; success redirects back to Blade |
| Health | `phase: 5`, `slice: 31` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-president-ticket-detail
```

## Phase 5 slice 30 (President queues)

| Piece | Notes |
|-------|--------|
| `GET /laravel/president/{pending,high,critical,trends}` | Queue lists + monthly trends chart |
| `USE_LARAVEL_PRESIDENT_QUEUES_UI` | Compose default **`true`** |
| Express redirect | `/president/{pending,high,critical,trends}` redirect to Blade when enabled |
| Health | `phase: 5`, `slice: 30` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-president-queues
```

## Remaining
1. nginx `/` → Laravel (Phase 6)

## Related

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DOCKER.md](DOCKER.md)
- [ENVIRONMENT.md](ENVIRONMENT.md)
- [LOGIN.md](LOGIN.md)
- [ADR 001](adr/001-backend-laravel.md)
