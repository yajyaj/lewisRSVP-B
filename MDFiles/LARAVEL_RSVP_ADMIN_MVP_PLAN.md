# Gather RSVP — Laravel-Only Admin MVP Plan

> **Operating role:** Behave as a **Senior Laravel Backend Developer**, **Senior Frontend Developer for Laravel Blade**, **Database Architect**, **Security Engineer**, and **QA Lead**. Inspect first; propose a narrow change set; implement in approved phases. **Do not start coding merely because this plan was read.**

## 1. Goal and boundaries

Deliver a secure, production-ready **minimum viable admin panel** for the existing Under the Sea first-birthday RSVP website. The **public website remains ReactJS + Tailwind CSS**; the **admin UI is entirely Laravel Blade + Tailwind CSS + Vite**, with optional Alpine.js for lightweight UI behavior. MySQL and the existing Laravel project remain the source of truth. No React, Vue, Inertia, or SPA for admin.

The Laravel application will serve the protected `/admin` pages. **Remove the old React `/admin` route only after verifying that Laravel's `/admin` URL works in the actual hosting/reverse-proxy configuration.** Preserve all public RSVP endpoints and integrations.

### Must preserve

- Existing reservation records, guest information, API contracts, business rules, event content, and working public React RSVP flows.
- Existing authentication and **Sanctum cookie-based SPA session / CSRF flow**, if present. Audit before modifying; do not casually replace or remove Sanctum middleware or shared session behavior.
- Existing `APP_KEY`, `.env`, secrets, tokens, CSRF/XSRF cookies, sessions, API keys, and credentials. **Never regenerate, rotate, print, paste into prompts, log, commit, or expose them.** “Preserve token” also means **minimize AI context/token usage**: read only relevant files, summarize findings, use focused diffs, and implement one module at a time.
- Unrelated working code, styles, routes, and data. Never run destructive migrations or Git commands without explicit approval.

## 2. MVP navigation (left sidebar)

**Always visible to authorized roles:**

1. **Dashboard** — reservation and guest KPIs, RSVP breakdown, recent responses, event snapshot.
2. **Reservations** — list, search, filter, create, view, edit, cancel/delete safely, and status updates.
3. **Guests** — view and maintain guest/contact details linked to reservations; avoid duplicate data storage.
4. **Event Settings** — manage birthday event name, date, start/end time, timezone, venue, address/map link, RSVP deadline, and optional capacity; only expose fields the public site can actually consume.
5. **My Profile** — update display name and password, logout, and view own role.

**Super Admin only:**

6. **Users** — admin account CRUD, roles, enable/disable access, password reset/change, and safeguards.

**Next phase (not blockers for MVP):** Reports/CSV export, event-day Check-in, Invitations, Notifications, Audit Logs UI, Media Library, Website Content/FAQ editor, and advanced analytics. Build audit events for critical changes during MVP even if the audit viewer comes later.

**Navigation behavior:** sidebar grouped by Overview / RSVP Management / Administration; active route highlighting; icons and tooltips; desktop collapse; accessible mobile drawer; hide links the role cannot access, while also enforcing policies on the server.

## 3. MVP visual specification — Blade only

- Professional, clean admin UI subtly inspired by the underwater public site: ocean teal `#087F8C`, deep blue `#123B55`, off-white `#F5F9FB`, muted borders `#DCE7ED`. Avoid cartoon decoration on operational screens.
- Desktop: 240–260px collapsible sidebar, topbar with breadcrumbs, page title, current admin/profile dropdown, responsive content area. Mobile: drawer navigation with accessible open/close, keyboard focus management, and no horizontal overflow.
- Shared Blade components for layouts, navigation, cards, badges, alerts, buttons, fields, tables, pagination, confirmation dialogs, and empty states.
- Consistent spacing, readable type, strong contrast, visible keyboard focus, clear field validation, touch-friendly controls, loading/empty/error states, and reduced-motion support.
- Avoid image generation for the admin UI; simple icons and CSS suffice. Do not change public RSVP visuals in this task.

## 4. Detailed MVP requirements

### 4.1 Authentication and access control

- Laravel-rendered admin login/logout with session-based authentication, CSRF protection, session regeneration after login, and rate limiting.
- Inspect existing guards, user table, Sanctum configuration, and admin roles first. Reuse compatible authentication instead of building a parallel identity system unnecessarily.
- At least two roles: **Super Admin** (full admin, user management) and **Admin/Staff** (reservations, guests, event settings as permitted). Implement Laravel policies/gates on **every** controller action, not only sidebar visibility.
- Protect `/admin/*` with `auth` plus authorization middleware/policies; ensure guests cannot access data or submit writes.
- Do not expose passwords or tokens. Store passwords using Laravel hashing; use secure reset flows; validate and rate-limit login. Do not seed default production credentials.

### 4.2 Dashboard

