# HPS Staff App — Build Progress

Internal staff app for High-Performance Sports (Port Moresby, PNG).
Spec: `../CLAUDE.md` (build specification). Repo: https://github.com/Jamekah/hps-staff-app

## Status Overview

| Phase | Scope | Status |
|---|---|---|
| 1. Foundation | Scaffold, auth + password reset, roles/policies, super admin seeder, user management | ✅ Complete (pending Laravel Cloud deploy) |
| 2. Core pages | Events calendar, gym schedule, announcements, shared folder | ⬜ Not started |
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

### Deferred to later phases
- Laravel Cloud deployment (end-of-Phase-1 per spec) — needs your Laravel Cloud
  account connected to the GitHub repo; app is ready to deploy.
- Redirect-after-login target is `/dashboard` for now; switches to the Events
  Calendar when it exists in Phase 2.

---

## Phase 2 — Core pages ⬜
Planned: events + event_staff tables/models/policies, month-grid calendar with
internal/external color legend + upcoming-events sidebar, gym schedule day view
(07:00–19:00, Studio 1/2 columns, recurrence computed at display time), announcements
page, shared folder (S3, signed URLs, MIME + 20MB validation).

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
