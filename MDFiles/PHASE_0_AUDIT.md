# Laravel Blade admin — Phase 0 audit

Date: October 18, 2026. Status: audit complete; Phase 1 awaits approval.

The full `LARAVEL_RSVP_ADMIN_MVP_PLAN.md` and `backend/AGENTS.md` were read. No application code, dependency, credential, route, or database record was changed. This report is the only new workspace deliverable. Laravel Boost tools were unavailable; existing Artisan metadata commands and targeted source inspection were used. `.ai/rules` and local skill files were not present; tracked skill deletions were preserved.

## Verified baseline and rollback

- Branch: `main`; commit: `a757142b78980597ca2a3f7449a3602d5429a58c`.
- Working tree is already dirty: frontend redesign changes/assets, backend Procfile and plan, deleted example environment/skill files, and unrelated copies/archive. Nothing was staged, committed, restored, or deleted.
- Verified snapshot: `C:\Users\Jayjay\Documents\WorkProjects\planned_rsvp-redesign-backups\laravel-admin-phase0-20261018-164309`.
- **118 source/config/asset files** copied and SHA-256 verified. Includes current backend application, routes, migrations, tests, resources, relevant manifests/configuration, and frontend source. `manifest.json` records exact files/hashes; `git-status.txt` records pre-existing work; `baseline.txt` describes exclusions and restoration. A restore-copy probe passed without overwriting any application file.
- Excludes `.env`, sensitive `config/admin.php`, runtime caches/logs, database files, dependencies, and build outputs. Credentials remain in place and untouched. Do not use this snapshot to restore excluded files.
- Restore only reviewed files changed by the approved phase from `files/<relative path>`. Review later diffs first; separately remove only that phase's new, unused files. Never reset/clean the tree or overwrite unrelated work.
- **This is a source rollback point, not a database backup.** Before any approved schema migration, take a protected database backup and verify restoration on a separate database. Do not place guest data, password hashes, session contents, or tokens in source control or audit output. Reverting migrations is not a substitute for restoring data.

## Architecture and dependency map

Installed PHP packages: Laravel **13.36.0**, Sanctum **4.3.3**, PHPUnit **12.5.39**. Existing backend package declarations provide Tailwind 4, Vite 8, and Laravel Vite plugin 3; backend Node dependencies are not installed and no backend lockfile was found. Installation/build compatibility needs verification during approved Phase 1, without running Composer's destructive setup script.

```text
React App.tsx
  /admin* -> AdminApp.tsx -> api.ts -> /api/auth/* and /api/admin/*
  public  -> InvitationExperience -> InvitationPage -> public RSVP APIs

api.ts -> CSRF cookie + credentialed fetch + X-XSRF-TOKEN
Laravel bootstrap -> statefulApi -> Sanctum -> web session guard -> users
API controllers -> owner-scoped eventProjects -> guests/invitations/rsvp_responses

Proposed: Laravel /admin/* -> web middleware -> same session identity
          -> active-account checks + policies -> Blade + Tailwind + Vite
```

Laravel currently serves only a welcome Blade page at `/`; there are no Blade admin routes, admin layouts, policies, Form Requests, or shared reservation services. The resources directory has starter CSS/JS and welcome view. Reuse the existing Tailwind/Vite pipeline; no admin framework or image generation is required.

## Database findings

Read-only `db:show`, `db:table` and `migrate:status` succeeded against the configured MySQL `rsvp_app` database. All **10 migrations** are applied. Metadata, not guest records or credentials, was inspected. Live columns/indexes/foreign keys for users, event projects, guests, invitations and RSVP responses match the inspected migration design.

| Existing table | Purpose and constraints |
| --- | --- |
| `users` | Identity, unique email and nullable unique username, hashed password. No role, active state, soft deletion, or last-login fields. |
| `event_projects` | Owner-scoped event; draft/published/archived; public/private; single venue/time range; deadline and party-size rules. |
| `occasion_labels` | Ordered labels belonging to an event. |
| `guests` | Existing primary contact: name, optional email/phone, event relationship. Reuse this table. |
| `invitations` | Event/guest relationship, unique SHA-256 token hash, active/revoked state. |
| `rsvp_responses` | Reservation equivalent; attending/declined, party size, existing optional notes/message/additional names. Unique response identity per event. |
| `sessions`, `password_reset_tokens`, `personal_access_tokens` | Existing session/reset/token infrastructure; preserve. |

One response currently represents a primary contact and potentially a party. The latest React form submits **one attendee when attending, zero when declined**, with no additional names. Existing stored group responses must remain intact. The backend still enforces event-specific party limits; the frontend restriction alone does not enforce a universal server limit. Use event settings `allow_plus_ones=false` / `max_party_size=1` for this event after approval; do not silently update existing events or records.

There is no pending RSVP enum value: existing pending metric counts active invitations without a response. Preserve that meaning. There is no cancellation state, reservation reference, capacity, audit table, or soft-delete support. Do not represent cancellation as a guest decline or fabricate pending response records.

## Authentication and security findings

