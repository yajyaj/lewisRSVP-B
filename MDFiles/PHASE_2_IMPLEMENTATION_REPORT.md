# Phase 2 — Dashboard and Reservations

Date: October 18, 2026. Status: implemented; Phase 3 requires approval.

## Delivered

- Blade dashboard with event selection, owner-scoped metrics, recent reservations and an event snapshot (date/time/timezone, venue, deadline and invitation status). Super Admin can work across event owners; staff remain scoped to their events.
- Separate totals for stored reservations, active attending reservations, expected attendees, pending invitations, active declines and cancellations. Total stored reservations includes cancellations. Pending retains the existing meaning: an active invitation without a response, not a new RSVP status. Capacity is not displayed or enforced because the existing application has no capacity field.
- Reservation list with primary contact, stable computed `RSVP-000001` reference, event, status, party size and submission date. Search by name/email/reference; event/status/date filters, allowlisted sort options, deterministic ordering, 20-row pagination and retained query strings. Date filters and displayed submission timestamps are labeled UTC.
- Create, view, edit, cancel and restore through named Blade routes. New manual reservations permit one guest; declines normalize to zero attendees. Existing groups and additional names are preserved unless explicitly changed. The group editor supports clearing names when reducing party size. No existing event party-size settings or stored group responses were rewritten.
- Detail screen with contact, supported notes/message, original group names, timestamps and paginated response history. Required change reasons, CSRF-protected confirmation and explicit restore action.
- Cancellation is an additive `cancelled_at` timestamp, separate from `attending`/`declined`. No guest, RSVP, invitation or token is deleted. Cancelled records are excluded from active totals, legacy response lists and CSV exports; their history remains available in Blade. Public resubmission of the same cancelled response returns a field-associated 422 error directing the guest to contact the host. Only an explicit admin restore reactivates it.
- Revision-based conflict checks for Blade edits/cancellation/restoration; a newer public submission increments the revision so stale admin changes are rejected. Legacy API updates keep their existing payload contract, with optional revision support rather than a new mandatory field.
- Transactional shared `ReservationService` and `ReservationRules` used by public RSVP endpoints, Blade operations and the existing admin response-update API. Event-row locking serializes response identity creation; a repeated manual submission key returns the existing response without creating another guest. Guest, response and audit writes are committed together.
- Public/private endpoint paths and response fields retained. Public deadline/publication/private-link rules remain enforced. Manual admin entry can occur after a guest deadline, as existing organizer edits could; it requires a change reason. Public clients still obey event-specific party-size limits. Public React source was not edited by this phase.
- Audit entries record actor, action, reservation ID and an optional validated operational reason. No credential, token or raw guest payload is stored in audit metadata. Guest-facing updates have no admin actor. These records support the reservation history without introducing an audit-log module.

## Database and rollback

- Before edits, **124 files** were SHA-256 verified in `C:\Users\Jayjay\Documents\WorkProjects\planned_rsvp-redesign-backups\laravel-admin-phase2-before-20261018-172209`; restore-copy probe passed. Manifest and original dirty Git status are included. Secrets, database files, dependencies and build outputs are excluded. Earlier restore points remain intact.
- Protected database backup: `C:\Users\Jayjay\Documents\WorkProjects\planned_rsvp-redesign-backups\laravel-admin-phase2-db-20261018\before-phase2.sql`. Directory inheritance removed; current Windows user granted access. Do not commit or publish this SQL backup.
- SQL SHA-256: `e1418ecac722218b42cbd8f669cd004fa1addce3e5a2e78bf9aac0a549634820`.
- Backup restore verified across **16 tables**. Migration and rollback were run in **separate guarded processes** against a disposable database, with the actual connection/database identity checked before execution. All original rows/columns matched before and after rehearsal. Disposable restore database removed. This avoids the connection-alias problem documented in Phase 1.
- Applied only `2026_10_18_092223_add_cancellation_and_revision_to_rsvp_responses.php`: nullable cancellation timestamp, default revision 1 and reservation query index; nullable audit reason and subject/history index. All **13 migrations** now show applied.
- Before/after application fingerprints verified every pre-existing application row and original column unchanged, including users, credentials, sessions, invitation hashes and personal-access-token records. Only migration history and additive schema/default metadata changed. `.env` and APP_KEY were untouched.
- Source rollback: review later diffs and restore only Phase 2 modified files from `files/<relative path>`; remove only Phase 2 new files no longer used. Prefer leaving additive columns/audit data in place when reverting code. A live migration rollback would remove cancellation, revision and audit reasons; do not run it blindly. A full SQL restore can discard later RSVPs and requires explicit authorization and a separately rehearsed data recovery plan.

## Validation

- Full PHPUnit suite: **53 tests, 317 assertions** using isolated in-memory SQLite. Includes **21 new Phase 2 tests** plus the existing public/private RSVP and Phase 1 auth/authorization regression tests.
- Covers scoped metrics, staff/Super Admin access, guest rejection, field validation, one-guest creation, idempotency, stale edit rejection, cancellation retention/confirmation/restoration, cancelled public resubmission, existing group preservation/reduction, search/date/status/reference filters, pagination, sort injection rejection, escaped names/notes/messages/reasons, legacy API compatibility and rollback when audit storage fails.
- Browser QA: **13 checks** using existing temporary Playwright/Edge with a disposable local SQLite database. [Results](phase2-qa/browser-results.json). Real browser create/edit/cancel/restore/search; forged cancellation returns 419; no JavaScript errors.
- Dashboard, reservation list, create form, details and edit form checked at **320, 375, 390, 768, 1024 and 1440px**: no page overflow. Table regions support contained horizontal scrolling where needed. Mobile drawer Escape/focus restoration checked.
- [Dashboard desktop](phase2-qa/dashboard-1440.png), [dashboard mobile](phase2-qa/dashboard-390.png), [reservations desktop](phase2-qa/reservations-1440.png), [reservations mobile](phase2-qa/reservations-390.png), [create form mobile](phase2-qa/create-390.png), [details desktop](phase2-qa/details-1440.png). Screenshots contain disposable fixtures only and were visually reviewed.
- Production Vite/Tailwind build passed with the temporary supported Node 22.22 executable. Existing global Node 22.11 remains below Vite 8's supported minimum. No dependencies were added or changed during Phase 2.
- Laravel Pint and `git diff --check` passed. Disposable server, credentials/database and operational scripts are cleaned up after QA. Browser harness remains at `tests/Browser/admin-reservations.cjs`; it requires freshly provisioned isolated fixtures and the existing temporary browser tools.
- Physical devices, screen-reader speech, production deployment/routing and concurrent requests on multiple database connections are not claimed as tested. Locking/idempotency are implemented and sequential duplicate/stale-write paths are tested; a production load test is separate.

## File scope

Modified: API RSVP/admin-event controllers, RsvpResponse/AdminAuditEvent models, admin layout, web routes, backend CSS/JS. Added: dashboard/reservation controllers, reservation Form Requests/policy/service/rules, the additive migration, Blade dashboard/reservation screens and shared fields/status/table/error components, feature/browser tests, QA artifacts and this report. The plan status is updated separately.

Unrelated frontend `InvitationPage.tsx` edits were detected during this phase and preserved. They are not part of the backend change or rollback scope. No React admin removal, new user/guest administration, event editing, profile editing or deployment was performed.

## Next checkpoint

Stop before Phase 3. That phase implements Super Admin user management and guest screens, including deactivation/last-active-Super-Admin safeguards, using the existing identity and guest schema. Obtain approval before proceeding.
