# Laravel Migration Notes

Incremental cutover from Express (`docker/web`) to Laravel (`backend/`). Blade owns GETs plus admin mutations, Department Head workflow + comment + document POSTs, Ticket Reporter preview save/submit + draft delete + create/edit/evidence/accomplishment uploads, RMO reopen + thread-comment, President decision + comment, and Executive comment (Phase 8 slice 3). **Comment edit/react and optional leftovers remain on Express.**

## Phase 0–5 slice 17 (complete)

APIs, dual-write, Blade login, profiles, full Ticket Reporter GET console, admin dashboard + users + departments + positions.

## Phase 5 slice 18 (Admin tickets list — complete)

| Piece | Notes |
|-------|--------|
| `GET /laravel/admin/tickets` | Ticket list with search/filters + soft-delete dialog |
| `USE_LARAVEL_ADMIN_TICKETS_UI` | Compose default **`true`** |
| Delete POST | Phase 7 slice 5: Blade (`POST /admin/tickets/:ref/delete`) |
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

## Phase 6 slice 6 (Employee `/dashboard`)

| Piece | Notes |
|-------|--------|
| nginx `GET /dashboard` | Laravel Blade stub (employees) or redirect to `/{role}` console |
| `USE_LARAVEL_DASHBOARD_UI` | Compose default **`true`** |
| Express redirect | `GET /dashboard` redirects to Blade when enabled |
| Health | `phase: 6`, `slice: 6` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice6-dashboard
curl.exe -sI http://localhost:8080/dashboard
```

Expected: guest `/dashboard` → Laravel `302` to `/login` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 6 slice 5 (RMO legacy aliases)

| Piece | Notes |
|-------|--------|
| nginx `GET /officer/{ai-review,review,final-validation}` | Laravel aliases → `/officer/tickets` or `/officer/action-plans` |
| `USE_LARAVEL_OFFICER_ALIASES_UI` | Compose default **`true`** |
| Express redirect | Matching Express GETs redirect to Laravel aliases when enabled |
| Health | `phase: 6`, `slice: 5` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice6-officer-aliases
curl.exe -sI http://localhost:8080/officer/ai-review
```

Expected: guest `/officer/ai-review` → Laravel `302` to `/login` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 6 slice 4 (Executive ticket detail)

| Piece | Notes |
|-------|--------|
| nginx `GET /executive/tickets/:ref` | Laravel Blade |
| `USE_LARAVEL_EXECUTIVE_TICKET_DETAIL_UI` | Compose default **`true`** |
| Express redirect | `GET /executive/tickets/:ref` redirects to Blade when enabled |
| Mutations | Comment POSTs stay on Express; success redirects back to Blade |
| Exceptions | Attachments + notifications stay Express |
| Health | `phase: 6`, `slice: 4` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice6-executive-ticket-detail
curl.exe -sI http://localhost:8080/executive/tickets/RISK-FAKE-1
```

Expected: guest `/executive/tickets/:ref` → Laravel `302` to `/login` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 6 slice 3 (Executive oversight pages)

| Piece | Notes |
|-------|--------|
| nginx `GET /executive/{heatmap,reports,trends,statistics,departments,register,critical}` | Laravel Blade |
| `USE_LARAVEL_EXECUTIVE_PAGES_UI` | Compose default **`true`** |
| Express redirect | Matching Express GETs redirect to Blade when enabled |
| Exceptions | Attachments, notifications stay Express (ticket detail is slice 4) |
| Health | `phase: 6`, `slice: 3` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice6-executive-pages
curl.exe -sI http://localhost:8080/executive/heatmap
```

Expected: guest `/executive/heatmap` → `302` to `/login`; signed-in executive → Blade heatmap.

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 6 slice 2 (Unprefixed Blade URLs)

