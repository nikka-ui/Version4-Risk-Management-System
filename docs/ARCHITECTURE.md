# System Architecture

## Overview

The AI Risk Management System (RMS) is an ISO **31000:2018**-aligned risk workflow with AI-assisted classification, multi-role ownership, and Docker-based deployment.

### Current implementation vs planned stack

| Layer | **Current (running)** | **Planned / target** |
|-------|----------------------|----------------------|
| Edge | nginx reverse proxy | Same |
| Application UI + workflow | **Laravel Blade** in `backend/` | Next.js frontend (future) |
| API | Laravel 11 in `backend/` (Sanctum tokens + Blade sessions) | Laravel 11 + Sanctum owns REST ([ADR 001](adr/001-backend-laravel.md)) |
| AI | Flask NLP-hybrid `/classify` + `/summarize` (Phase 11 slice 5) | Optional transformer/GPU model swap |
| Persistence | PostgreSQL + MinIO/S3 (`store.json` import-only) | Full relational model in PostgreSQL |
| Cache | Redis (compose) | Queues / cache for Laravel |

Documented “planned” API tables and Next.js UI remain the long-term target. Day-to-day behavior below matches the **Laravel Blade** app.

## Logical architecture (current)

```mermaid
flowchart TB
  subgraph clients [Clients]
    Browser[Web browser]
  end
  subgraph edge [Edge tier]
    Nginx[nginx reverse proxy]
  end
  subgraph app [Application tier]
    API[Laravel Blade + API]
    AI[AI service Flask]
  end
  subgraph data [Data tier]
    Store[(store.json volume)]
    PG[(PostgreSQL attachments)]
    Redis[(Redis)]
    S3[MinIO / S3]
  end
  Browser --> Nginx
  Nginx --> API
  Nginx --> AI
  API --> Store
  API --> PG
  API --> Redis
  API --> S3
  API --> AI
```

## Roles and responsibilities

Canonical registry: [`backend/app/Support/Roles.php`](../backend/app/Support/Roles.php). Details and seed logins: [LOGIN.md](LOGIN.md).

| Role | Path | Responsibilities |
|------|------|------------------|
| Ticket Reporter (`supervisor`) | `/supervisor` | Create/edit drafts; require full 5W1H + evidence; submit; revise when returned; implement plans; submit accomplishments |
| Department Head / VP (`dept_head`) | `/dept` | Own department tickets: accept/reject/reassign; action plans; return for revision (after accept); close Low/Moderate after accomplishment; manage `/dept/returned` after President return |
| Risk Management Officer — RMO (`rm_officer`) | `/officer` | Governance oversight: register, SLA/overdue, monitoring, comments; reopen closed tickets. **Does not** own, edit mitigation as owner, or close |
| President (`president`) | `/president` | Approve / reject / return High & Critical action plans and final decisions |
| Executive Committee (`executive`) | `/executive` | View-only oversight (dashboard, heatmap, reports, trends); High/Critical notifications |
| System Administrator (`admin`) | `/admin` | Users (incl. employee IDs), departments, positions, ticket view/delete, audit logs, branding/settings. **No** workflow approve/close |
| Employee (`employee`) | `/dashboard` | Non-assignable stub |

**Removed from the active model:** Audit Officer console and RMO-as-ticket-owner workflow.

## Workflow summary

1. Reporter logs in and creates a risk report (**all 5W1H** + **≥1 evidence file**).
2. AI assists summarization/categorization; on submit the ticket is **assigned** to a department.
3. Department Head accepts ownership (or rejects/reassigns) and builds an **action plan**.
4. **Low/Moderate:** plan published to reporter for implementation. **High/Critical:** plan goes to the **President** first.
5. Reporter implements; submits an **accomplishment**.
6. Department closes Low/Moderate after accomplishment; High/Critical use **President final** decision.
7. RMO monitors compliance and may **reopen** closed tickets; Executive monitors High/Critical views continuously.

Historical swimlane art in [`RMS FLOWCHART.png`](../RMS%20FLOWCHART.png) may still show older Audit/RMO-ownership steps — prefer this document and [LOGIN.md](LOGIN.md) for current behavior.

### Notable rules

- **Ownership gate:** Department may return a report for revision only after accepting ownership.
- **Returned-ticket queue:** `/dept/returned` holds President-returned or rejected High/Critical work for plan/final revision.
- **Action-plan revision:** After President return, the department revises and republishes; reporter resubmit may require content change when status is `returned` / `ownership_rejected`.
- **Notifications:** Role- and department-scoped; President/Executive filtered to High/Critical.

## Ticket state machine

Statuses:

