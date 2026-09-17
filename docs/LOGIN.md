# Login and built-in accounts (development)

The Sign In UI is **Laravel Blade** at `/login` (`/laravel/login` still works). Default sign-in is **passwordless**: enter **username** → system emails a 6-digit OTP to the **email on that user account** (Envelope From `OTP_MAIL_FROM`, default `itdepartment.accc@gmail.ph`) → enter OTP → success goes to Laravel `/auth/bridge`. The SMTP sending mailbox is separate from the username the user types. **Admin** sign-in at `/login?as=admin` uses **username + password** (`POST /login` with `mode=password`) via `LoginBridgeService` (no OTP). Sign-out is Laravel `GET`/`POST /logout`. Unmatched edge paths (including `/favicon.ico`) are Laravel. A Laravel web session is established after a successful OTP at `POST /login/otp` or after admin password login at `POST /login`. Blade static `/css` and `/img` are Laravel.

## Access URL

| Environment | URL |
|-------------|-----|
| Docker (default) | http://localhost:8080/login |
| Legacy Blade path | http://localhost:8080/laravel/login |

## Passwordless web login flow (default)

| Step | Route | What happens |
|------|-------|----------------|
| 1 | `GET /login` | Username form only (no password field); link “Login as Admin” → `/login?as=admin` |
| 2 | `POST /login` | Rate-limited OTP request; username stored in session; redirect to `/login/otp` (same UX whether or not the user exists) |
| 3 | `GET /login/otp` | OTP input + masked email hint when the account is known |
| 4 | `POST /login/otp` | Verify OTP → `Auth::login` → session regenerate → bridge code → `/auth/bridge` |
| Resend | `POST /login/otp/resend` | Request a new code for the session username |

## Admin password web login

| Step | Route | What happens |
|------|-------|----------------|
| 1 | `GET /login?as=admin` | Username (prefilled `admin`) + password form; link back to OTP sign-in |
| 2 | `POST /login` with `mode=password` | Rate-limited `LoginBridgeService::authenticate` → `Auth::login` → bridge code → `/auth/bridge` (no OTP) |

API / Next.js token auth at `POST /v1/auth/token` remains **username + password**. Admin user management can still set/reset passwords. The Blade “Forgot password?” link still uses emailed OTP to reset that API/admin password.

OTP mail uses Envelope From `OTP_MAIL_FROM` (default `itdepartment.accc@gmail.ph`). Local Docker (`compose.override.yml` / staging default) points SMTP at **Mailpit** — open http://127.0.0.1:8025 to read codes; they do **not** reach Gmail or other real inboxes. Non-Docker `backend/.env` defaults to `MAIL_MAILER=log` (messages in the app log). Production must set `MAIL_MAILER=smtp` plus `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` for a mailbox allowed to send as `OTP_MAIL_FROM`.

## Roles (canonical)

Source of truth: [`backend/app/Support/Roles.php`](../backend/app/Support/Roles.php).

| Role id | Label | Console path | Assignable in User Management |
|---------|-------|--------------|-------------------------------|
| `supervisor` | Ticket Reporter | `/supervisor` | Yes |
| `dept_head` | Department Head / Vice President | `/dept` | Yes |
| `rm_officer` | Risk Management Officer (RMO) | `/officer` | Yes |
| `compliance_officer` | Compliance Officer | `/compliance` | Yes |
| `executive` | Executive Committee | `/executive` | Yes |
| `president` | President | `/president` | Yes |
| `admin` | System Administrator | `/admin` | Yes |
| `employee` | Employee | `/dashboard` | No (registry stub only) |

There is **no Audit Officer** console. RMO is **governance oversight only** — departments own tickets; the President approves High/Critical plans and finals.

## Built-in credentials (seed accounts — DEVELOPMENT ONLY)

Defined in Laravel `UserSeed` / `DemoUserSeeder` and merged by `php artisan rms:import-users` when missing from `store.json`. Usernames are case-insensitive at login.

**WARNING: These are development seed passwords for API token auth (`/v1/auth/token`), Blade admin password login (`/login?as=admin`), and admin password fields. The default Blade landing page uses email OTP, not these passwords. Change all of them before any production deploy. Never treat seed credentials as production secrets.**

| Username | Password (dev seed — rotate) | Role |
|----------|------------------------------|------|
| `sys-admin` | `<change-me-before-deploy>` | System Administrator |
| `admin` | `<change-me-before-deploy>` | System Administrator |
| `reporter` | `<change-me-before-deploy>` | Ticket Reporter |
| `dephead` | `<change-me-before-deploy>` | Department Head (Information Technology) |
| `mmcd` | `<change-me-before-deploy>` | Department Head (MMCD) |
| `finance` | `<change-me-before-deploy>` | Department Head (Finance) |
| `operations` | `<change-me-before-deploy>` | Department Head (Operations) |
| `adminsupport` | `<change-me-before-deploy>` | Department Head (Administration) |
| `hrms` | `<change-me-before-deploy>` | Department Head (HRMS) |
| `nbo` | `<change-me-before-deploy>` | Department Head (New Business Operations) |
| `rmo` | `<change-me-before-deploy>` | Risk Management Officer |
| `compliance` | `<change-me-before-deploy>` | Compliance Officer |
| `pceo` | `<change-me-before-deploy>` | President / CEO |
| `executive` | `<change-me-before-deploy>` | Executive Committee |

Local seed values historically used weak shared passwords in the seeder — **rotate immediately** after first login in any shared environment. See `backend/database/seeders` for current hashes.

Legacy usernames may still exist in older imports (`it-head`, `fin-head`, `rm-officer`, `president`).

**Do not use seed passwords in production.**

## Ticket Reporter (`supervisor`)

Sign in as `reporter` (dev seed password — rotate; see table above) → http://localhost:8080/supervisor

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

Sign in as `dephead` (or other department accounts; rotate seed password) → http://localhost:8080/dept

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

## Compliance Officer (`compliance_officer`)

Sign in as `compliance` (dev seed password — rotate; see table above) → http://localhost:8080/compliance

Validates High/Critical accomplishments before presidential final. Same seed-password rules as other demo roles.

## Risk Management Officer — RMO (`rm_officer`)

Sign in as `rmo` (dev seed password — rotate) → http://localhost:8080/officer  
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

Sign in as `pceo` (dev seed password — rotate) → http://localhost:8080/president

| Screen | URL | Purpose |
|--------|-----|---------|
| Overview / queues | `/president` | Pending High/Critical work |
| Ticket detail | `/president/tickets/:ref` | Approve, reject, or return (reason required for reject/return) |

Scope is **High and Critical** only:

1. **Action-plan phase** (`pending_president`) — approve / reject / return the department plan.
2. **Final phase** (`pending_president_final`) — final close/approve / return.

Notifications for this role are filtered to High/Critical.

## Executive Committee (`executive`)

Sign in as `executive` (dev seed password — rotate) → http://localhost:8080/executive

View-only oversight: dashboard, heatmap, reports, trends, statistics, department performance, ticket detail and comments. Notifications are High/Critical only. Pill/UI: “View only”.

## System Administrator (`admin`)

Sign in as `sys-admin` or `admin` (dev seed password — rotate) → http://localhost:8080/admin

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
