# Laravel Migration Notes

Incremental cutover from Express (`docker/web`) to Laravel (`backend/`). **Express remains the entry for form POST/uploads and most admin management pages.**

## Phase 0–5 slice 14 (complete)

APIs, dual-write, Blade login, profiles, full Ticket Reporter GET console, admin dashboard.

## Phase 5 slice 15 (Admin users — current)

| Piece | Notes |
|-------|--------|
| `GET /laravel/admin/users` | User list + filters + create form |
| `GET /laravel/admin/users/{username}/edit` | Edit form on same page |
| `USE_LARAVEL_ADMIN_USERS_UI` | Compose default **`true`** |
| Create/edit/activate/deactivate/delete/reset POSTs | Still Express; success redirects back to Blade |
| Reset-password page GET | Still Express |
| Health | `phase: 5`, `slice: 15` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice5-admin-users
```

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d web
```

## Remaining

1. Other admin Blade pages (departments, positions, tickets, audit, settings), then dept/officer/executive/president consoles
2. nginx `/` → Laravel (Phase 6)

## Related

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DOCKER.md](DOCKER.md)
- [ENVIRONMENT.md](ENVIRONMENT.md)
- [LOGIN.md](LOGIN.md)
- [ADR 001](adr/001-backend-laravel.md)
