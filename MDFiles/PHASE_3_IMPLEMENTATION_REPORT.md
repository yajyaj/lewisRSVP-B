# Phase 3 — Users, Guests and navigation folders

Date: October 18, 2026. Status: implemented; stop before Phase 4.

## Delivered

- Super Admin-only account list, search, role/status filters, 20-row pagination, create, view, edit, deactivate/reactivate and a separate password-change screen. Last login is derived from existing successful-login audit records; unavailable history is labeled Not recorded.
- Existing `users` identity store, roles and active flag reused. No migrations, new guest/user tables, dependencies or real account changes were needed. Staff cannot access user-management actions, including direct requests. Existing public React and API routes remain.
- Explicit, validated assignment of role/status. Account creation and all privileged changes require the acting Super Admin's current password and an operational reason. Initial/replacement passwords must be confirmed, at least 12 characters, mixed case with a number/symbol, and no more than 72 bytes for bcrypt compatibility. Passwords are hashed, never echoed or recorded in audit metadata, and excluded from old form values.
- Duplicate email/username and ambiguous login identifiers rejected. Email changes clear the old verification timestamp. General editing cannot change a password; password changes have a separate rate-limited endpoint.
- Transactions lock account rows in deterministic order, recheck the actor's active Super Admin role/current password, and enforce self-deactivation and last-active-Super-Admin protections. Self-demotion is allowed only when another active Super Admin remains; the caller returns to Dashboard. Stale account forms are rejected.
- Delete means **deactivate**, retaining the account, owned events, guests, reservations and personal-access-token records. Only the target account's database sessions are removed. Reauthentication is required after a password change; a self-password change signs out the current session.
- Password signatures and audit-backed access revisions protect cookie sessions across both Blade and legacy authenticated APIs. Old cookies cannot become valid again merely because a deactivated account is reactivated. Unrelated accounts' sessions and credentials remain unchanged. Personal-access-token rows are intentionally retained; this is not a claim of bearer-token rotation/revocation.
- Guest list/search, event and responded/pending filters, pagination, detail and contact edit using **existing guest rows**. Staff remain owner scoped; Super Admin can manage across events. Pending means an active invitation without a response. Additional attendee names remain attached to reservations rather than becoming duplicate contacts.
- Contact updates preserve attendance counts/cancellation state/invitation links, update the existing primary contact, and increment linked reservation revisions so stale reservation forms cannot overwrite newer contact details. Stale guest forms are rejected. A View contact link connects reservation details to the guest record.
- Account and guest actions write actor/action/subject/reason audit records within their transactions. Guest histories and account histories are separated by action namespace. No raw credential or guest payload is copied to audit metadata.

## Navigation folders

The user's follow-up folder organization is complete. Every admin navigation destination has a designated Blade folder:

```text
resources/views/admin/
  dashboard/index.blade.php
  reservations/{index,form,show}.blade.php
  users/{index,form,show,password}.blade.php
  guests/{index,form,show}.blade.php
  event-settings/index.blade.php
  profile/index.blade.php
  auth/login.blade.php
  layouts/app.blade.php
  partials/foundation.blade.php

resources/views/components/admin/
  reservations/{table,status}.blade.php
  ...shared fields, errors, buttons, cards and icons
```

Dashboard and foundation views were moved out of the admin root. Reservation-specific components moved into `components/admin/reservations/`; callers now use `x-admin.reservations.table` and `x-admin.reservations.status`. Routes/controller view names and navigation highlighting were updated. Event Settings and My Profile remain Phase 4 placeholders in their own folders; their functionality was not implemented early.

## Verification

- Full isolated SQLite suite: **77 tests, 452 assertions**. Includes **24 Phase 3 tests**, with existing authentication, public/private RSVP and reservation regressions retained.
- Covers each staff-denied user action, hashing/confirmation, actor reauthentication, duplicate identifiers, role changes, self-deactivation/last-admin safeguards, safe self-demotion, target-only session invalidation, old-cookie rejection after password/deactivation changes, self-password logout, sensitive-input redaction, optimistic edits, audit rollback, owner isolation, guest contact/revision preservation, search/pagination/escaping and designated view folders.
- **17 Edge browser checks passed** using existing temporary Playwright tools and disposable local SQLite fixtures. Eight Users/Guests screens checked at **320, 375, 390, 768, 1024 and 1440px**, with no page overflow. Actual browser create/edit/password-change/deactivate/reactivate/contact-edit flows passed; forged deactivation returned 419; staff Users access returned 403; no JavaScript errors. [Results](phase3-qa/browser-results.json).
- Visually reviewed [Users desktop](phase3-qa/users-1440.png), [new account mobile](phase3-qa/users-create-390.png), and [guest edit mobile](phase3-qa/guests-1-edit-390.png). All screenshots contain disposable fixtures, not real accounts or guest information.
- Blade bundle built successfully with the existing temporary Node 22.22 executable. Global Node 22.11 remains below Vite 8's supported minimum; use a supported Node runtime for future builds. No packages were added.
- Laravel Pint and whitespace checks passed. Browser server, SQLite credentials/database and fixture scripts are removed after QA. Browser harness remains in `tests/Browser/admin-users-guests.cjs` and requires fresh isolated fixtures plus the existing temporary browser tools.
- Public frontend files matched their Phase 3 baseline hashes. No frontend source, `.env`, APP_KEY, real password, real account role/status, invitation token or database record was changed by this implementation.
- Physical devices, screen-reader speech, production routing and simultaneous multi-connection account mutations are not claimed as tested. Transaction/lock safeguards are implemented; tests exercise the sequential security boundaries and failure paths. Email reset delivery is not added: this phase provides a reauthenticated Super Admin password-change workflow.

## Rollback

- Verified pre-phase snapshot: `C:\Users\Jayjay\Documents\WorkProjects\planned_rsvp-redesign-backups\laravel-admin-phase3-before-20261018-212028`. **155 files** SHA-256 verified, with original dirty Git status and a restore-copy probe. Excludes secrets, database files, dependencies and build output. Prior phase snapshots and protected database backups remain intact.
- Modified scope: User/Guest models; User policy; active-admin middleware; Blade/API login controllers; admin web routes/layout; shared input; Dashboard/Foundation controller view names; reservation detail contact link; reservation component callers. Added: user/guest controllers, requests, Guest policy, account/guest services, module Blade views, feature/browser tests and QA/report artifacts. Moved dashboard/foundation and reservation component files as listed above.
- To revert only Phase 3, inspect later changes, restore reviewed files from `files/<relative path>`, restore the moved views/components to their prior locations, and remove only new Phase 3 files that are no longer referenced. Do not reset/clean the working tree or restore unrelated user edits.
- No schema rollback is required. A code rollback must **not** undo real accounts, role changes, password changes or guest edits made through the new screens. Preserve audit records and review session-revocation compatibility before reverting security middleware after live account changes. A source snapshot is not a database backup; obtain explicit authorization and a protected, verified data recovery plan for any later data rollback.

## Next checkpoint

Phase 4 implements supported Event Settings and My Profile/password management. Obtain approval before starting it. React admin removal and actual hosting cutover remain Phase 5; no deployment was performed.