| Piece | Notes |
|-------|--------|
| nginx `GET /login` | Laravel Blade (POST `/login` also Laravel) |
| nginx `GET /admin`, `/supervisor`, `/dept`, `/officer`, `/president`, `/executive` | Laravel; POSTs stay Express |
| Exceptions | Attachments, audit CSV export stay Express (officer aliases are slice 5)
| Blade links | Canonical unprefixed (`/admin/users`, not `/laravel/admin/users`) |
| `/laravel/*` | Still rewritten to Laravel (bookmarks) |
| `USE_LARAVEL_EDGE_UI` | Compose default **`true`**. Soak nginx uses `docker/nginx/soak/` (no unprefixed locations) |
| Health | `phase: 6`, `slice: 2` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice6-edge-ui
curl.exe -sI http://localhost:8080/login
curl.exe -sI http://localhost:8080/admin
```

Expected: `/login` → `200` Blade Sign In; `/admin` → `302` to `/login` when signed out.

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 6 slice 1 (Edge `/` → Laravel)

| Piece | Notes |
|-------|--------|
| nginx `location = /` | Proxies exact `/` to Laravel `api:8080` |
| Laravel `GET /` | Guest → `/login`; signed-in → `/{role}` when edge UI is on |
| `USE_LARAVEL_EDGE_ROOT` | Compose default **`true`**. Soak sets **`false`** so `/` redirects to Express `/login` |
| Health | `phase: 6`, `slice: 1` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice6-edge-root
curl.exe -sI http://localhost:8080/
```

## Phase 5 slice 31 (President ticket detail)

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

## Phase 8 slice 3 (Dept documents — current)

| Piece | Notes |
|-------|--------|
| nginx `POST /dept/tickets/:ref/documents` | Laravel multipart dept documents (CSRF, MinIO + Postgres) |
| `USE_LARAVEL_DEPT_TICKET_MUTATIONS` | Compose default **`true`** (same flag as workflow/comment) |
| Express mirror | Laravel writes Postgres + MinIO, then upserts Express `store.json` via `/internal/tickets/upsert` |
| Still Express | Comment edit/react; personnel/resolution Blade UI; admin audit CSV |
| Health | `phase: 8`, `slice: 3` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d --force-recreate nginx
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice8-dept-documents
curl.exe -sI http://localhost:8080/dept/tickets/RISK-SMOKE-AAAAAA/documents
```

Expected: guest documents URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 8 slice 2 (Reporter evidence + accomplishment)

| Piece | Notes |
|-------|--------|
| nginx `POST /supervisor/tickets/:ref/{evidence,accomplishment}` | Laravel multipart evidence + accomplishment (CSRF, MinIO + Postgres) |
| `USE_LARAVEL_REPORTER_UPLOAD_MUTATIONS` | Compose default **`true`** (same flag as create/edit) |
| Express mirror | Laravel writes Postgres + MinIO + accomplishment row, then upserts Express `store.json` |
| Health | `phase: 8`, `slice: 2` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice8-reporter-evidence
curl.exe -sI http://localhost:8080/supervisor/tickets/RISK-SMOKE-AAAAAA/evidence
```

Expected: guest evidence URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

## Phase 8 slice 1 (Reporter create/edit uploads)

| Piece | Notes |
|-------|--------|
| nginx `POST /supervisor/tickets/new/preview` + `POST /supervisor/tickets/:ref/edit` | Laravel multipart create/edit (CSRF, MinIO + Postgres) |
| `USE_LARAVEL_REPORTER_UPLOAD_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres + MinIO, then upserts Express `store.json` via `/internal/tickets/upsert` |
| Still Express | Evidence/accomplishment/documents (moved in slices 2–3); comment edit/react |
| Health | `phase: 8`, `slice: 1` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d --force-recreate nginx
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice8-reporter-uploads
curl.exe -sI http://localhost:8080/supervisor/tickets/new/preview
```

Expected: guest preview URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 13 (Dept thread-comment)

