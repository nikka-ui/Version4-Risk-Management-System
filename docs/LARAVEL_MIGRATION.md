# Laravel Migration Notes

Incremental cutover from Express (`docker/web`) to Laravel (`backend/`). **Express remains the entry for form POST/uploads and most admin management pages.**

## Phase 0–5 slice 13 (complete)

APIs, dual-write, Blade login, profiles, full Ticket Reporter GET console including create/edit/preview forms.

## Phase 5 slice 14 (Admin dashboard — current)

| Piece | Notes |
|-------|--------|
| `GET /laravel/admin` | System administrator overview (KPIs, recent users, deleted tickets) |
| `USE_LARAVEL_ADMIN_DASHBOARD_UI` | Compose default **`true`** |
| Audit log table on dashboard | Empty until audit mirror lands; link to Express `/admin/audit-logs` |
| User/dept/ticket management POSTs | Still Express |
| Health | `phase: 5`, `slice: 14` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-admin-dashboard
```

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d web
```

## Remaining

1. Other admin Blade pages, then dept/officer/executive/president consoles
2. nginx `/` → Laravel (Phase 6)

## Related

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DOCKER.md](DOCKER.md)
- [ENVIRONMENT.md](ENVIRONMENT.md)
- [LOGIN.md](LOGIN.md)
- [ADR 001](adr/001-backend-laravel.md)