- KPI cards: **total reservations**, **confirmed/attending reservations**, **pending reservations**, **declined reservations**, **expected attendees** (sum guest counts of confirmed reservations), and **remaining event capacity** if configured.
- Recent 5–10 reservations with guest name, party size, status, date, and link to details.
- Event snapshot: title, date/time/timezone, venue, RSVP deadline, and public RSVP status (open/closed).
- Optional simple status breakdown chart; don't add heavy chart packages unless justified.
- Count reservations separately from attendees; status values must match existing schema. Use efficient aggregate queries and indexes where appropriate.

### 4.3 Users (admin accounts CRUD)

- Table: name, email, role, status, last login (only if recorded), created date, actions; server-side search, filtering, pagination.
- Create: name, unique email, secure password setup, assigned role, active state; validation and authorization.
- Edit: name, email, role, status; password change through a separate secure flow.
- Delete: **prefer deactivate/soft delete** where compatible; confirmation, CSRF, audit event; prevent self-deletion, deletion/deactivation of the last active Super Admin, and privilege escalation.
- Never expose password hashes, tokens, or sensitive account fields in views or responses.

### 4.4 Reservations CRUD

- List: guest/primary contact, reservation reference, event, party size, RSVP status, submitted date; search by name/email/reference; status/date filters; pagination; sortable safe columns.
- Add: primary guest/contact, attendee count, status, optional notes and existing supported fields; validate event capacity and RSVP rules consistently.
- View: complete reservation and related guests, timestamps, notes, and status history if available.
- Edit: update details/status with validation, optimistic conflict handling where appropriate, and a recorded change reason for sensitive edits.
- Delete: use **soft delete/cancel** by default if supported; require explicit confirmation and permission; avoid orphaning guests or losing auditability. If schema lacks soft delete, propose a migration before changing behavior.
- Guard against duplicate submissions, invalid status transitions, negative guest counts, over-capacity bookings, and mass-assignment vulnerabilities. Reuse public RSVP service/business logic rather than duplicating rules.

### 4.5 Guests

- Show guest/attendee records or derived reservation contacts using the **existing schema**; do not create a new guests table without evidence it is needed.
- Search and view by reservation; edit contact or guest information only if the public RSVP data model supports it.
- Clarify whether one reservation represents one guest, a family/group, or multiple named attendees before designing CRUD.
- Do not invent dietary, accessibility, or child/adult fields if the public form doesn't collect them.

### 4.6 Event Settings

- Editable: event title, honoree name, event date/time/timezone, venue/address, RSVP deadline, contact details, and optional capacity—**only where supported by the existing application**.
- Server-side validation for date order, valid timezone, URL formats, and capacity constraints.
- Ensure React frontend can consume the values through an existing or deliberately versioned public endpoint. **Do not silently change hardcoded frontend text or break its API response shape.**
- Record who changed settings and when. Restrict changes by role.

### 4.7 My Profile

- View/update own name and email, securely change password after current-password verification, logout.
- Handle session invalidation appropriately after password changes without breaking unrelated users' sessions.

## 5. Laravel engineering standards

- Use Laravel conventions: named routes, Form Request validation, controllers kept thin, Eloquent models/scopes, policies/gates, service classes for shared RSVP business logic, migrations for approved schema changes, and transactions for multi-record updates.
- Suggested namespaces: `App\\Http\\Controllers\\Admin`, `App\\Http\\Requests\\Admin`, `App\\Policies`, `App\\Services\\Reservations`, and Blade views under `resources/views/admin`. Adapt to existing project layout.
- Eager-load relationships, use pagination, indexes for common filters, protect against N+1 queries and overfetching.
- Server-side authorization and validation on every write; CSRF, XSS-safe Blade escaping, prepared queries/Eloquent, upload restrictions if uploads exist.
- Use explicit allowlists for fillable fields and validated sorting/filter parameters. No unreviewed third-party admin panel package.
- Prefer feature tests for authentication, authorization, CRUD, status/capacity validation, public API compatibility, and failure paths.

## 6. Route separation and React `/admin` removal

1. Inventory React router definitions, `/admin` navigation links, SPA hosting rewrite rules, Laravel `web.php` and `api.php`, and reverse proxy/document roots.
2. Implement Laravel admin login and pages behind protected routes, ideally `admin.*` route names.
3. Test actual URL ownership: `/admin` and `/admin/login` must render Laravel Blade; React public URLs must still work.
4. Test Sanctum cookie/CSRF login flow and public RSVP submissions against the same backend after the change.
5. Only then remove React admin route/components and unused admin-specific frontend code; **do not delete shared authentication utilities** used by public functionality.
6. Test direct URL refresh, logout, unauthorized access, redirects, browser back navigation, and mobile layout.
7. If frontend/backend live on different origins and `/admin` routing is not feasible, propose a subdomain/hosting adjustment for approval rather than breaking routes.

## 7. Database approach

- **Inspect first**: `users`, reservations/RSVP tables, guests, events/settings, migrations, foreign keys, statuses, soft-delete support, and existing unique constraints.
- Create a schema mapping and migration proposal; reuse current tables and relationships whenever possible.
- Add only required fields/tables after approval (for example admin roles, event settings, audit event records). Avoid duplicate reservation or user tables.
- Back up database before schema changes; test migrations and rollback on a nonproduction copy. **Do not assume migration rollback restores deleted production data.**