| Piece | Notes |
|-------|--------|
| nginx `POST /dept/tickets/:ref/comment` | Laravel dept thread comment + reply (CSRF, text only) |
| `USE_LARAVEL_DEPT_TICKET_MUTATIONS` | Compose default **`true`** (same flag as workflow) |
| Express mirror | Laravel writes Postgres, then upserts Express `store.json` via `/internal/tickets/upsert` |
| Still Express | Reporter create/edit uploads; evidence/accomplishment/documents; comment edit/react |
| Health | `phase: 7`, `slice: 13` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-dept-thread-comments
curl.exe -sI http://localhost:8080/dept/tickets/RISK-SMOKE-AAAAAA/comment
```

Expected: guest comment URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 12 (President comment)

| Piece | Notes |
|-------|--------|
| nginx `POST /president/tickets/:ref/comment` | Laravel president thread comment (CSRF, text only, High/Critical) |
| `USE_LARAVEL_PRESIDENT_TICKET_MUTATIONS` | Compose default **`true`** (same flag as decision) |
| Express mirror | Laravel writes Postgres, then upserts Express `store.json` via `/internal/tickets/upsert` |
| Still Express | Reporter create/edit uploads; dept thread-comments; evidence/accomplishment/documents |
| Health | `phase: 7`, `slice: 12` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-president-thread-comments
curl.exe -sI http://localhost:8080/president/tickets/RISK-SMOKE-AAAAAA/comment
```

Expected: guest comment URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 11 (Officer thread-comment)

| Piece | Notes |
|-------|--------|
| nginx `POST /officer/tickets/:ref/thread-comment` | Laravel RMO governance comment + reply (CSRF, text only) |
| `USE_LARAVEL_OFFICER_TICKET_MUTATIONS` | Compose default **`true`** (same flag as reopen) |
| Express mirror | Laravel writes Postgres, then upserts Express `store.json` via `/internal/tickets/upsert` |
| Still Express | Reporter create/edit uploads; president/dept thread-comments; evidence/accomplishment/documents |
| Health | `phase: 7`, `slice: 11` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-officer-thread-comments
curl.exe -sI http://localhost:8080/officer/tickets/RISK-SMOKE-AAAAAA/thread-comment
```

Expected: guest thread-comment URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 10 (Executive comment)

| Piece | Notes |
|-------|--------|
| nginx `POST /executive/tickets/:ref/comment` | Laravel thread comment + reply (CSRF, text only) |
| `USE_LARAVEL_EXECUTIVE_TICKET_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres, then upserts Express `store.json` via `/internal/tickets/upsert` |
| Still Express | Reporter create/edit uploads; officer/president/dept thread-comments; evidence/accomplishment/documents |
| Health | `phase: 7`, `slice: 10` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-executive-ticket-mutations
curl.exe -sI http://localhost:8080/executive/tickets/RISK-SMOKE-AAAAAA/comment
```

Expected: guest comment URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 9 (President decision)

| Piece | Notes |
|-------|--------|
| nginx `POST /president/tickets/:ref/decision` | Laravel action-plan + final decision (CSRF) |
| `USE_LARAVEL_PRESIDENT_TICKET_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres, then upserts Express `store.json` via `/internal/tickets/upsert` |
| Still Express | President thread-comment (uploads) |
| Health | `phase: 7`, `slice: 9` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-president-ticket-mutations
curl.exe -sI http://localhost:8080/president/tickets/RISK-SMOKE-AAAAAA/decision
```

Expected: guest decision URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 8 (Officer reopen)

| Piece | Notes |
|-------|--------|
| nginx `POST /officer/tickets/:ref/reopen` | Laravel reopen + reassign department (CSRF) |
| `USE_LARAVEL_OFFICER_TICKET_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres, then upserts Express `store.json` via `/internal/tickets/upsert` |
| Still Express | Officer thread-comment (uploads) |
| Health | `phase: 7`, `slice: 8` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-officer-ticket-mutations
curl.exe -sI http://localhost:8080/officer/tickets/RISK-SMOKE-AAAAAA/reopen
```

Expected: guest reopen URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 7 (Reporter preview/delete mutations)

| Piece | Notes |
|-------|--------|
| nginx `POST /supervisor/tickets/new/preview/:ref/{save,submit}` | Laravel preview save (no-op persist) + submit (CSRF confirm) |
| nginx `POST /supervisor/tickets/:ref/delete` | Laravel draft hard-delete (CSRF) |
| `USE_LARAVEL_REPORTER_TICKET_MUTATIONS` | Compose default **`true`** |
| Express mirror | Submit upserts `store.json` via `/internal/tickets/upsert`; delete removes draft via `/internal/tickets/delete-draft` |
| Still Express | Multipart create/edit, evidence, accomplishment, comments |
| Health | `phase: 7`, `slice: 7` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-reporter-ticket-mutations
curl.exe -sI http://localhost:8080/supervisor/tickets/RISK-SMOKE-AAAAAA/delete
```

