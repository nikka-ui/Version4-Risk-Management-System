# Laravel Migration Notes

Incremental cutover from Express (`docker/web`) to Laravel (`backend/`). **Express remains the browser entry until a later cutover.**

## Phase 0–2 + Phase 3 slices 1–5 (complete)

Scaffold, users, org, ticket read/draft/submit APIs, dept accept/reject/action-plan, Express bridges (flag OFF).

## Phase 3 slice 6 (dept return/reassign/close + president decision — current)

**What exists**

| Piece | Notes |
|-------|--------|
| `POST /api/v1/tickets/{ref}/return` | Owned ticket → `ownership_rejected` (report revision) |
| `POST /api/v1/tickets/{ref}/reassign` | Cross-dept reassignment → `assigned` |
| `POST /api/v1/tickets/{ref}/close` | After accomplishment → `closed` |
| `POST /api/v1/tickets/{ref}/president-decision` | Action-plan / final presidential decisions |
| `DeptTicketService` + `PresidentTicketService` | Mirrors Express dept/president workflow |
| `rms.president` middleware | Sanctum + `president` role |
| Express bridge | Mirrors return/reassign/close/president when `USE_LARAVEL_API=true` |
| `php artisan rms:smoke-dept-s6` | Return → reassign → close smoke |
| `USE_LARAVEL_API` | Still default **`false`** |

**What did NOT change (flag off)**

- `/dept` and `/president` UI still Express + `store.json` + notifications
- Login, roles, modules, attachments unchanged

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:import-users
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:import-org
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-dept-s6
Invoke-WebRequest http://localhost:8080/login -UseBasicParsing | Select-Object StatusCode
Invoke-RestMethod http://localhost:8080/api/v1/health
```

## Remaining (not started)

1. Personnel / documents / thread comments / reopen (officer)  
2. Attachments / MinIO API ownership  
3. Notifications / reports  
4. Staging soak with `USE_LARAVEL_API=true`

## Related

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DOCKER.md](DOCKER.md)
- [ENVIRONMENT.md](ENVIRONMENT.md)
- [ADR 001](adr/001-backend-laravel.md)
