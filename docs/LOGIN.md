# Login and built-in accounts (development)

The Sign In UI is **Laravel Blade** at `/login` (`/laravel/login` still works). Success goes to Laravel `/auth/bridge`. Sign-out is Laravel `GET`/`POST /logout`. Unmatched edge paths (including `/favicon.ico`) are Laravel. A Laravel web session is established at POST `/login`. Blade static `/css` and `/img` are Laravel.

## Access URL

| Environment | URL |
|-------------|-----|
| Docker (default) | http://localhost:8080/login |
| Legacy Blade path | http://localhost:8080/laravel/login |

## Roles (canonical)

Source of truth: [`backend/app/Support/Roles.php`](../backend/app/Support/Roles.php).

| Role id | Label | Console path | Assignable in User Management |
|---------|-------|--------------|-------------------------------|
| `supervisor` | Ticket Reporter | `/supervisor` | Yes |
| `dept_head` | Department Head / Vice President | `/dept` | Yes |
| `rm_officer` | Risk Management Officer (RMO) | `/officer` | Yes |
| `executive` | Executive Committee | `/executive` | Yes |
| `president` | President | `/president` | Yes |
| `admin` | System Administrator | `/admin` | Yes |
| `employee` | Employee | `/dashboard` | No (registry stub only) |

There is **no Audit Officer** console. RMO is **governance oversight only** — departments own tickets; the President approves High/Critical plans and finals.

## Built-in credentials (seed accounts)

Defined in Laravel seed users / `store.json` import. Usernames are case-insensitive at login.

| Username | Password | Role |
|----------|----------|------|
| `sys-admin` | `a3c2026` | System Administrator |
| `admin` | `a3c1993` | System Administrator |
| `reporter` | `a3c1993` | Ticket Reporter |
| `dephead` | `a3c1993` | Department Head (Information Technology) |
| `mmcd` | `a3c1993` | Department Head (MMCD) |
| `finance` | `a3c1993` | Department Head (Finance) |
| `operations` | `a3c1993` | Department Head (Operations) |
| `adminsupport` | `a3c1993` | Department Head (Administration) |
| `hrms` | `a3c1993` | Department Head (HRMS) |
| `nbo` | `a3c1993` | Department Head (New Business Operations) |
| `rmo` | `a3c1993` | Risk Management Officer |
| `pceo` | `a3c1993` | President / CEO |
| `executive` | `a3c1993` | Executive Committee |

Legacy usernames still work: `it-head` / `fin-head` / `ops-head` / `admin-head` (`dept2026`), `rm-officer` / `president` (`a3c2026`).

**Do not use these passwords in production.**

## Ticket Reporter (`supervisor`)

Sign in as `reporter` / `a3c1993` → http://localhost:8080/supervisor

| Screen | URL | Purpose |
|--------|-----|---------|
| Overview | `/supervisor` | Summary and quick links |
| Drafts / tickets | `/supervisor/tickets` | Create, edit drafts, track submitted work |
| New report | `/supervisor/tickets/new` (Blade `/laravel/...` when flag on) | 5W1H risk report |
| Ticket detail | `/supervisor/tickets/:ref` | View, revise returned tickets, implement plans, accomplishments |
| Returned / action | `/supervisor/actions` (Blade `/laravel/...` when flag on) | Tickets needing revision or implementation |
| Accomplishments | `/supervisor/accomplishments` (Blade `/laravel/...` when flag on) | Accomplishment history |
| Notifications | `/supervisor/notifications` (Blade `/laravel/...` when flag on) | In-app alerts |
| Profile | `/supervisor/profile` | Account profile |

**Submit rules:** All 5W1H fields and **at least one evidence file** (PDF/PNG/JPG) are required. Evidence metadata is stored in PostgreSQL (`risk_attachments`); file bytes go to MinIO/S3 (not `store.json`).

**Revision:** When status is `returned` or `ownership_rejected`, the reporter must change the report before resubmit. Reporters do **not** own tickets or write action plans.

## Department Head / Vice President (`dept_head`)

Sign in as `dephead` (or other department accounts) / `a3c1993` → http://localhost:8080/dept

| Screen | URL | Purpose |
|--------|-----|---------|
| Overview | `/dept` → `/laravel/dept` | Department dashboard |
| Inbox | `/dept/inbox` → `/laravel/dept/inbox` | Newly assigned tickets — accept, reject, or reassign |
| Active / drafts | `/dept/active`, `/dept/drafts` → `/laravel/dept/…` | In-progress ownership, action plan drafts |
| Returned tickets | `/dept/returned` → `/laravel/dept/returned` | Plans or finals returned/rejected by the President |
| Overdue / pending closure | Dept queues | SLA and closure after accomplishment |
| Ticket detail | `/dept/tickets/:ref` → `/laravel/dept/tickets/:ref` | Ownership actions, action plan draft/publish, resolution |

