# HPS Staff App — Build Progress

Internal staff app for High-Performance Sports (Port Moresby, PNG).
Spec: `../CLAUDE.md` (build specification). Repo: https://github.com/Jamekah/hps-staff-app

## Status Overview

| Phase | Scope | Status |
|---|---|---|
| 1. Foundation | Scaffold, auth + password reset, roles/policies, super admin seeder, user management | ✅ Complete & deployed to Laravel Cloud |
| 2. Core pages | Events calendar, gym schedule, announcements, shared folder | ✅ Complete (needs deploy + bucket setup) |
| 3. Notifications | In-app feed, FCM, device tokens, scheduled jobs | ⬜ Not started |
| 4. Android | Capacitor wrapper, FCM native, APK build | ⬜ Not started |

---

## Phase 1 — Foundation ✅ (2026-07-16)

### Built
- **Laravel 12** project scaffolded at `hps-staff-app/` (Livewire 3 + Volt, Tailwind, Breeze auth).
  - ⚠️ Spec said Laravel 11, but Composer blocks all Laravel 11 releases due to open
    security advisories. Laravel 12 has an identical structure and supports everything
    in the spec — recorded here as an approved deviation.
- **Timezone**: `APP_TIMEZONE=Pacific/Port_Moresby` in `.env` / `.env.example`.
  DB stores UTC; conversion happens at the display/scheduling boundary (per spec).
- **Auth (Breeze, Livewire stack)**: login, logout, password reset via email,
  profile page. **Public registration removed** — accounts are created only by the
  super admin.
- **Roles**: `role` enum column (`staff` / `admin` / `super_admin`) + `is_active`
  boolean on `users`. `App\Enums\Role` enum; `User::isAdmin()` / `isSuperAdmin()` helpers.
- **Gates** (in `AppServiceProvider`):
  - `admin` — admin + super_admin (will guard content CRUD in Phase 2)
  - `manage-users` — super_admin only
- **Inactive-user enforcement**:
  - Login blocked with a clear "account deactivated" message (`LoginForm`)
  - Existing sessions killed by `EnsureUserIsActive` middleware (global web middleware)
- **Super admin seeder**: reads `SUPER_ADMIN_NAME/EMAIL/PASSWORD` via `config/superadmin.php`
  (config-cache safe for Laravel Cloud). Credentials live only in `.env` — never committed.
- **User Management page** (`/users`, super admin only): list + search + pagination,
  create/edit (name, email, role), activate/deactivate, delete, "send reset link".
  New users get a random password and a set-password email (reset-link flow, per spec).
  Self-protection: super admin cannot deactivate, delete, or demote themselves.
- **Database**: local MySQL `hps_staff_app` (XAMPP MariaDB).

### Verified
- **40 feature tests passing** (106 assertions): Breeze auth suite + inactive-user
  login/session tests + full user-management authorization and CRUD coverage
  (staff/admin forbidden, guest redirected, create sends reset link, self-protection).
- **Manual browser check**: login → dashboard, Users page renders, "+ New User" modal
  creates a user, flash message shown, reset email generated (log mailer locally).

### Deployed
Live on Laravel Cloud (2026-07-16): MySQL attached, migrations on deploy,
super admin seeded. Mail is still `log` — connect a mail provider (Resend
recommended) before onboarding real staff.

---

## Phase 2 — Core pages ✅ (2026-07-16)

Built per `../PHASE-2-BRIEF.md` (supersedes CLAUDE.md where they differ).

### Built
- **Data model**: `events` + `event_staff`, `gym_schedules` + `gym_schedule_staff`,
  `announcements`, `documents` tables with models, factories, and policies
  (view: everyone; create/update/delete: admin+; announcements editable by their
  author or super admin).
- **Events Calendar** (`/calendar`, default landing page after login):
  custom Livewire month grid (no calendar JS lib), weeks start Monday,
  internal = sky blue / external = amber with legend, multi-day events span days,
  "+N more" expansion on busy days, event detail modal (PNG-time display, staff
  list), upcoming-events sidebar beyond current month, admin create/edit/delete
  with multi-select staff assignment (active users only).
