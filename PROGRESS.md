# HPS Staff App — Build Progress

Internal staff app for High-Performance Sports (Port Moresby, PNG).
Spec: `../CLAUDE.md` (build specification). Repo: https://github.com/Jamekah/hps-staff-app

## Status Overview

| Phase | Scope | Status |
|---|---|---|
| 1. Foundation | Scaffold, auth + password reset, roles/policies, super admin seeder, user management | ✅ Complete & deployed to Laravel Cloud |
| 2. Core pages | Events calendar, gym schedule, announcements, shared folder | ✅ Complete (needs deploy + bucket setup) |
| 3. Notifications | In-app feed, FCM, device tokens, scheduled jobs | ✅ Complete (needs queue worker + scheduler on Cloud) |
| 4. Android | Capacitor wrapper, FCM native, APK build | ✅ Complete — signed APK built |

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

## Phase 3 — Notifications ✅ (2026-07-17)

Built per `../PHASE-3-BRIEF.md`. Firebase project provisioned by Jason;
credentials live only in `.env` / Laravel Cloud secrets (verified: none in repo).

### Built
- **In-app feed**: bell in the nav (desktop + mobile) with unread badge,
  60s Livewire polling, dropdown of recent 10, mark-all-read; `/notifications`
  page with pagination, per-item + mark-all read; clicking a notification marks
  it read and follows its link. Notifications are scoped per-user.
- **FCM channel** (`App\Notifications\Channels\FcmChannel`): kreait/laravel-firebase
  (6.x — 7.x needs PHP 8.3; local XAMPP is 8.2, sodium extension enabled for it),
  multicast to all of a user's `device_tokens`, https click-through link via
  WebPush config, **stale-token pruning** from unknown/invalid FCM responses,
  lazily resolved so credential-less environments (tests) never boot Firebase.
- **Device tokens**: `device_tokens` table; `POST/DELETE /api/device-tokens`
  (session-authenticated). Upsert re-associates a token to the current user;
  the frontend deletes the token on logout (keepalive fetch).
- **Web push**: Firebase JS SDK in the Vite bundle; service worker served at
  `/firebase-messaging-sw.js` **via a Laravel route** so its config comes from
  env (config-cache safe, nothing hardcoded); dismissible "Enable notifications"
  banner (no auto-prompt; hidden when denied/unsupported/dismissed); foreground
  messages show an in-page toast; token re-registered on load when permission
  is already granted.
- **Triggers** (all queued via `ShouldQueue`, database queue):
  - `events:notify-today` — daily 08:00 PGT, assigned active staff only.
  - `gym:notify-upcoming` — every 5 min; half-open window `[now+60m, now+65m)`
    over recurrence-expanded occurrences; `Cache::add` dedupe key per
    occurrence/day (24h) against scheduler overlap.
  - Announcement publish → all active users, chunked (100/batch), single
    `publish()` code path; **editing does not re-broadcast**.

### Verified
- **98 tests passing (262 assertions)**: targeting (assigned-only, active-only,
  all-active for announcements), window boundaries (62' caught, 30'/70' not),
  weekly recurrence day matching, dedupe, token endpoint auth/upsert/delete,
  feed counts + read state + cross-user isolation.
- **Live end-to-end locally**: published an announcement as admin → queue worker
  delivered in-app rows to all active users → staff bell showed 1 → dropdown
  content correct → mark-all-read cleared it. `events:notify-today` run manually
  notified the assigned staff only, with location in the message body.
- **Real FCM round-trip**: notification sent through the actual FCM API with the
  service-account credentials against a fake device token — FCM authenticated,
  reported it invalid, and the channel **pruned it** (tokens 1 → 0, no errors).
- Service worker route returns valid JS with the public web config only
  (no private key material); repo scanned — no secrets staged.

### To do on deploy (Laravel Cloud)
- **Add a queue worker** to the production environment (notifications are queued;
  without a worker nothing sends).
- **Enable the scheduler** so `events:notify-today` / `gym:notify-upcoming` run.
- Confirm the `FIREBASE_CREDENTIALS` + `VITE_FIREBASE_*` env vars are set
  (VITE_ ones must be present at **build** time for the JS bundle).

## Phase 4 — Android ✅ (2026-07-18)

Built per `../PHASE-4-BRIEF.md`. **Capacitor 8** (brief said 6; 8 is current,
same APIs, matches the installed Java 21 toolchain).

### Built
- **Branding**: HPS logo (phoenix "P") now replaces the Breeze placeholder in
  the web app header and login page (`public/images/logo.png`, source kept at
  `branding/logo.png`). Android app icon (adaptive), round icon, and light/dark
  splash screens generated from it via @capacitor/assets. ⚠️ Source logo is
  177×153px — icons are upscaled; swap in a ≥1024px version later for crisper
  results (regenerate with `npx @capacitor/assets generate --android`).
- **Capacitor Android project** (`android/`, package `com.hps.staffapp`,
  "HPS Staff") in **remote-URL mode**: the shell loads the production site over
  https and injects the native bridge, so web deploys reach the app instantly —
  no reinstall. Offline fallback retry page (`capacitor-shell/offline.html`)
  via `server.errorPath`. minSdk 26, portrait-locked, cleartext traffic off,
  white status bar matching the header.
- **Native push**: `push.js` branches at runtime — on Android it uses the
  Capacitor PushNotifications bridge (runtime permission incl. Android 13+,
  registers token as `platform: android` on every app open, foreground toasts,
  notification-tap navigates to the payload link); browsers keep the Phase 3
  web flow. High-importance `hps_default` notification channel (heads-up +
  sound) created in MainActivity and referenced from the manifest for FCM.
- **Back button**: navigates history; minimizes (not exits) from the landing page.
- **Signing**: release keystore `android/app/hps-release.jks` (30-year validity,
  CN=HPS Staff App / O=High Performance Sports / L=Port Moresby / C=PG) with
  credentials in `android/keystore.properties` — both **git-ignored**, along
  with `google-services.json` and `local.properties` (verified none staged).
- **APK**: `android/app/build/outputs/apk/release/app-release.apk` (5.5 MB,
  APK Signature Scheme v2/v3 verified) — copied to `../HPS-Staff-v1.0.apk`.

### ⚠️ CRITICAL — keystore backup (owner action)
Back up `android/app/hps-release.jks` **and** `android/keystore.properties`
somewhere safe outside this machine (e.g. a private cloud drive). They are not
in git. **If the keystore is lost, future app updates cannot install over the
old APK** — staff would have to uninstall/reinstall.

### Remaining verification (needs a physical phone)
Sideload `HPS-Staff-v1.0.apk` → login → allow notifications → confirm an
`android` row in device_tokens → publish announcement with app closed →
tray notification → tap opens announcements page → back-button behavior.

---

## 🎉 All four build phases complete
Web app live on Laravel Cloud; Android APK ready for sideload distribution.
Future roadmap (per CLAUDE.md): sports clinic booking module, possible iOS wrapper.

---

## Environment / Ops Notes
- Local: XAMPP (PHP 8.2.12, MariaDB 10.4), Composer 2.9.5. Start MySQL before working.
- Run app: `php artisan serve` · Run tests: `php artisan test`
- Mail is `log` driver locally (reset links land in `storage/logs/laravel.log`).
- **Action for Jason**: change the super admin password after first production login —
  it was shared in a plaintext note.