**Ownership:** After accept (`in_progress`), the head builds and publishes an action plan.

- **Low / Moderate** → published plan goes to the reporter (`in_mitigation`).
- **High / Critical** → plan goes to the President (`pending_president`).

**Return to reporter** for report revision is allowed only **after ownership is accepted**. Closing after accomplishment uses department closure for Low/Moderate; High/Critical final decisions go through the President.

## Risk Management Officer — RMO (`rm_officer`)

Sign in as `rmo` / `a3c1993` → http://localhost:8080/officer  
(With Blade flags on: overview `/laravel/officer`, queues `/laravel/officer/{tickets,overdue,monitoring,action-plans}`.)

| Screen | URL | Purpose |
|--------|-----|---------|
| Overview | `/officer` → `/laravel/officer` | Governance dashboard |
| Risk register | `/officer/tickets` → `/laravel/officer/tickets` | Organization-wide tickets (view) |
| Overdue & SLA | `/officer/overdue` → `/laravel/officer/overdue` | SLA / overdue monitoring |
| Monitoring | `/officer/monitoring` → `/laravel/officer/monitoring` | Lifecycle monitoring |
| Ticket detail | `/officer/tickets/:ref` → `/laravel/officer/tickets/:ref` | View, thread comments, reopen closed tickets |

RMO **cannot** accept ownership, edit mitigation plans, or close tickets as owner. Reopen of closed tickets (reassign to department) is allowed for governance.

## President (`president`)

Sign in as `pceo` / `a3c1993` → http://localhost:8080/president

| Screen | URL | Purpose |
|--------|-----|---------|
| Overview / queues | `/president` | Pending High/Critical work |
| Ticket detail | `/president/tickets/:ref` | Approve, reject, or return (reason required for reject/return) |

Scope is **High and Critical** only:

1. **Action-plan phase** (`pending_president`) — approve / reject / return the department plan.
2. **Final phase** (`pending_president_final`) — final close/approve / return.

Notifications for this role are filtered to High/Critical.

## Executive Committee (`executive`)

Sign in as `executive` / `a3c1993` → http://localhost:8080/executive

View-only oversight: dashboard, heatmap, reports, trends, statistics, department performance, ticket detail and comments. Notifications are High/Critical only. Pill/UI: “View only”.

## System Administrator (`admin`)

Sign in as `sys-admin` / `a3c2026` (or `admin` / `a3c1993`) → http://localhost:8080/admin

| Screen | URL | Purpose |
|--------|-----|---------|
| Overview | `/admin` → `/laravel/admin` when `USE_LARAVEL_ADMIN_DASHBOARD_UI=true` | Summary and quick links |
| Users | `/admin/users` → `/laravel/admin/users` when `USE_LARAVEL_ADMIN_USERS_UI=true` | Create/edit users, roles, employee IDs (`EMP-###`), password reset |
| Departments | `/admin/departments` → `/laravel/admin/departments` when `USE_LARAVEL_ADMIN_DEPARTMENTS_UI=true` | Department catalog |
| Positions | `/admin/positions` → `/laravel/admin/positions` when `USE_LARAVEL_ADMIN_POSITIONS_UI=true` | Position catalog |
| Tickets | `/admin/tickets` | View / soft-delete tickets (no workflow approve/close) |
| Audit logs | `/admin/audit-logs` | Administrator and system action trail |
| Settings | `/admin/settings` → `/laravel/admin/settings` | Landing branding, AI, security options; reset helpers |
| Profile | `/admin/profile` | Admin profile |

Administrators **cannot** approve risk reports, publish mitigation as owners, or override RMO/President workflow decisions.

Operational data lives in PostgreSQL. `docker/data/store.json` is import-only (optional dual-write). Attachment files: MinIO/S3.

## End-to-end workflow (current)

1. Reporter creates a full 5W1H report with evidence and submits.
2. AI assists classification/routing; ticket is **assigned** to a department.
3. Department Head accepts (or rejects/reassigns); builds action plan.
4. Low/Moderate → reporter implements; High/Critical → President reviews the plan.
5. Reporter implements published plan and submits an accomplishment.
6. Department closes Low/Moderate after accomplishment; High/Critical go to President final decision.
7. RMO monitors organization-wide and may reopen closed tickets; Executive views High/Critical oversight.

See [Architecture](ARCHITECTURE.md) for statuses and design notes.

## Security notes

- Seed credentials are for **development only**.

## Implementation files

| Path | Purpose |
|------|---------|
| `backend/app/Support/Roles.php` | Role registry (labels, paths, assignable) |
| `backend/routes/web.php` | Blade HTTP routes |
| `backend/resources/views` | Blade consoles |

## Rebuild after code changes

```powershell
docker compose -f docker/compose.yml -f docker/compose.override.yml up --build -d api
```
