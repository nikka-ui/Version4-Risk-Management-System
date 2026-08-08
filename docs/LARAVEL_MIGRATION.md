# Laravel Migration Notes

Incremental cutover from Express (`docker/web`) to Laravel (`backend/`). **Express remains the browser entry until a later cutover.**

## Phase 0–2 + Phase 3 slices 1–9 (complete)

Scaffold, users, org, ticket workflow APIs (draft→submit→dept→president→personnel/comments/reopen), attachment metadata, notifications + report logs, Express bridges (flag OFF).

## Phase 3 slice 10 (attachment file bytes / MinIO — current)

**What exists**

| Piece | Notes |
|-------|--------|
| `POST /api/v1/tickets/{reference}/attachments/upload` | Multipart `attachments[]` (or `file`) → stores bytes to MinIO + registers metadata |
| `GET /api/v1/attachments/{id}/download` | Streams stored bytes with content-type + filename |
| `ObjectStorageService` | S3/MinIO access; **key scheme identical to Express** (`{safeRef}/{att-…}-{safeName}`) |
| `evidence` filesystem disk | `backend/config/filesystems.php`, keyed to the same `S3_*` envs as `web` |
| `league/flysystem-aws-s3-v3` | Added to `composer.json`/`composer.lock` |
| `api` compose service | Now gets `S3_*` env + `depends_on` minio/minio-init |
| `AttachmentService::storeRawFile/storeUploadedFile(s)/openReadStream` | Validation mirrors Express (pdf/png/jpg/jpeg, 20MB, ≤10) |
| `php artisan rms:smoke-slice10` | Upload→exists→download byte-match→cleanup |
| `AttachmentUploadApiTest` | `Storage::fake('evidence')` upload/download/reject/404 |
| `USE_LARAVEL_API` | Still default **`false`** |

**Interop:** Express and Laravel share bucket `rms-uploads`, the `risk_attachments` table, and the object-key scheme, so Laravel can read/serve objects Express uploaded (and vice versa) with **no byte mirroring**. The slice-8 metadata mirror already carries the real `storageKey`.

**What did NOT change (flag off)**

- Browser upload/download still Express + multer + `docker/web/lib/attachments.js`
- Executive/president report dashboards stay Express-computed
- Login, roles, modules unchanged

## Phase 3 slice 9 (notifications + report logs)

**What exists**

| Piece | Notes |
|-------|--------|
| `GET /api/v1/notifications` | List my notifications (recipient + role filter, oversight High/Critical) |
| `GET /api/v1/notifications/unread-count` | Unread count |
| `POST /api/v1/notifications` | Create/mirror a notification |
| `POST /api/v1/notifications/read-all` | Mark all read (optional `ticketRef`) |
| `POST /api/v1/notifications/{id}/read` | Mark one read → returns `href` |
| `GET /api/v1/report-logs` | List append-only report logs |
| `POST /api/v1/report-logs` | Append/mirror a report log |
| `Notification` + `ReportLog` models/tables | Laravel-owned mirror (own migrations) |
| `NotificationService` + `ReportLogService` | Mirror Express store.notifications / reportLogs |
| Express bridge | `store.js` mirrors on `appendNotification` / `appendReportLog` when `USE_LARAVEL_API=true` |
| `php artisan rms:smoke-slice9` | Notification + report-log smoke |
| `USE_LARAVEL_API` | Still default **`false`** |

**What did NOT change (flag off)**

- Live bell/badge, dashboard notification lists, `/​{role}/notifications/*` routes still Express + `store.json`
- Executive/president aggregated report dashboards stay Express-computed from live tickets
- Login, roles, modules, attachments/MinIO unchanged

### Verify

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d api web
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan migrate --force
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:import-users
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice9
docker compose -f docker/compose.yml -f docker/compose.override.yml exec api php artisan rms:smoke-slice10
Invoke-WebRequest http://localhost:8080/login -UseBasicParsing | Select-Object StatusCode
Invoke-RestMethod http://localhost:8080/api/v1/health
```

## Remaining (not started)

1. Route the browser upload/download through Laravel when `USE_LARAVEL_API=true` (capability now exists)  
2. UI cutover (flag on) / retire Express  
3. Staging soak with `USE_LARAVEL_API=true`

## Related

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DOCKER.md](DOCKER.md)
- [ENVIRONMENT.md](ENVIRONMENT.md)
- [ADR 001](adr/001-backend-laravel.md)
