# HPS Staff App — Build Progress

Internal staff app for High-Performance Sports (Port Moresby, PNG).
Spec: `../CLAUDE.md` (build specification). Repo: https://github.com/Jamekah/hps-staff-app

## Status Overview

| Phase | Scope | Status |
|---|---|---|
| 1. Foundation | Scaffold, auth + password reset, roles/policies, super admin seeder, user management | ✅ Live in production |
| 2. Core pages | Events calendar, gym schedule, announcements, shared folder | ✅ Live in production |
| 3. Notifications | In-app feed, FCM, device tokens, scheduled jobs | ✅ Live in production |
| 4. Android | Capacitor wrapper, FCM native, APK build | ✅ Installed & verified on a physical phone |
| 5. SM Clinic | Sports medicine bookings, clash engine, clinician views | ✅ Live in production |
| Laravel 13 upgrade | Framework 12 → 13 (PHP 8.3+), no behaviour change | ✅ Merged to main and deployed |
| 6. Staff Availability | Leave records, roster-only staff, leave in the conflict engine | ✅ Live in production |
| 6.1 Leave stats & allowances | Annual/sick allowances, stats panel, clinic services on deploy | ✅ Live in production |
| Continuous integration | GitHub Actions runs the suite on PHP 8.4 and 8.5 for every PR | ✅ Live on `main` |

**Everything below is verified working in production as of 2026-07-18.** Cloud
infrastructure configured by Jason: MySQL, object-storage bucket, queue worker,
scheduler, Gmail SMTP mail, Firebase env secrets.

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
  New users get a random password and an **account-setup email** (24-hour
  `invites` token — see "Account setup replaces the reset email", 18 Nov 2026;
  originally the stock reset-link flow).
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

### Phone verification ✅ (2026-07-18)
APK sideloaded on a physical Android phone by Jason: login, all pages, and
push notifications confirmed working — including tray notifications with the
app closed. Web changes deployed to Laravel Cloud appear in the app without
reinstalling (remote-URL mode).

---

## 🎉 All four build phases complete
Web app live on Laravel Cloud; Android APK ready for sideload distribution.
Future roadmap (per CLAUDE.md): sports clinic booking module, possible iOS wrapper.

---

## Environment / Ops Notes
- Local: XAMPP (PHP 8.2.12, MariaDB 10.4), Composer 2.9.5. Start MySQL before working.
- Run app: `php artisan serve` · Run tests: `php artisan test`
- Mail: `log` driver locally (reset links land in `storage/logs/laravel.log`);
  **Gmail SMTP in production** (app password stored as a Cloud secret). New-user
  account-setup emails verified delivering to real inboxes.
- **Secrets never in git** (verified): `.env`, `android/app/google-services.json`,
  `android/keystore.properties`, `android/app/hps-release.jks`.
- ⚠️ **Keystore backup**: `android/app/hps-release.jks` + its passwords exist ONLY
  on this machine. Losing them means future APKs cannot update-install over the
  installed one. Back them up somewhere safe.
- **Action for Jason**: change the super admin password after first production login —
  it was shared in a plaintext note.

---

## Post-launch tweaks (2026-07-18)
- App heading **"HPS Operations"** added beside the logo in the nav bar and on the
  login screen.
- Footer with **"Information and support"** link on every page (app + login
  layouts); opens a popup crediting JMK IT Solutions with a link to
  https://jmkits.net/. Footer pinned to the bottom via flex layout.

---

## UI Redesign — "Modernist" ✅ (2026-08-18)

Implemented from the Claude Design project *Website redesign with calendar*
(`Calendar Redesign.dc.html`, `Gym Schedule Redesign.dc.html`, and the
`modernist` design-system bundle). Built on the `redesign` branch, reviewed
locally by Jason, and **merged to main / deployed to production**.

### Design decisions taken
- **Desktop calendar: variant 1A "Ledger"** — the design doc itself carried this
  direction forward (Turn 2 titled "Ledger on mobile", Turn 3 "Ledger treatment"),
  so 1B/1C were not built.