Expected: guest delete URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 6 (Dept ticket workflow mutations)

| Piece | Notes |
|-------|--------|
| nginx `POST /dept/tickets/:ref/{accept,reject,return,reassign,action-plan,close}` | Laravel workflow (CSRF); personnel/documents/comments/resolution stay Express |
| `USE_LARAVEL_DEPT_TICKET_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres, then upserts Express `store.json` via `/internal/tickets/upsert` |
| Health | `phase: 7`, `slice: 6` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-dept-ticket-mutations
curl.exe -sI http://localhost:8080/dept/tickets/RISK-SMOKE-AAAAAA/accept
```

Expected: guest accept URL → Laravel `302` to `/login` or `405 Allow: POST` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 5 (Admin ticket soft-delete)

| Piece | Notes |
|-------|--------|
| nginx `POST /admin/tickets/:ref/delete` | Laravel soft-delete with reason (CSRF) |
| `USE_LARAVEL_ADMIN_TICKET_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres, then mirrors into Express `store.json` via `/internal/tickets/soft-delete` |
| Health | `phase: 7`, `slice: 5` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-admin-ticket-mutations
curl.exe -sI http://localhost:8080/admin/tickets/RISK-SMOKE-AAAAAA/delete
```

Expected: guest delete URL → Laravel `302` to `/login` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 4 (Admin settings mutations)

| Piece | Notes |
|-------|--------|
| nginx `POST /admin/settings*` | Laravel save / reset-landing / reset-ai (CSRF) |
| `USE_LARAVEL_ADMIN_SETTINGS_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres `system_settings`, then mirrors into Express `store.json` via `/internal/org/settings` |
| Health | `phase: 7`, `slice: 4` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan migrate --force
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-admin-settings-mutations
curl.exe -sI http://localhost:8080/admin/settings
```

Expected: guest `/admin/settings` → Laravel `302` to `/login` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 3 (Admin user mutations)

| Piece | Notes |
|-------|--------|
| nginx `POST /admin/users*` | Laravel create/edit/activate/deactivate/delete/reset-password (CSRF) |
| `USE_LARAVEL_ADMIN_USER_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres, then mirrors into Express `store.json` via `/internal/org/users` (plaintext password for Express fallback) |
| Health | `phase: 7`, `slice: 3` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-admin-user-mutations
curl.exe -sI http://localhost:8080/admin/users
```

Expected: guest `/admin/users` → Laravel `302` to `/login` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 2 (Admin position mutations)

| Piece | Notes |
|-------|--------|
| nginx `POST /admin/positions*` | Laravel create/edit/delete (CSRF) |
| `USE_LARAVEL_ADMIN_POS_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres, then mirrors into Express `store.json` via `/internal/org/positions` |
| Health | `phase: 7`, `slice: 2` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-admin-pos-mutations
curl.exe -sI http://localhost:8080/admin/positions
```

Expected: guest `/admin/positions` → Laravel `302` to `/login` (no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Phase 7 slice 1 (Admin department mutations)

| Piece | Notes |
|-------|--------|
| nginx `POST /admin/departments*` | Laravel create/edit/delete (CSRF) |
| `USE_LARAVEL_ADMIN_DEPT_MUTATIONS` | Compose default **`true`** |
| Express mirror | Laravel writes Postgres, then mirrors into Express `store.json` via `/internal/org/departments` |
| Health | `phase: 7`, `slice: 1` |

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up -d nginx api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice7-admin-dept-mutations
curl.exe -sI -X POST http://localhost:8080/admin/departments
```

Expected: guest POST → Laravel `302` to `/login` (XSRF cookie, no `X-Powered-By: Express`).

### Opt out

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml -f docker/compose.soak.yml up -d nginx api web
```

## Remaining
1. Optional leftovers: dept personnel/resolution Blade UI, comment edit/react, notifications read-all (other roles), admin audit CSV export
2. Then retire Express / flip soak defaults

## Related

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DOCKER.md](DOCKER.md)
- [ENVIRONMENT.md](ENVIRONMENT.md)
- [LOGIN.md](LOGIN.md)
- [ADR 001](adr/001-backend-laravel.md)