- Existing API login checks a hashed password by username/email, logs into the `web` guard, and regenerates the session. Logout invalidates the session and regenerates CSRF state. API login is throttled; protected endpoints use `auth:sanctum`.
- Stateful API middleware and credentialed CORS already exist. Preserve these and the public client's CSRF behavior. Blade forms must use web CSRF middleware and reuse the same identity store.
- Existing admin data access is owner scoped in controllers, not role/policy based. There is no active-account enforcement. New role/deactivation restrictions must also cover legacy API access while React admin remains available.
- Password/remember-token model fields are hidden. Public event details use an explicit allowlist. Private invitation secrets and public response keys are stored as hashes. Do not expose those hashes through new Blade views/audit metadata.
- `config/admin.php` has a nonempty password fallback. Its value was not displayed or copied into the snapshot. Propose removing the fallback through an approved, focused change; preserve `.env` and existing account passwords. Seeder reruns currently preserve existing users and token rows.
- Public save uses a transaction and repeat-response keys. Extract shared business logic with compatibility tests; do not duplicate it for Blade. Review concurrent first submissions: locking a missing response does not by itself guarantee a graceful retry on a unique-key race.
- Existing admin response edits update guest and response separately; future shared writes should be transactional. Invitations are also created across separate writes.
- Existing event/user foreign keys cascade deletes. Account deactivation is safer than deleting accounts; never expose destructive account deletion as a shortcut.
- Event address validation permits 500 characters but the database column is 255. Start/end time ordering is not validated. Resolve with focused validation/schema proposals in the relevant later phase.
- Existing feature coverage addresses public/private access, repeat updates, deadlines/party limits, ownership, login, token hashing, metrics, CSV safety and preservation during seeding. Tests were inspected, not rerun in Phase 0. Earlier QA results are historical evidence, not a fresh pass.

## Routing and public integration risks

- React `App.tsx` currently claims every pathname starting with `/admin`. Laravel `web.php` has no admin route. Do not remove React admin yet.
- `backend/Procfile` points Apache at `public/`; `.htaccess` forwards Laravel requests to its front controller. No production reverse-proxy/static-host routing configuration was found. Actual hosting ownership of `/admin` is not verified.
- Build and test Blade on the backend origin first. During Phase 5, verify the real host routes `/admin` and `/admin/login` to Laravel before removing React admin. If separate origins prevent this, obtain approval for the hosting/subdomain arrangement.
- Keep `/api/events/{slug}`, `/api/invitations/{token}`, and both RSVP POST endpoint paths and response shapes. Keep shared `frontend/src/api.ts` after React admin removal.
- The homepage is a non-submitting sample. Published events load saved API data. Two customized venues exist only in the sample; current schema/API expose one venue. Honoree/contact/capacity fields are also unsupported. Do not imply new admin fields will update these without an approved contract/schema change.

## Minimum phased implementation proposal

**Phase 1 only after approval:**

1. Add Blade `admin` layouts/components, login view, responsive shell, protected landing placeholder, ocean-teal operational styling, accessible mobile drawer, and existing Vite resource entries. No React admin UI or new illustration work.
2. Add `Admin` session controller and Form Requests; named `admin.*` web routes; CSRF, login throttling, session regeneration and POST logout. Preserve current API endpoints.
3. Propose an additive users migration for `role` and `is_active`. Existing users retain access as active staff; do not automatically promote everyone. Bootstrap one explicitly selected existing organizer as Super Admin through a controlled command without password/token changes. No account identifier has been selected in this audit.
4. Add policies and active-account checks for Blade and existing API paths. Default staff scope remains their own events; propose Super Admin access across events, with explicit tests. Do not expose user-management actions to staff.
5. Add authentication/authorization/CSRF/session/API-compatibility feature tests. Verify responsive shell at 320, 375, 390, 768, 1024 and 1440px. Read the applicable testing skill if available before writing tests; obtain missing guidance if required. Install only the already-declared backend Node dependencies after approval and verify runtime compatibility.
6. Verify database backup restoration before applying the additive migration; rehearse migrations and source rollback on an isolated copy. Provide the shell demo and stop for Phase 2 approval.

Likely Phase 1 paths: `routes/web.php`, `routes/api.php` (only needed enforcement), `bootstrap/app.php`, `app/Models/User.php`, `app/Http/Controllers/Admin/*`, `app/Http/Requests/Admin/*`, middleware/policies, a controlled role-bootstrap command, additive user migration, `resources/views/admin/*`, `resources/views/components/admin/*`, `resources/css/app.css`, `resources/js/app.js`, and feature tests. Exact file list will be recorded in the next phase snapshot before edits.

**Later approved phases:**

- Phase 2: dashboard and reservations using existing relationships; shared transactional RSVP service, pagination/filter allowlists, cancellation/soft-delete and audit schema proposals. Preserve public attending/declined semantics and handle cancellation/re-submission deliberately. Capacity remains out of scope unless approved and modeled.
- Phase 3: Users and Guests, deactivation/last-Super-Admin protections, existing guest records, audit events for critical writes. No duplicate guests table.
- Phase 4: supported event settings and profile/password management; no invented public fields. Define affected-session handling explicitly.
- Phase 5: actual hosting route cutover, React admin removal only after verification, full API/browser regression and responsive/accessibility QA. Deployment needs separate approval.

Critical audit recording should begin with the first new privileged writes; settle its minimal additive schema in the approved phase that introduces those writes. Do not log credentials, tokens, or raw guest payloads.

## Approval checkpoint

Phase 0 is complete. Await user approval for the Phase 1 scope above. Before migrating, require a verified protected database backup and an explicit choice of the existing account to bootstrap as Super Admin. Hosting topology must be resolved before Phase 5, not guessed during Phase 1.