- **Phone calendar: variant 2A "dots + day agenda"** (Jason's pick). The grid
  carries markers only; the tapped day lists its events in full below it.
  2B was rejected in the design doc's own notes (titles truncate at 51px columns).
- **Gym extras: session counts + NOW line + studio filter only** (Jason's pick).
  "Busiest stretch", "free blocks", tomorrow's count and the overlap note in the
  detail sheet were left out — fuzzier definitions, more logic, less value.

### Theme layer
- `tailwind.config.js` now carries the Modernist tokens: warm off-white grounds
  (`paper` #f3f2f2 / `surface` #eae9e9), an ink ramp off #201e1d, brand red
  `accent` #ec3013 with a 100–900 ramp, and **border radius 0 everywhere**
  (`full` kept for genuine circles). Type is **Archivo** (400–800) via bunny.net,
  matching the existing self-imposed no-CDN-runtime-JS constraint.
- Studio colours moved to **blue #2f5e89 / green #216b52** (from violet/teal) with
  tint/dark/mid/muted steps, keeping them clearly apart from the calendar's
  ink/red event vocabulary. oklch from the design was converted to hex so older
  Android WebViews render it.
- `resources/css/app.css` adds four component classes used throughout:
  `.hps-label`, `.hps-panel`, `.hps-panel-head`, `.hps-row`, `.hps-chip`.
- New Blade components: `x-sheet` (the detail/form sheet shell), `x-flash`
  (session status/error), `x-dark-button`. Every existing button/input/nav
  component was restyled in place, so pages inherit the system automatically.

### Pages rebuilt
- **Calendar** — Ledger month grid (solid ink/red pills, weekday rules, "+N more"),
  upcoming-events sidebar with date stack, phone dots grid + day agenda,
  event detail sheet, create/edit form as a sheet with a segmented type picker.
- **Gym Schedule** — desktop day timeline (hour gutter, tinted studio blocks with
  S1/S2 badges, side-by-side overlaps unchanged), red **NOW** marker, "Day at a
  glance" per-studio counts, phone time-gutter list with an ALL/S1/S2 filter,
  and a new **session detail sheet** (tapping a block) that carries the admin
  edit/delete actions — replacing the tiny inline icons, which were poor on phones.
- **Announcements, Files, Users, Notifications, Profile, and all auth pages**
  extrapolated into the same vocabulary (the brief only designed two pages).

### New component state
- `EventsCalendar::$selectedDate` + `selectDay()` — drives the phone agenda;
  month navigation parks it on today or the 1st.
- `GymSchedulePage::$studioFilter`, `$selectedScheduleId`, `nowOffsetPercent()`,
  and grouped `mobileRows`. The filter is **phone-only by design**: the desktop
  timeline always shows both studios so admins never lose sight of overlaps.

### Verified
- **114 tests passing** (288 assertions) — the 98 existing ones plus 16 new
  covering the day agenda (multi-day spans, month navigation, empty days) and
  the gym filter/counts/NOW marker/detail-sheet permissions.
- **Browser-checked** at 1280px and 380px: calendar grid + agenda, gym timeline
  with NOW line and overlapping blocks, phone list and filter — **no horizontal
  overflow at 380px on either page**.
- **Authorization re-checked after the rewrite**: a staff user sees none of
  "New Event / New Session / New Announcement / Upload File / Edit Event /
  Edit Session" on any page, while an admin sees all of them. No `@can` gate was
  lost in the restyle.

### Status bar fix (2026-08-18)
On the Android app the header sat underneath the phone's status bar, making the
menu and notification bell hard to tap. Cause: the app targets **SDK 36**, and
**Android 15+ forces edge-to-edge** — the WebView renders behind the status bar
and the `android:statusBarColor` in `styles.xml` is ignored on those versions.

Fixed in CSS rather than natively, so it deploys through Laravel Cloud and reaches
installed phones **without an APK rebuild or reinstall**:
- `viewport-fit=cover` added to both layouts' viewport meta (required for `env()`
  to report real inset values).
- `.safe-top` / `.safe-bottom` utilities in `app.css` pad content clear of the
  status bar and the on-screen navigation bar.
- Applied to the nav (its white background extends up behind the status bar, so
  the band reads as part of the app) and to the page wrappers.

`env()` resolves to 0 in desktop and mobile browsers, so the change is inert
outside the native app — verified: nav padding 0px and unchanged height in-browser.

---

## Phase 5 — SM Clinic ✅ (2026-08-21)

Reviewed locally by Jason and **merged to main / deployed to production**.

Sports Medicine booking module, built per `../PHASE-5-BRIEF.md`. Additive: no
existing events / gym / announcements / files / notifications behaviour changed,
apart from the two new user columns and reuse of the notification pipeline and
the gym occurrence query.

### Built

**New user attributes** — `can_book` and `is_clinician` booleans on `users`,
both defaulting false and orthogonal to the `role` enum. Granted on the existing
super-admin Users page (two checkboxes, plus at-a-glance badges in the table).
A user may hold either, both, or neither.

**Access model** (gates + policies, enforced on routes, nav, and every action):
- Full module (`use-clinic`) — super_admin, admin, or staff with `can_book`
- Scoped "My Appointments" (`view-own-appointments`) — anyone with
  `is_clinician`, **or still holding assignments**, so revoking the flag never
  strands existing bookings
- Plain staff — no clinic nav links at all, 403 on direct access

**Data model** — `clinic_services` (six seeded; only Rehabilitation recurs),
`clinic_clients`, `clinic_appointment_series` (parent for recurring rehab), and
`clinic_appointments` (one materialised row per occurrence). Models, factories,
and policies for each.

**`App\Services\StaffAvailability` — the conflict engine.** Deliberately
module-agnostic (takes a user id + window), so future features reuse it directly.
A clinician is unavailable if the window overlaps any of:
1. events they're assigned to,
2. gym occurrences they're allocated to (reusing `GymSchedule::occurrencesOn()`),
3. their own `scheduled` clinic appointments.
Overlap is half-open, so back-to-back bookings are legal. Cancelled / completed /
no-show appointments never block. `ignoreAppointmentId` lets an edit avoid
clashing with itself.

**Booking rules** (`App\Services\ClinicBooking`):
- **Single bookings hard-block on a clash** — deliberately unlike the gym, which
  permits overlaps by design.
- **Recurring rehab never blocks** — each occurrence is checked, clashing ones
  are flagged `has_clash`, and a summary is surfaced at confirmation
  ("3 of 12 sessions clash for X: dates"). The booker proceeds or adjusts.
- Recurrence controls appear **only** for services with `allows_recurrence`, and
  are re-checked server-side so a non-recurring service can never be booked as a
  series.
- End time pre-fills from the service's default duration and is editable per
  booking; explicit `starts_at`/`ends_at` are persisted.

**Views** — daily timeline (08:00–16:00, blocks positioned by time, overlaps
split into side-by-side columns, clashes flagged, cancelled shown struck-through
as history), monthly day-card overview, day/month toggle remembered per session,
and a "Next today" side panel. Clinic gets its own **plum** accent, distinct from
the calendar's ink/red and the gym's blue/green.

**Status lifecycle** — scheduled → cancelled / completed / no_show. Cancel by a
booker/admin or the assigned clinician; whole-series cancel available. Outcomes
set by the assigned clinician (or a booker/admin) once the appointment has ended.

**Notifications** (reusing the Phase 3 database + FCM pipeline):
- New-assignment notice on booking (one summary per series, not per occurrence)
- `clinic:notify-upcoming` — 60-minute reminder, 5-minute cadence, half-open
  `[now+60m, now+65m)` window, dedupe key `clinic-reminder:{id}`
- `clinic:prompt-status` — every 15 min, finds elapsed `scheduled` appointments,
  prompts the clinician for an outcome, stamps `status_prompt_sent_at` so it
  never re-prompts. Deep-links into the clinician's scoped view.
- Both registered with the scheduler in Pacific/Port_Moresby.
- Clients receive nothing automated (they aren't users) — noted as possible future work.

### Verified
- **179 tests passing (407 assertions)** — 81 new, no regressions in any existing suite.
  Clash engine (13), booking service (12), access control (12), notifications (10),
  lifecycle + UI (18), user privileges (4), plus the existing suites.
- **Browser-checked**: daily timeline renders all appointments positioned correctly;
  monthly view shows day cards with "+N more"; recurrence UI appears for
  Rehabilitation and is absent for Sports massage; the clinician picker greyed out
  a clinician **because of a genuine gym-session clash** (verified against the
  conflict engine — Studio 1 Strength 08:30–10:00 and Studio 2 Netball 09:00–10:30);
  the scoped view listed only the signed-in clinician's appointments and recording
  an outcome persisted to exactly that row.

### Notes for later
- Service durations are all seeded at 30 minutes as a placeholder — supply real
  per-service values when known (duration stays editable per booking regardless).
- Editing an appointment acts on the single occurrence; series-level edits are
  create-time only (cancel-series is available).

---

## UI refresh — softer type and corners ✅ (2026-09-09)

Reviewed locally by Jason and **merged to main / deployed to production**.

Applied the 4C style spec (`../HPS APP UI Tweak_09Sep26.txt`). A refinement of
the Modernist system, not a new design: **layout and palette are unchanged**,
only type and corners move. No functional change — 182 tests still passing.

### Changed
- **Type**: Archivo → **Source Sans 3** (400/500/600/700, via the bunny.net CDN
  already in use). Weights are now **capped at 700** — the spec forbids heavier,
  and the old 800s were what made the UI feel blocky. Headings stay 700; row
  titles, labels and button text drop to 600 for a quieter read.
- **Corners**: the flat 0-radius scale becomes **12px / 8px / 3px**:
  - `rounded-xl` (12px) — panels, the right rail, dialogs and sheets
  - `rounded-lg` (8px) — buttons, inputs, event pills, timeline blocks, chips
  - `rounded-sm` (3px) — legend swatches, S1/S2 and status badges, the NOW chip
  - `rounded-full` — notification count, unread dots, the now-marker dot
- `.hps-panel` gained `overflow-hidden` so table headers and first/last rows clip
  to the new corners instead of poking past them.
- New `.hps-segment` component: segmented controls (day/month toggle, studio
  filter, My Appointments tabs) and arrow pairs round only their **outer** edge,
  so shared inner borders stay flush with no double curve.
- Detail sheets now float as a rounded card (padding on the backdrop) rather
  than sitting flush to the screen edge, which the 12px corners need to read.

### Mobile
Audited every page at 380px: no horizontal overflow anywhere, and **every tap
target now meets the spec's 44px minimum** (was 10 short on the gym page and 23
on Users — dense table action links, a pre-existing issue). Desktop keeps its
tighter 34–36px metrics via `sm:` overrides.

### Note
The clinic's plum accent is kept as-is. It post-dates this spec, which lists
colours only for events and studios; plum keeps the "no colour means two
things" rule intact.

---

## Laravel 13 upgrade ✅ (2026-11-17)

Reviewed locally by Jason and **merged to main / deployed to production**.

Framework upgrade only, per `../LARAVEL-13-UPGRADE-BRIEF.md`. **No feature, UI or
behaviour change.** Laravel 12 bug fixes ended 13 Aug 2026 and security fixes end
24 Feb 2027; Laravel 13 needs PHP 8.3+.

**Laravel Cloud runs PHP 8.3+** — Laravel 13 will not boot on 8.2.

### Package versions (before → after)

| Package | Before | After |
|---|---|---|
| php (constraint) | ^8.2 | **^8.3** |
| laravel/framework | 12.64.0 | **13.42.0** |
| laravel/tinker | 2.11.1 | **3.1.0** |
| livewire/livewire | 3.8.2 | **3.8.11** (stayed on Livewire 3) |
| livewire/volt | 1.10.5 | **1.11.2** |
| kreait/laravel-firebase | 6.2.0 | **7.2.1** |
| league/flysystem-aws-s3-v3 | 3.35.2 | 3.35.3 |
| laravel/breeze (dev) | 2.4.2 | 2.4.2 (kept — resolves fine on 13) |
| phpunit/phpunit (dev) | 11.5.56 | **12.5.44** |
| laravel/pint (dev) | 1.29.3 | 1.32.3 |
| laravel/pail (dev) | 1.2.7 | 1.3.0 |
| laravel/sail (dev) | 1.63.0 | 1.70.0 |
| nunomaduro/collision (dev) | 8.9.5 | 8.9.6 |
| mockery/mockery (dev) | 1.6.12 | 1.6.15 |
| fakerphp/faker (dev) | 1.24.1 | 1.24.1 |

Transitively: Symfony 7.4 → **8.1**, Carbon 3.13.1 → 3.15.1, aws-sdk-php 3.388.7
→ 3.404.0. `composer audit`: **no advisories**.

**Livewire 3 was kept.** Composer resolves Laravel 13 with Livewire 3.8.11, so the
brief's stop condition (a forced Livewire 4 migration) never triggered.

**Nothing was removed.** Breeze resolves against Laravel 13, so it stayed as a dev
dependency.

### Application code changed: none
`composer.json` and `composer.lock` are the only modified files. The 12 → 13
upgrade guide was worked through item by item and **none of its breaking changes
touch this app**: no `VerifyCsrfToken`/`PreventRequestForgery` references, no
`upsert()`, no `JobAttempted`/`QueueBusy` listeners, no manager `extend()`
callbacks, no `pagination::default` overrides, no `Str` factory overrides, no
custom `array_first`/`array_last`, no custom cache/queue/dispatcher drivers.

Two new Laravel 13 config keys were **deliberately left unset**, because the
framework defaults match the old Laravel 12 behaviour and the brief forbids
behaviour changes:
- `session.serialization` — framework default is `php` (the L13 *skeleton* sets
  `json`). Leaving it unset **keeps existing sessions valid**, so nobody is
  logged out by the deploy. Switching to `json` is more secure but invalidates
  every active session; left as an optional follow-up.
- `cache.serializable_classes` — framework default is `null` (unrestricted, as
  before). Moot here regardless: the app only ever caches booleans (the
  notification dedupe keys).

The L13 "cache prefix / session cookie name" rename is also a no-op here —
`config/cache.php`, `config/database.php` and `config/session.php` have used the
hyphenated form since Phase 1, so no prefix or cookie name changes on deploy.

### Customisations confirmed intact
No config file was touched, so all of these survive verbatim: the
`APP_TIMEZONE` / `Pacific/Port_Moresby` default in `config/app.php` (the stock
skeleton still hardcodes UTC), PNG wall-clock datetime storage,
`config/superadmin.php`, the S3 disk, the database queue, Livewire's 20MB
temporary-upload rule, the `/firebase-messaging-sw.js` route, the
`EnsureUserIsActive` global web middleware, and all four Port-Moresby scheduler
entries.

`bootstrap/app.php` needed no change. The only L13 skeleton delta is an
exception JSON-rendering default for `api/*` routes, which this web-only app
has no use for.

### One user-visible change (framework default, unavoidable)
The password-reset email subject is now **"Reset your password"** (was "Reset
Password Notification"). This is Laravel 13's new default wording. Confirmed in
the log mailer.

### Verified
- **182/182 tests pass** — the same 182 as on main, with no test changes.
  Also run as a baseline on PHP 8.4 *before* touching dependencies (182 passed),
  which rules out PHP-version-only failures.
- `php artisan --version` → **Laravel Framework 13.42.0** on **PHP 8.4.26**.
- `npm run build` succeeds (vite 7.3.6); `laravel-vite-plugin` needed no change.
- Migrations and both seeders run clean.
- All four scheduled commands run manually, exit 0: `events:notify-today`,
  `gym:notify-upcoming`, `clinic:notify-upcoming`, `clinic:prompt-status`.
- **Real FCM round-trip**: a deliberately invalid token was pushed through the
  live FCM API; FCM reported it unregistered and `FcmChannel` pruned the row.
  No errors logged, and the matching in-app (database) notification was stored.
- Manual UI pass, all on Laravel 13: login → calendar, logout, full password
  reset round-trip via the log mailer (link → new password → "Your password has
  been reset"); event create + edit (14:00 entered displays as 2:00pm — no
  timezone drift); weekly recurring gym session create + whole-series edit, with
  occurrences correctly present Tue/Thu and absent Wed; SM Clinic booking with a
  clinician **greyed out and `disabled`** by a genuine cross-module clash (the
  computed gym occurrence, via `StaffAvailability`); file upload through the real
  Livewire path and download returning
  `Content-Disposition: attachment; filename="HPS Training Plan 2027.pdf"`;
  announcement publish → queue worker → bell count 1.
- Capacitor Android app needs no change (remote-URL mode).

### Also verified on MariaDB (2026-11-17, second pass)
The first pass ran on SQLite because XAMPP's MariaDB was unreachable. That was
resolved and **every runtime check was re-run against real MariaDB 10.4.32**:

- **182/182 tests pass against MariaDB** (not just SQLite), run on a dedicated
  throwaway database.
- `migrate:status` against the **real** `hps_staff_app`: all 13 migrations show
  `Ran`, nothing pending — the upgrade needs no schema change.
- Migrations + both seeders run clean on MariaDB.
- All four scheduled commands exit 0; FCM round-trip prunes an invalid token and
  stores the in-app notification.
- UI pass repeated: weekly gym recurrence (present Tue/Thu, absent Wed), SM
  Clinic clinician `disabled` by the cross-module gym clash, announcement →
  queue worker → bell count 1 (no failed jobs), upload + download returning
  `filename="MariaDB Check Document.pdf"`.
- `days_of_week` round-trips correctly as `[2,4]`. On MariaDB 10.4 the JSON
  column is stored as `longtext`, and occurrence expansion happens in PHP (per
  spec, computed at display time) — there is **no** dependency on MySQL JSON
  functions, which is why SQLite and MariaDB behave identically here.
- The L13 joined-`DELETE` change is not reachable: the app issues no `DELETE`
  with `JOIN`/`ORDER BY`/`LIMIT`.

All write tests ran on temporary databases (`hps_staff_app_l13check`,
`hps_staff_app_l13test`), both dropped afterwards. The real `hps_staff_app` was
only ever read.

### Local environment note (not an app issue)
XAMPP's MariaDB cannot bind port 3306 because the **MySQL 8.4 Windows service
(`MYSQL84`, auto-start) owns it**. For this verification MariaDB was started
manually on **port 3307**; that is temporary and will not survive a reboot.
A permanent fix is an environment decision — either stop/disable `MYSQL84` so
XAMPP gets 3306 back as designed, or move XAMPP to 3307 permanently and update
`DB_PORT` in this project *and* the other local projects that use MariaDB
(`nhc_portal`, `periodization_planner`, `renttrack_png`). Nothing was changed
permanently: `.env`, `my.ini` and the MySQL services were all left as found.

### Not verified
- The Android APK was not rebuilt or re-tested (not required — remote-URL mode).
- Nothing was deployed; Laravel Cloud still runs Laravel 12 on its own MySQL.

### PHP floor corrected (2026-11-18)
`composer.json` declared `"php": "^8.3"` after the upgrade, and that was wrong
in practice. **Laravel 13 itself does require only PHP 8.3+** — `laravel/framework`
v13.42.0 declares `php ^8.3` — but the resolved dependency set does not:
`symfony/*` 8.1.x, which Laravel 13 pulls in, requires **`php >=8.4.1`**, and
around eighteen of those components are in the lock.

So `composer install` could never have succeeded on 8.3. Nothing noticed because
every machine that has touched this repo runs 8.5, and Laravel Cloud builds on
8.5 too. The **first run of the new CI workflow** caught it within a minute of
being switched on.

The constraint is now `^8.4.1` — the exact floor the lock imposes, rather than a
rounded `^8.4` that would still fail on 8.4.0. Refreshed with
`composer update --lock`, which changed **only** the lock's content hash and
platform entry: no package version moved.

### Optional follow-ups (not required by Laravel 13)
- `livewire/livewire` 3.8.11 → 4.x — a major migration touching every page.
- `phpunit/phpunit` 12.5.44 → 13.x — Laravel 13's skeleton targets `^12.5`.
- `session.serialization` → `json` for gadget-chain hardening (logs everyone out once).
- The L13 skeleton drops `laravel/sail` and adds `laravel/pao`; our `dev` composer
  script also still uses the explicit `concurrently` form rather than `artisan dev`.
  All left alone deliberately — none affects runtime.

---

## Phase 6 — Staff Availability ✅ (2026-11-18)

Merged to main (PR #3, merge commit `e700da7`) and **deployed to production**.

Leave tracking and the staff roster behind it, built per `../PHASE-6-BRIEF.md`.
Additive: no existing events / gym / clinic / announcements / files behaviour
changed except where the brief required it (the three user columns, the fourth
conflict source, staff pickers, and notification targeting).

### Built

**Roster-only staff** — staff who don't use the app are now real `users` rows
with no way in, so every module keeps pointing at one staff table. Three new
columns: `position` (nullable), `login_enabled` (boolean, default true) and
`email` made **nullable** while keeping its unique index (MySQL and SQLite both
allow many NULLs). `password` stays NOT NULL; roster entries get a random hash
they can never use.

**The security boundary** (the main risk in this phase, and tested hardest):
- `login_enabled` is part of the credential set in `Auth::attempt()`, so a
  roster entry and a wrong password fail **identically** — same generic
  `auth.failed` message, nothing revealing the account exists.
- The same constraint is passed to `Password::sendResetLink()` everywhere it is
  called, so **no reset token is ever issued** for a roster entry. The broker's
  "we can't find that user" response is byte-identical to an unknown email.
- `EnsureUserIsActive` also drops a live session if a login is switched off.
- Six tests pin this down, including one that compares the roster-entry response
  against the unknown-email response directly.

**Access model** — two new gates, both admin + super_admin:
- `manage-availability` — the page, and every leave action
- `manage-roster` — adding and maintaining staff who have no login

Plus a real `UserPolicy`, so the split is enforced in policy rather than UI:
admins may add, edit and (de)activate **roster entries only** and may never
touch a user who holds a login; roles, clinic flags, invites and deletion stay
the super admin's. Plain staff and `can_book` staff get 403 and no nav link.

**Users page (super admin)** — "No login" badge, position column and field, and
a **Send invite** action that flips `login_enabled` and emails the standard
account-setup link, keeping the same row and all its history. "Reset link" is
refused for a roster entry and points at Send invite instead.

**Import list** — paste (`Name, Position, Email`, the last two optional) or CSV
upload (≤1MB, ≤300 rows, header skipped, template downloadable). Every row is
previewed as **New / Duplicate / Invalid** before anything is written; only New
rows are created, all as roster entries. Duplicates match on name
(case-insensitive, trimmed) or email, against both existing users **and rows
already accepted in the same batch**, so pasting someone twice creates them once.

**`staff_leaves`** — one row per absence: user, type (annual / sick / training /
duty_travel / other), `first_day`, `return_date` ("back at work on",
**exclusive**), note, recorded_by, cancelled_at / cancelled_by, indexed on
(user_id, first_day, return_date). Whole days only. Cancelling keeps the row as
history and stops it blocking anything. Validation: return > first (so one day is
the minimum), the person must be active, and no overlap with their own
non-cancelled leave — refused with a message naming the record in the way.

**Conflict engine — a fourth source.** `StaffAvailability::conflicts()` now
includes leave, as `[first_day 00:00, return_date 00:00)`, which drops straight
into the existing half-open overlap rule: "back Wednesday" means free from
Wednesday 00:00, with no special casing anywhere. The label is
`On leave (back 9 Dec)` and **never names the type** — clinic bookers read these
strings. New leave-only helpers (`leaveFor`, `onLeaveMap`, `leaveMap`) answer
"are they away?" while ignoring every other kind of clash, and
`assignmentConflicts()` is the inverse, for the conflict report. The engine stays
module-agnostic.

**Enforcement, which differs by module on purpose:**

| | On leave |
|---|---|
| Event | **Blocks** a newly assigned person |
| Gym, one-off (`none`) | **Blocks** a newly allocated person |
| Gym, recurring series | **Flags** the affected occurrence dates |
| Clinic, single booking | **Blocks** (through the engine) |
| Clinic, recurring rehab | **Flags** (`has_clash`) |

- Only *newly added* staff are blocked. Someone assigned **before** the leave was
  recorded keeps their assignment, gets a warning chip, and never blocks an
  unrelated edit — reassignment is a deliberate act.
- Pickers grey out and disable people on leave, with "On leave · back {D M}",
  re-evaluated live as the dates change.
- Recurring gym series show a live summary before saving and keep it after:
  "Kila is on leave for 3 of 24 sessions: 9, 11, 16 Dec."
- "Away" badges (no reason) on the event and gym detail sheets.

**Stored clinic flags are recomputed.** `has_clash` is set at booking time, so
leave recorded afterwards makes it wrong in both directions. A `StaffLeave`
observer calls `ClinicBooking::recomputeClashesFor()` on create, update and
delete, covering **both the old and the new date range** on an edit — so the
invariant holds wherever leave is written from, including seeders.

**Reminders skip staff on leave** — `events:notify-today`,
`gym:notify-upcoming` and `clinic:notify-upcoming` all check leave at the moment
the event / session / appointment starts. `clinic:prompt-status` is deliberately
unchanged: the outcome still has to be recorded.

**Nothing is ever sent to roster-only staff.** `User::scopeNotifiable()`
(= active + login_enabled) and `isNotifiable()` now sit behind every command and
broadcast, announcements included — otherwise a stale backlog would greet them
on the day they're invited. General announcements still reach staff who are on
leave: leave suppresses reminders about work, not news.

**The page** (`/availability`, Livewire, Modernist + 9 Sep refresh) — a
segmented control over three tabs, remembered per session:
- **Overview** — Away today (with working days left), Returning in the next 7
  days, Upcoming leave (next 30 days), and Assignment warnings: upcoming work
  whose assigned person is away, each linking to the day. Every panel has its
  own empty state ("Everyone's in today").
- **Month** — desktop team grid, staff rows × day columns, Monday weeks,
  weekends shaded, leave drawn as bars with type + back date on hover, prev /
  next / today, a position filter and a "show everyone" toggle (only staff with
  leave that month are listed by default). At ≤640px the grid is replaced by a
  7-day strip: tap a day, see who's away.
- **Staff** — the roster: name, position, Login / No login badge, status,
  working days of leave this year, next leave, search and position filter, plus
  Add staff, Import list, edit and (de)activate. A detail sheet carries the
  profile, leave history (this year, with an all-years toggle), totals by type
  and "Record leave for {name}".

**Record leave sheet** — five fields and nothing else: staff (searchable), type
(text chips), first day away, back at work on (with +1 day / +1 week / +2 weeks
chips), note. Under them, live: "8 working days (10 calendar) · back Wed 2 Dec",
then the conflict report — everything that person is already assigned to in the
window, each row linking to its day. **Nothing is ever auto-removed**, and the
report never blocks saving.

**New hue** — availability is **amber/ochre** (`leave` in `tailwind.config.js`),
the fifth and last colour in the system, clear of the calendar's ink/red, the
gym's blue/green and the clinic's plum. Leave types are told apart by text
chips, never by more colours.

**Working days** — `App\Services\WorkingDays`, Mon–Fri over the half-open
window. PNG public holidays are out of scope, and `config('app.public_holidays')`
is the single empty place they would plug in: fill it and every count in the app
follows.

**Demo seeder** (local-only, as before) — 10 roster-only staff with positions, a
clinic client with three appointments, and 9 leave records spanning past,
current and upcoming of every type, deliberately overlapping existing events,
gym series and clinic appointments so every warning state has something real
behind it. One cancelled record, to show history is kept.

### Verified
- **307 tests passing (723 assertions)** — 125 new, no regressions in any
  existing suite. Engine (13), working days (7), security (6), roster
  management (17), import (15), leave recording (16), enforcement (20),
  notifications (11), page and access (20).
- **Migrations run clean on real MySQL**, not just the SQLite test database:
  first locally on MySQL 8.4 (throwaway `hps_staff_app_p6check`, created and
  dropped; the real local `hps_staff_app` was never written to), then on
  **production's own MySQL 8.0.39** (see below). `email` ends up
  `varchar(255) NULL` with `users_email_unique` **still present**, `position`
  `varchar(100) NULL`, `login_enabled` `tinyint(1)` default 1, and
  `staff_leaves` with the compound index and the right FK behaviours (user
  cascade, recorded_by/cancelled_by set null).

### Verified against production's MySQL 8.0.39 (2026-11-18)

Laravel Cloud's production database is MySQL **8.0.39**, not the 8.4 running
locally, and the `email` ALTER was the one change worth proving on the real
engine. Method, so it can be repeated:

1. Created a throwaway schema `hps_p6_check` on the same cluster
   (`db-a1472ed5…`, which also hosts `main` and `renttrack_png`).
2. Cloned the **live** table structure with
   `CREATE TABLE hps_p6_check.users LIKE hps_staff_app.users` — a real copy of
   the production definition, indexes included.
3. Inserted **50 synthetic rows** (`p6check{n}@example.test`). No production
   user data was copied at any point; production was only ever read, and only
   for a row count.
4. Shipped both Phase 6 migration files into the app container and ran them
   against that schema with Laravel 13 / PHP 8.5, exactly as a deploy would.

Results on 8.0.39:
- Both migrations applied cleanly (136ms and 363ms).
- `email` → `varchar(255)`, nullable; `position` → `varchar(100)`, nullable;
  `login_enabled` → `tinyint(1)`, NOT NULL.
- **`users_email_unique` survived the ALTER**, alongside `primary`.
- All 50 rows intact, none altered.
- Three roster-only rows with `email = NULL` then inserted happily side by side
  under the unique index — the behaviour the whole design depends on.
- `staff_leaves` created with all 11 columns, the compound
  (user_id, first_day, return_date) index, and FKs: user_id cascade,
  recorded_by / cancelled_by set null.

**⚠️ Rollback is one-way once roster staff exist.** `migrate:rollback` on the
Phase 6 batch was tested on 8.0.39 with three roster-only rows present and
**failed**: `SQLSTATE[22004] … 1138 Invalid use of NULL value` on
`alter table users modify email varchar(255) not null`. Worse, Laravel rolls
back in reverse order, so it had already **dropped `staff_leaves`** before
reaching the failure, leaving `email` nullable, the columns in place, one
migration still recorded as ran, and every leave record gone.

The `down()` now checks for users without an email and throws an explicit
message instead of letting MySQL raise SQLSTATE[22004] — but that only makes
the failure legible, it cannot save the leave records dropped by the other
migration first. **After this is live, recover with a database snapshot, not a
rollback.** The cluster takes scheduled snapshots with 1-day retention.
- **Browser-checked** against the seeded demo data: the Overview panels, the
  desktop month grid (bars, shaded weekends, position filter, show-everyone),
  the Staff roster with Login / No login badges and policy-correct actions (a
  login account offers only "Leave" to an admin), and the Record leave sheet
  with its live summary and conflict report ("Anna Temu is assigned to 60 items
  during this leave").
- **380px**: `document.scrollWidth === clientWidth === 380` on every tab — no
  horizontal overflow — and the smallest visible tap target measured **44px**.
  The month grid is replaced by the day strip, as designed.
- `npm run build` compiles the new `leave` hue into the production CSS.

### Deployed (2026-11-18)

Deployment **#32** (`e700da7`) succeeded in **1m34s** via push-to-deploy. The
deploy command ran `php artisan migrate --force`, applying both Phase 6
migrations to the live database.

Post-deploy checks against production:
- `migrations` = 15 (13 + the two Phase 6)
- `users` = 3, **all three `login_enabled`** and active — nobody was locked out
  by the new column, which defaults to true
- `users_email_unique` still present; `email` now nullable; no NULL emails yet
- `staff_leaves` present, 0 rows
- Route `GET availability` registered and resolving to the Livewire page
- `manage-availability` gate registered; `User::notifiable()` returns 3
- All four scheduled commands still listed and due normally

`https://hps-staff-app-production-nq8tav.laravel.cloud/login` returns 200.

**Nothing has been created in the module yet** — no roster staff, no leave. The
first real use will be an admin adding roster staff from the Staff tab.

### Notes for later
- The Assignment warnings panel lists the first 20 and then says "and N more in
  the next 30 days". With the demo data a week of leave against daily gym series
  produces ~90 warnings, which is correct but unreadable in full; the count in
  the panel header stays exact.
- Allocation is per gym series, so leave cannot remove one person from one
  occurrence — flagged instead, per the brief. Per-occurrence exceptions remain
  out of scope.
- Duplicate detection treats a repeat **within one import batch** as a duplicate
  as well. The brief only specified matching against existing users; this seemed
  plainly right, and is tested.
- Still out of scope and not designed around: staff requesting their own leave
  with approval, entitlements / balances, part-day leave, and exporting leave
  reports.

---

## Phase 6.1 — Leave stats & allowances ✅ (2026-11-18)

Merged to main (PR #4, merge commit `1058e90`) and **deployed to production**.

Two small additive parts, built to `../PHASE-6.1-BRIEF.md` on top of Phase 6.

### Part A — clinic services after every deploy

**The incident.** On 18 Nov 2026, shortly after Phase 6 went live, production's
SM Clinic booking form showed an **empty service dropdown**. Nothing in Phase 6
caused it: Laravel Cloud runs `migrate` on deploy but **never runs seeders**, and
`ClinicServiceSeeder` had simply never been run against production since the
clinic shipped. Jason fixed the live site by hand with
`php artisan db:seed --class=ClinicServiceSeeder --force`.

**The fix.** `2026_11_18_200001_ensure_clinic_services_exist` is a data migration
that inserts the six services, so a rebuild, a new environment or a staging copy
can never start without them.

- **Insert-only, matched on `name`.** Existing rows are never updated, so
  durations Jason tunes in production survive — and on production the migration
  is a **no-op**, which was verified against the live data before it was written
  (all six present, 30 minutes, Rehabilitation recurring).
- Uses the **query builder**, not the Eloquent model, so a later change to
  `ClinicService` cannot break a migration that has already run elsewhere.
- `down()` deliberately does nothing: appointments reference these rows.
- `ClinicServiceSeeder` stays for tests and local setup.

**⚠️ Never run a plain `db:seed` in production.** `SuperAdminSeeder` uses
`updateOrCreate`, so it would reset the super admin's password to whatever
`SUPER_ADMIN_PASSWORD` currently holds. `DatabaseSeeder` now carries that
warning in a comment, and nothing in the deploy pipeline runs seeders.

### Part B — leave stats & allowances

**The rules**, all locked in scoping and now enforced in one place:

| | |
|---|---|
| Allowance year | Calendar year; leave spanning New Year counts its own days in each |
| Annual | **15 working days** per year |
| Sick | **10 working days** per year |
| Training / Duty travel / Other | No limit — counted and shown only |
| Counting | Working days (Mon–Fri) in `[first_day, return_date)`; **future booked leave counts as used**; cancelled never counts |
| Carry-over | None |
| Over the limit | **Warn, then allow** — never block |
| Applies to | Every staff member, roster-only included; no pro-rata |

**`App\Services\LeaveAllowance` is the single source of truth.** The year-clipping
that used to sit in `StaffAvailabilityPage::workingDaysThisYear()` and again in
the detail sheet now lives here, once, so the Staff tab column, the detail sheet
and the stats panel cannot disagree — there is a test asserting exactly that.
It offers `usedDays` / `takenDays` / `bookedDays`, `remaining` (null when
unlimited, negative when over), `summaryForYear()` for the whole table in a
**fixed number of queries**, and `projectionFor()` / `projectedOverage()` for the
warning, with `ignoreLeaveId` so an edit never counts a leave against itself.

**Limits live in `config/leave.php`**, with `LEAVE_ANNUAL_DAYS` /
`LEAVE_SICK_DAYS` env overrides, read through a new `LeaveType::allowance()`.
A type absent from the config has no limit, so adding or removing one is a
config change rather than a code change.

**The Leave stats panel** sits full width under the Overview's four panels:
- a **year selector** that stops at the earliest year with any leave, plus a
  "This year" shortcut;
- a **team summary** strip of per-type totals and "N staff over a limit";
- a **per-staff table**: Annual and Sick as "used / limit" with a slim bar,
  "N left", and "N taken · N booked" underneath; the unlimited types as plain
  days or a muted "—"; and a combined total;
- caution styling at **≥80%** of a limit, and a deeper red with **"+N over"**
  past it — the text differs in all three states, so colour is never the only
  signal and the over-limit red can't be mistaken for a brand-red button;
- search, position filter, a "Show deactivated" toggle (active-only by default,
  so everyone with their full allowance intact is visible), and sorting by name,
  annual, sick or total;
- **phone cards** below 640px: name and total, two allowance bars, then one line
  for the other types.

**Warnings when recording leave.** Once staff, type and both dates are set, the
sheet shows a live allowance line ("Annual: 9 of 15 used in 2026 · this leave
uses 4 → 2 left"). If it would exceed the limit in any affected year it warns
instead ("This takes Kila Vagi to 18 of 15 annual days in 2026 (3 over).") and
requires a **"Record anyway"** tick. The check is **repeated server-side** and
the acknowledgement is cleared whenever the staff member, type or either date
changes, so a stale form cannot skip it. Unlimited types show nothing.

**The staff detail sheet** gained the same used / limit / remaining display and
now follows the stats panel's selected year, as does the Staff tab's column.

### Verified
- **366 tests passing (888 assertions)** — 52 new, no regressions.
  Allowance service (27), stats panel and over-limit flow (25), plus Part A (7).
- The N+1 guard is a real assertion: `summaryForYear()` runs the **same single
  query** for 2 staff and for 25.
- Time is **frozen** in the allowance tests (`2026-06-15`), since the
  taken/booked split is defined against today.
- The New Year split is checked both ways: 22 Dec 2026 → 6 Jan 2027 counts 8
  days in 2026 and 3 in 2027, and the two always sum to the 11-day whole.
- **Browser-checked** against the extended demo seeder: the panel shows Grace
  Oala at 12/15 (caution, "3 left", 12 taken) and Ruth Kaupa at 13/10 (red,
  "+3 over"), with Kia Loca showing 0 taken · 5 booked.
- **380px**: no horizontal overflow, and every tap target ≥44px (the only
  element below it is the raw checkbox input, whose clickable label is 44px).

### Notes for later
- The stats panel has its **own** search and position filter rather than sharing
  the Staff tab's. The brief suggested reusing them, but those controls only
  render on the Staff tab, so a shared binding would mean a filter set there
  silently narrowing the Overview.
- The stats panel is **active-only by default** while the Staff tab still lists
  everyone; the toggle covers the difference deliberately.
- `summaryForYear()` was kept shape-agnostic because CSV export is the likely
  next step — it already returns everything such an export would need.
- `clinic_services.name` has no unique index, so Part A is a select-then-insert
  and races in theory. One deploy process makes that moot; adding a unique index
  to a live table seemed the larger risk.

### Deployed (2026-11-18)

Deployment **#36** (`1058e90`) succeeded in **1m31s**. It was the first change
to reach production through CI: GitHub Actions ran the full suite on PHP 8.4
and 8.5 (**366 tests, 888 assertions**, green on both) before the merge.

Post-deploy checks against production:
- `migrations` = 16 — the one new migration being Part A's clinic services.
- **Part A was a true no-op**, as designed: still exactly 6 services, all
  durations still 30 minutes, nothing overwritten.
- 14 users and the single real leave record untouched.
- `config('leave.allowances')` resolves to annual 15 / sick 10 on the server.
- `LeaveAllowance::summaryForYear()` run against the live data returns 14 staff,
  10 working days team-wide and **0 over a limit** — the one record being
  Jayyson's annual leave, 23 Nov → 7 Dec, 10 working days. He will read as
  10 / 15 with 5 left, below the 80% caution threshold.

### Continuous integration (2026-11-18)

`.github/workflows/tests.yml` runs the suite on every pull request and on
pushes to `main`, against **PHP 8.4 and 8.5**. Until it existed, every deploy
— including both Phase 6 deploys — went to production on local test runs
alone.

It earned its place on the first run. The matrix originally tested **8.3**, the
floor `composer.json` declared, and that job failed at `composer install`:
`symfony/http-foundation v8.1.11 requires php >=8.4.1`. The lock had never been
installable on 8.3. See "PHP floor corrected" in the Laravel 13 section.

## Account setup replaces the reset email (2026-11-18)

### Why

Adding a user from the Users page emailed them Laravel's stock password-reset
notification: subject "Reset your password", body "we received a password reset
request for your account", and the line "If you did not request a password
reset, no further action is required." Nobody had requested anything — the
account had just been created for them — so the first thing a new staff member
read was an email telling them they could ignore it. The link also expired in
60 minutes, which is right for a reset and far too short for onboarding
someone who may open their inbox that evening.

That was the spec's own choice ("prefer the reset-link flow"), not a defect,
and it had worked since Phase 1. It is now replaced.

### What a new account gets

`App\Notifications\AccountSetupInvitation` — subject **"Set up your HPS Staff
account"**, a **"Set up your account"** button, and copy that says an account
has been created and choosing a password activates it. No reset wording
anywhere. It states the 24-hour window and who to ask for a fresh link.

Setting the password **is** the activation: no email-confirmation step, no
second factor. The invitee lands on `/set-password/{token}`
(`pages.auth.setup-account`), a guest page of its own with its own heading and
an **Activate account** button, and is redirected to the login screen with
"Your account is ready. Sign in with your new password."

Both routes into an account use it:
- **Create user** (super admin) — the row still gets a random 32-character
  password nobody knows; the email is the only way in.
- **Send invite** on a roster entry — flips `login_enabled` and sends the same
  email, as before keeping the row and everything assigned to it.

**Password reset is untouched.** `/forgot-password` → stock Laravel email →
`/reset-password/{token}`, 60 minutes, exactly as it was.

### The 24 hours, and why a second token table

The window is a property of the **broker**, not the token, so the two flows
needed two brokers: `users` (60 min) and the new `invites`
(`AUTH_INVITE_EXPIRE_MINUTES`, default 1440). They also get **separate tables**
— `password_invite_tokens`, added by
`2026_11_18_300001_create_password_invite_tokens_table`.

Sharing one table would have been simpler and wrong: a genuine reset token,
dead at 61 minutes on its own page, could have been redeemed through the setup
page for a further 23 hours. Separate stores make each token meaningless to the
other page, and an invite now survives the staff member trying "forgot
password" first. Two tests pin both directions.

An expired or reused setup link does not say "invalid token" — it says the link
has expired or was already used, and to ask an administrator for a new one,
which is what has actually happened.

`login_enabled => true` stays in the credentials on the `invites` broker, so a
roster entry still cannot be handed a token by any path.

### Verified

**376 tests, 926 assertions** — green. Ten new ones in
`tests/Feature/Auth/AccountSetupTest.php`: the email's subject, button, URL and
24-hour line; the token landing in its own table; activation at 23 hours;
refusal at 25; a reset token rejected by the setup page and a setup token
rejected by the reset page; and no token for a roster entry. Existing
expectations of `ResetPassword` on create and invite were switched to
`AccountSetupInvitation`.

### Deployed (2026-11-18)

Merged to main (PR #7, merge commit `5e2e747`). Deployment **#38** succeeded in
**1m25s** via push-to-deploy, after CI ran the suite green on PHP 8.4 and 8.5.

Post-deploy checks against production:
- `migrations` = 17, the newest being
  `2026_11_18_300001_create_password_invite_tokens_table`.
- `password_invite_tokens` exists.
- `config('auth.passwords.invites.expire')` = **1440** on the server.
- `route('password.setup', ...)` resolves to
  `/set-password/{token}?email=...` on the production domain.
- 16 users, untouched.

Not verified: no real setup email has been sent from production yet. The next
user added — or a Send invite — is the first live test of the new copy. Mail
still goes out over Gmail SMTP, unchanged by this work.

## User deletion: a readable refusal, and no self-delete (2026-11-18)

### What deletion actually did

Asked to confirm whether deleting a user is soft or hard, the answer turned out
to be neither clean nor safe:

**Nothing in this app is soft-deleted.** No `SoftDeletes` trait, no
`deleted_at` column, no restore. A deleted user is gone.

For a plain staff member that works as intended — device tokens, event and gym
assignments and their own leave records all cascade away. Leave they *recorded
for someone else* survives with `recorded_by` nulled, which is right. Their
in-app notifications are left orphaned: `notifications` is polymorphic and has
no foreign key, so nothing sweeps them.

**But anyone who had authored anything could not be deleted at all.**
`created_by` on events, gym schedules and announcements, `uploaded_by` on
documents, and `assigned_staff_id` / `booked_by` on both clinic tables are all
`constrained('users')` with no `onDelete` rule — RESTRICT. A probe confirmed
it: deleting an admin who had posted one announcement threw
`QueryException: FOREIGN KEY constraint failed`, the row stayed, and
`ManageUsers::delete()` had no try/catch, so it surfaced as a raw 500. With
`APP_DEBUG` off in production that is a blank error page and no explanation.

In practice every admin and super admin was undeletable, and the failure looked
like a bug rather than a rule.

### The refusal, in words

`User::deletionBlockers()` counts the content that restricts, and
`ManageUsers::delete()` checks it before trying:

> Kila Morea cannot be deleted: they created 3 events and 1 announcement that
> would be lost. Deactivate them instead — that blocks their login and
> notifications, and keeps the record.

A `QueryException` catch sits behind it as a backstop, in case a restricting FK
is added later and the list is not updated. **Keep `deletionBlockers()` in step
with every restricting FK pointing at `users`.**

Deactivating was already the right answer for almost every real case: it stops
the login, stops notifications, and keeps the history intact. The message now
says so.

### Self-deletion removed

`resources/views/profile.blade.php` still carried Breeze's stock **Delete
account** form, so any staff member could permanently erase themselves with
nothing but their password — past the rule that only the super admin deletes
accounts, and past the rule that an admin may never delete someone who holds a
login. It called the same raw `->delete()`, so it had the same two outcomes:
silent erasure, or a 500.

The form and its component are gone, with a comment in the Blade saying why. A
test asserts the profile page offers no way to delete your own account.

### Not changed

Hard deletion is still hard deletion, and the orphaned notification rows are
still orphaned. Whether "delete" should become "deactivate forever" across the
board is a larger question, left alone: `is_active` + `login_enabled` already
cover the real need, and hard-deleting someone who booked clinic appointments
would destroy clinical records.

### Verified

**386 tests, 943 assertions** — green. Eleven new ones in
`tests/Feature/Users/DeleteUserTest.php` cover the clean cascade, the surviving
`recorded_by` record, a refusal for each kind of authored content (data
provider), a clinician holding appointments, the counted wording, the content
being left intact, and deactivation still being available when deletion is not.
`ProfileTest` lost its two self-delete tests and gained the guard.
