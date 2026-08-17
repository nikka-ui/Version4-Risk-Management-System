# Operations Guide

Day-two operations for the RMS Docker deployment.

## Image management

- Pin base image tags in [`docker/compose.yml`](../docker/compose.yml) (avoid `latest`).
- Rebuild application images after dependency updates:

```powershell
docker compose -f docker/compose.yml -f docker/compose.prod.yml build --no-cache api ai-service
```

- Scan images before release (Docker Scout, Trivy, or equivalent).

## Backups

### PostgreSQL

```powershell
docker compose -f docker/compose.yml exec postgres pg_dump -U rms rms > backup_rms_$(Get-Date -Format yyyyMMdd).sql
```

Schedule daily backups with retention per policy (recommended: 30 days minimum).

Restore:

```powershell
Get-Content backup.sql | docker compose -f docker/compose.yml exec -T postgres psql -U rms -d rms
```

### Redis

Redis holds ephemeral cache/queue data. Persist AOF volume `rms_redis_data` but prioritize Postgres for disaster recovery.

### Object storage

- **Dev:** MinIO data in volume `rms_minio_data`
- **Prod:** Use managed S3 with versioning and lifecycle rules

### Application store (`store.json`)

Ticket, user, department, position, notification, and log dual-write data live in:

`docker/data/store.json` (compose mount `./data/store.json` → `/import/store.json`)

Back up this file with PostgreSQL and MinIO/S3 objects when taking application-level backups. The file is gitignored. Live ticket workflow is Laravel/Postgres; `store.json` is the compatibility mirror.

## Resetting ticket data

Preserve **users, departments, positions, settings** in PostgreSQL while clearing tickets in the database, MinIO objects, and the `store.json` ticket arrays if you still keep the mirror. There is no Express `rms-web` reset script after Phase 9 slice 8.

Prefer backing up `docker/data/store.json` plus `pg_dump` before wiping tickets.

## Updates and maintenance

1. Announce maintenance window.
2. `docker compose pull` for infrastructure images (postgres, redis, nginx).
3. Rebuild custom images (`api`, `ai-service`).
4. `docker compose up -d` with prod compose files.
5. Run database migrations inside `api` container.
6. Verify `/health` and smoke-test critical workflows.

## Monitoring

| Check | Endpoint / command |
|-------|-------------------|
| Edge health | `GET /health` on nginx |
| AI health | `GET /ai-health` |
| Container status | `docker compose ps` |
| Resource usage | `docker stats` |

Integrate with your monitoring stack (Prometheus, Datadog, etc.) in a future CI/CD phase.

## Logs

```powershell
docker compose -f docker/compose.yml logs --tail=200 nginx api ai-service
```

Configure a log driver for production (e.g. `json-file` max-size or centralized collector).

## Incident response

| Scenario | Action |
|----------|--------|
| Suspected breach | Rotate `db_password` and `app_key` secrets; restart affected containers |
| DB corruption | Restore from latest `pg_dump`; review audit logs |
| AI service abuse | Block external access to port 5001; review API rate limits |
| Container compromise | `docker compose down`; rebuild images from clean base; redeploy |

## Production checklist

- [ ] TLS certificates installed in `docker/nginx/certs/`
- [ ] Secrets generated and stored outside git
- [ ] Dev profiles (`dev`, MinIO, Mailpit) not enabled
- [ ] Firewall allows only 80/443
- [ ] Backups automated and tested
- [x] GitHub Actions CI: PHPUnit + ai-service tests (Phase 12 slice 1)
- [ ] Image vulnerability scan in CI (Phase 12 slice 2)

## Related

- [Container Security](CONTAINER_SECURITY.md)
- [Docker Guide](DOCKER.md)