| Status | Description |
|--------|-------------|
| `draft` | Reporter composing |
| `submitted` | Submitted (brief AI / routing stage) |
| `assigned` | Routed to department inbox |
| `ownership_rejected` | Department rejected ownership — reporter revises |
| `in_progress` | Department accepted; planning / working |
| `pending_president` | High/Critical plan awaiting President |
| `pending_president_final` | High/Critical final awaiting President |
| `in_mitigation` | Implementation required (reporter) |
| `pending_audit` | Accomplishment submitted (awaiting closure path) |
| `resolved` | Resolved |
| `closed` | Complete |
| `reopened` | Reopened (e.g. by RMO) for further action |

**Legacy** (retained for historical tickets; not the active Audit loop): `under_review`, `returned`, `under_audit`, `audit_returned`.

Ticket references: `RISK-{YEAR}-{#####}` (e.g. `RISK-2026-00001`), assigned as max existing sequence for the year + 1.

## API surface (Phase 1 + planned)

Versioned REST under `/api/v1/` (Laravel). Browser login and workflow HTTP are Laravel Blade.

**Live on Laravel:**

- Blade UI (`/login`, role consoles, mutations)
- Sanctum tokens (`POST /api/v1/auth/token`)
- Admin audit logs in Postgres (`audit_logs`, Phase 10 slice 1)
- Admin settings in Postgres (`system_settings`, Phase 10 slice 2)
- `store.json` dual-write off by default (Phase 10 slice 3)
- Draft/submit AI via `ai-service` `/classify` with PHP stub fallback (Phase 11 slice 1)
- AI classify history in Postgres (`ai_analysis_results`, Phase 11 slice 2)
- Admin AI history Blade + ticket-scoped API (Phase 11 slice 3)
- Express taxonomy classify in `ai-service` (Phase 11 slice 4)
- TF-IDF NLP hybrid classify + taxonomy PHP stub fallback (Phase 11 slice 5)
- Admin ticket AI reclassify API + Blade (Phase 11 slice 6)
- GitHub Actions CI: PHPUnit + ai-service tests (Phase 12 slice 1)

**Planned later:**

- Transformer/GPU model swap behind the same classify/summarize contract
- Next.js frontend scaffold

Today, nginx sends all browser traffic to Laravel (Phase 9 slice 8+). Postgres is the live SoT; `store.json` is import-only unless dual-write flags are re-enabled. See [LARAVEL_MIGRATION.md](LARAVEL_MIGRATION.md).

## Data model

### Current

- **PostgreSQL** — live identity, tickets, settings, notifications, audit logs (Laravel)
- **`docker/data/store.json`** — import source / optional mirror (dual-write OFF by default)
- **PostgreSQL `risk_attachments`** — evidence metadata keyed by `ticket_ref`
- **MinIO/S3** — file bytes under `{ticketRef}/...`

### Planned (relational)

- `users`, `risk_tickets`, `mitigation_plans`, `accomplishment_reports`, `audit_logs`, `ai_analysis_results`

## Technology stack

| Layer | Current | Target |
|-------|---------|--------|
| Web / workflow | Laravel 11 Blade + PHP 8.3 | React / Next.js UI |
| API | Laravel 11 + Sanctum | Same |
| Database | PostgreSQL 16 (attachments + future API) | Same |
| Cache/queue | Redis 7 | Same |
| AI | Python 3.11, Flask | Expanded models |
| Edge | nginx 1.27 | Same |
| Files | MinIO (dev) / S3 (prod) | Same |

## Docker mapping

| Logical component | Container |
|-------------------|-----------|
| Reverse proxy | `nginx` (`rms-nginx`) |
| App (Blade + API) | `api` (`rms-api`) |
| AI | `ai-service` |
| Database | `postgres` |
| Cache | `redis` |
| Object store | `minio` |

See [Docker Guide](DOCKER.md) and [Port Registry](PORT_REGISTRY.md).

## Security architecture

- TLS at nginx (production)
- **RBAC enforced in Laravel** (`backend/app/Support/Roles.php` + web middleware)
- Secrets via Docker secrets files (not in git)
- Network segmentation: `rms_edge`, `rms_app`, `rms_data`
- nginx re-resolves Docker DNS for upstreams (avoids stale IP **502** after container recreate)

Details: [Container Security](CONTAINER_SECURITY.md).

## Operations hooks

- Ticket data lives in PostgreSQL. `docker/data/store.json` is import-only (optional dual-write). Back up Postgres with MinIO/S3.

See [Operations](OPERATIONS.md).

## Alternate backend

Node.js 20 + Express remains an alternate for the **`api`** container in [ADR 001](adr/001-backend-laravel.md).
