# Phase 1 — Blade administration foundation

Date: October 18, 2026. Status: implemented and validated; stop before Phase 2.

## Delivered

- Laravel Blade/Tailwind admin at the **Laravel backend's `/admin`**, with a named `/admin/login` route and POST logout. Existing React `/admin` remains on the frontend origin until Phase 5 hosting cutover.
- Ocean-teal/deep-blue shell, grouped navigation, active links/tooltips, desktop sidebar collapse, profile dropdown, skip link, and mobile drawer with inert background, Escape dismissal, focus restoration and Tab wrapping. Shared Blade field, card, button and SVG icon components. No admin React, generated images, external fonts, or additional application packages.
- Existing `web` guard/session identity reused. Login session regeneration, CSRF/origin protection, generic credential errors, password input excluded from old input, and throttling. Active-account enforcement applies to both Blade and existing authenticated API routes.
- Additive `users.role` (`staff` by default) and `users.is_active` (true by default). Roles remain excluded from mass assignment. Unknown roles and inactive accounts cannot access administration.
- Event policy and scoped queries: staff retain owner-only access; active Super Admin can access all event projects. Users navigation and route require the Super Admin policy. No new user-management writes yet.
- User selected `admin@example.com` as the initial Super Admin. That existing account was promoted; its password, remember token, and all personal-access-token rows were verified unchanged. Other existing users remain active staff.
- Minimal `admin_audit_events` records for successful login/logout and initial role bootstrap; no passwords, tokens, request payloads, or guest details recorded. Audit viewer and other critical-write events belong to their later approved modules.
- Controlled `rsvp:bootstrap-super-admin {identifier}` command: requires an unambiguous active existing account and no other active Super Admin, and is idempotent for the selected Super Admin.
- Removed the nonempty password fallback from `config/admin.php` after a hash-verified private backup. `.env`, APP_KEY, database account credentials and existing user passwords were not changed.

The dashboard and sidebar destinations are clearly labeled foundation placeholders. Metrics, reservation CRUD, users CRUD, guests CRUD, event editing and profile editing are **not implemented** in Phase 1.

## Verification

- Full existing and new PHPUnit suite: **32 tests, 186 assertions**, isolated in-memory SQLite. Includes 15 new foundation tests and existing public/private RSVP, ownership, session login, repeat-response, deadline/party-size and seeder/token-preservation coverage.
- New tests cover guest redirects, validation/rate limiting, session regeneration, inactive and unknown roles, legacy API access blocking, XSS escaping, Super Admin/staff boundaries, credential-preserving bootstrap/logout and forged login/logout rejection with the test-only CSRF bypass disabled.
- Existing temporary Edge/Playwright tools: **12 checks passed** with a disposable SQLite server and fixture accounts. No real guest data used. [Results](phase1-qa/browser-results.json).
- Rendered login and shell checked at **320, 375, 390, 768, 1024 and 1440px**. No horizontal overflow; desktop widths measured at 256px expanded / 88px collapsed. Real HTTP Blade session accepted by Sanctum; untrusted-origin logout returned 419; staff Users direct access returned 403.
- [Desktop shell](phase1-qa/shell-1440.png), [mobile shell](phase1-qa/shell-390.png), [login](phase1-qa/login-1440.png). Screenshots contain disposable QA accounts only.
- Tailwind/Vite production build passed using temporary official **Node 22.22.0**. The global Node 22.11 is below Vite 8's supported version; use Node 20.19+ or 22.12+ for future builds. No global Node installation was changed.
- Existing backend dependencies installed; `package-lock.json` created. npm 10 hit a resolver error; temporary npm 11 completed installation. npm omitted the Windows Rolldown optional binding under the older Node runtime; the dependency's matching native binding was fetched without adding a new root dependency. Install under a supported Node version for reproducibility.
- Laravel Pint passed after formatting; `git diff --check` passed. Public React source remains byte-identical to the Phase 1 snapshot.
- Physical-device/screen-reader and production reverse-proxy/deployment verification remain later QA; no production deployment is claimed.

Browser harness: `tests/Browser/admin-foundation.cjs`; requires the existing temporary Playwright installation, Edge, and a freshly provisioned disposable server/credentials. It contains no credentials and is not part of the production bundle. Disposable server/SQLite credentials are removed after QA.

## Database rehearsal incident and reconciliation

The first rehearsal reused the source connection configuration under another alias but retained its internal `name=mysql`. Laravel's migration connection resolution consequently added the planned user columns and empty audit table to the configured source database, while migration history remained pending. Further source writes were stopped when detected.

The original pre-change SQL backup was restored into a new disposable database. Isolation was corrected by explicitly setting and asserting **both the connection name and database name**. All original rows and columns across **15 tables** matched the original backup. Both migrations and their rollback then passed on the restored copy, with original records preserved. The source's already-applied additive schema was inspected, its two migration-history entries reconciled, and only then the explicitly selected organizer was promoted. All 12 migrations now show applied. No guest, event, RSVP, password or token record was deleted or replaced. Disposable restore databases were removed.

## Verified restore points

1. Source: `C:\Users\Jayjay\Documents\WorkProjects\planned_rsvp-redesign-backups\laravel-admin-phase1-before` — **118 SHA-256-verified files**, manifest, original dirty Git status and restore-copy probe. Existing Phase 0 and underwater restore points remain intact.
2. Protected database/config directory: `C:\Users\Jayjay\Documents\WorkProjects\planned_rsvp-redesign-backups\laravel-admin-phase1-db-20261018` — Windows inheritance removed; current user granted access. Contains private SQL snapshots and a hash-verified copy of the pre-change admin config. Never commit or publish this directory.
3. Original SQL backup: `before-phase1.sql`, SHA-256 `50c9b3eb4be430f13eece74a9bce8e1e2a1226eceee9113b3eee5a93e64c8d34`. Its restore was verified against original records. `before-phase1-retry.sql` is a later diagnostic snapshot; use the original for the pre-phase baseline.

Source rollback: compare later changes, restore only Phase 1 modified files from `files/<relative path>`, and remove only new Phase 1 files no longer referenced. The sensitive config's original is only in the protected directory; do not copy it into general source backups. Prefer a code rollback while leaving additive schema/data intact. Do not blindly restore the entire SQL backup or roll back migrations on the live database: that could discard later responses, roles or audit data. Obtain explicit authorization and rehearse any data rollback separately.

Modified baseline files: `bootstrap/app.php`, `routes/web.php`, `routes/api.php`, `app/Models/User.php`, `app/Models/EventProject.php`, `app/Providers/AppServiceProvider.php`, the three existing API auth/event/invitation controllers, `config/admin.php`, `resources/css/app.css`, `resources/js/app.js`, and `vite.config.js`.

New Phase 1 files: admin session/foundation controllers, login Form Request, active-account/legacy-policy middleware, EventProject/User policies, authentication service, audit model, bootstrap command, the two `2026_10_18_08473*` migrations, Blade admin views/components, `tests/Feature/AdminFoundationTest.php`, browser harness/results/screenshots, backend npm lockfile, and this report. No unrelated changes were restored, staged or committed.

## Next approval

Phase 2 adds real dashboard metrics and reservations using the existing schema. Present and approve cancellation/soft-delete and shared reservation-service changes before implementing them. Keep the public attending/declined contract, one-guest event requirement and existing stored group responses intact. Do not remove React admin before verified Phase 5 cutover. Actual hosting configuration is still unknown.