## 8. MVP acceptance criteria

- Admin can sign in/out and access only authorized pages; unauthenticated users are redirected appropriately.
- Super Admin can safely create, edit, deactivate, and manage admin users; last-Super-Admin protections work.
- Authorized admins can create, search, filter, view, edit, and cancel reservations; counts and guest totals update correctly.
- Guest management reflects the existing RSVP schema without duplicate or inconsistent records.
- Event settings save correctly and do not break public React pages.
- Dashboard counts reconcile with database queries and distinguish reservations from individual guests.
- No secrets are exposed; CSRF, validation, policies, login rate limits, and audit events for important writes are verified.
- Mobile/desktop layout passes 320, 375, 390, 768, 1024, and 1440px; no horizontal overflow or inaccessible actions.
- Existing public RSVP submission and Laravel APIs continue to work; old React `/admin` removed only after Laravel route cutover succeeds.
- Automated feature tests pass and manual smoke tests are documented.

## 9. Phases and approval checkpoints

**Phase 0 — Audit + rollback (no code changes):** inspect routes, schema, auth, React integration, current assets, uncommitted changes. Produce dependency map, scope, risks, and verified restore strategy. **Wait for approval.**

**Phase 1 — Foundation:** Blade design system, sidebar/topbar, admin login/logout, roles/policies, protected routes, tests. Demo responsive shell. **Wait for approval.**

**Phase 2 — Dashboard + Reservations:** metrics, reservation CRUD, search/filter/pagination, status rules, tests. Demo with real schema data (never publish personal data). **Wait for approval.**

**Phase 3 — Users + Guests:** Super Admin users CRUD, guest data screens, permissions, tests. **Wait for approval.**

**Phase 4 — Event Settings + Profile:** event settings and safe public API integration, profile/password management, tests. **Wait for approval.**

**Phase 5 — Cutover + QA:** verify Laravel `/admin` URL ownership; remove React admin route; run end-to-end RSVP and admin regression tests; document deployment and rollback. **Wait for approval before deployment.**

**Phase 6 — Optional post-MVP:** Reports/CSV export, Check-in, Invitations, Notifications, Media Library, Website Content, audit log viewer, analytics. Prioritize based on real event-day needs. A basic CSV export or check-in can be pulled into MVP if the event is imminent.

## 10. Safe rollback / design rejection

- Before each approved phase, create a named, **verified** Git commit/branch or isolated backup. Record exact changed files and migrations. Protect existing uncommitted work.
- On “I don't like the design,” offer **revert this page/component** or **revert the whole current phase**. Revert only files from that approved phase, not unrelated user changes.
- Never run `git reset --hard`, `git clean -fd`, force-push, or destructive DB rollback without explicit authorization.
- For data-bearing migrations, provide a forward repair plan and backup restore procedure; reverting code alone may not revert database state.
- After rollback, re-test login, public RSVP submission, reservations, and `/admin` routing.

## 11. Efficient prompt to start in VS Code

> Read `LARAVEL_RSVP_ADMIN_MVP_PLAN.md` fully. Act as a Senior Laravel Backend Engineer, Senior Laravel Blade/Tailwind Frontend Engineer, Security Engineer, and QA Lead. Start with **Phase 0 only**: inspect relevant Laravel and React routes, schema, existing RSVP APIs, authentication/Sanctum configuration, and uncommitted changes. Preserve all secrets, tokens, `APP_KEY`, `.env`, current data, and working public RSVP flows. Summarize the architecture and recommend the minimum changes for a Laravel-only admin. Establish a verified, non-destructive rollback point. **Do not edit code, install dependencies, migrate the database, or remove React `/admin` yet.** Wait for my approval before Phase 1. Minimize AI token consumption by reading targeted files and using concise diffs.

---

**This plan defines intended implementation, not verified findings about the current repository.** Resolve all schema, route, and authentication assumptions during Phase 0.

## Implementation status — October 18, 2026

- Phase 0: complete. See [audit](PHASE_0_AUDIT.md).
- Phase 1: implemented and validated after approval. See [implementation, QA and rollback report](PHASE_1_IMPLEMENTATION_REPORT.md), including the database rehearsal incident and verified reconciliation.
- Phase 2: Dashboard and Reservations implemented after approval. See [implementation, QA and rollback report](PHASE_2_IMPLEMENTATION_REPORT.md). React admin remains available until the verified Phase 5 cutover.
- Phase 3: Users and Guests implemented after approval. See [implementation, QA, folder map and rollback report](PHASE_3_IMPLEMENTATION_REPORT.md). Navigation views and reservation components are grouped in designated folders as requested.
- Phase 4: Event Settings and My Profile implemented after approval. See [implementation, QA and rollback report](PHASE_4_IMPLEMENTATION_REPORT.md). Navigation views remain in designated folders.
- Phase 5: awaiting approval. Actual hosting cutover, React admin removal and final end-to-end QA remain pending; no deployment performed.