- **Gym Schedule** (`/gym`): **unified day timeline** (revised from two-column
  design per Phase 2 brief — CLAUDE.md updated accordingly), 07:00–19:00 axis,
  date picker/prev/next/today, blocks show name/client/time/staff + studio badge
  (Studio 1 violet, Studio 2 teal, distinct from event colors), overlapping
  sessions render side by side (never blocked — greedy column layout),
  recurrence (none/daily/weekly + weekday picker) computed at render time from
  a single series row, series-level edit/delete, end_date required with no
  maximum length. `GymSchedule::occurrencesOn($date)` is the reusable query
  Phase 3 notifications will target.
- **Announcements** (`/announcements`): newest-first, paginated 15/page,
  author + timestamp; admins create/edit/delete own, super admin any.
  Creation goes through a single `publish()` code path for Phase 3 to hook.
- **Shared Folder** (`/files`): type icons (PDF/Word/Excel), human-readable size,
  upload date, download for all users; admin upload/delete. Server-side MIME
  validation (not extension), 20MB cap (Livewire temp-upload limit raised to
  match), random non-guessable storage names, downloads keep the original
  filename. On S3 (Laravel Cloud) downloads redirect to 5-minute signed URLs;
  local dev streams the file.
- **Navigation**: Calendar / Gym Schedule / Announcements / Files (+ Users for
  super admin) in desktop and mobile menus, placeholder notification bell
  (wired in Phase 3). Login now lands on the calendar.

### Fixed along the way
- **Timezone bug**: Laravel 12's skeleton hardcodes `'timezone' => 'UTC'` and
  ignores `APP_TIMEZONE` — timestamps were silently stored/displayed in UTC.
  `config/app.php` now reads `APP_TIMEZONE` (default `Pacific/Port_Moresby`).
- **Timezone decision (deviation from original CLAUDE.md note)**: datetimes are
  stored as PNG wall-clock time, not UTC. PNG has no daylight saving (fixed
  UTC+10), Laravel natively stores in the app timezone, and Phase 3's scheduled
  jobs ("08:00 daily", "60 min before session") compare naturally with zero
  conversion. CLAUDE.md updated.
- Livewire `storeAs` defaults to its temporary-upload disk — uploads now pass
  the application disk explicitly.

### Verified
- **75 tests passing (210 assertions)**: all Phase 1 suites + policy enforcement
  per page, event CRUD/staff assignment/multi-day rendering, recurrence expansion
  (daily + weekly incl. boundary dates, e.g. Mon/Wed/Fri over 2 months),
  overlap column layout, file validation rejections (zip, >20MB), download
  authorization.
- **Browser-checked** (admin + staff roles): calendar grid/modal/"+2 more",
  weekly session absent Thu / present Fri, announcements, file download with
  original filename; staff sees zero admin controls; all four pages usable at
  380px with no horizontal overflow.

### To do on deploy
- Attach an **object storage bucket** to the Laravel Cloud environment
  (Documents need S3; `FILESYSTEM_DISK=s3` + bucket vars are auto-injected).

## Phase 3 — Notifications ⬜
Planned: Laravel notifications (database + FCM channels), `device_tokens` table +
registration, `events:notify-today` (daily 08:00 PGT), `gym:notify-upcoming`
(every 5 min, 60-min pre-session reminders), announcement broadcast, bell/feed UI.

## Phase 4 — Android ⬜
Planned: Capacitor wrapper, FCM native setup, APK build.

---

## Environment / Ops Notes
- Local: XAMPP (PHP 8.2.12, MariaDB 10.4), Composer 2.9.5. Start MySQL before working.
- Run app: `php artisan serve` · Run tests: `php artisan test`
- Mail is `log` driver locally (reset links land in `storage/logs/laravel.log`).
- **Action for Jason**: change the super admin password after first production login —
  it was shared in a plaintext note.
