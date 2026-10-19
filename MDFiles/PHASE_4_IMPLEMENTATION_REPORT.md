# Phase 4 - Event Settings and My Profile

Date: October 18, 2026. Status: implemented and validated; Phase 5 awaits approval.

## Delivered

- Blade Event Settings list, title/name search, publication filter, pagination, create and edit screens. Staff manage their own events; Super Admin can manage all events without changing ownership.
- Existing event fields reused: invitation title/message/labels, internal name/slug, date, start/end time, timezone, venue/address/map, RSVP deadline, publication/access, party rules, theme and image URLs. New events default to draft, Asia/Manila and one guest. No schema or dependency changes.
- Unsupported honoree/contact/capacity fields and multiple venue records were not invented. The public React sample's existing two-venue content remains unchanged. Dynamic invitation endpoints retain their existing single-venue data model.
- Shared event validation/service now handles Blade and the existing organizer API create/update endpoints. Existing endpoint names, response shapes and serialized event-date input compatibility are preserved. Address length now matches the database's 255-character column; URLs allow HTTP/HTTPS, and supplied end time must follow start time on the same date. Overnight scheduling is not supported by this form.
- The Blade deadline is entered/displayed in the event timezone and saved in UTC for the existing public API. Nonexistent local times during daylight-saving transitions are rejected. Ambiguous fall-back times use the timezone library's interpretation; no explicit offset selector was added.
- Event-row transactions, policy rechecks, stale-form detection and actor/action/reason audit entries protect edits. Unchanged occasion labels retain their IDs. Changing private access to public requires explicit Blade confirmation and revokes existing active invitation links while retaining token hashes and records, matching the organizer API's existing behavior.
- My Profile shows the current user's username/role and supports own name/email updates with current-password verification. Email uniqueness covers both email and username identifiers; email changes clear the old verification timestamp. Role/status/username cannot be changed through this screen.
- Separate, rate-limited password change requires the current password, confirmation, at least 12 characters with mixed case/number/symbol and no more than 72 bytes. Passwords are hashed and excluded from old form values. Stale profile forms are rejected.
- Password changes invalidate the owner's database sessions and existing audit-backed cookie access revisions, then sign out the current session. Other users' sessions and credentials remain intact. Personal-access-token records are retained; bearer-token revocation is not claimed.
- Navigation files remain in designated folders: `admin/event-settings/{index,form}.blade.php` and `admin/profile/index.blade.php`. Nested Event Settings routes highlight correctly. Shared input IDs support the two separate password-confirmation forms without duplicate element IDs.

## Verification

- Full isolated SQLite suite: **99 tests, 562 assertions passed**, including **22 Phase 4 tests / 110 assertions**. Coverage includes owner isolation, Super Admin access, guest rejection, settings/public API compatibility, UTC deadline roundtrip, invalid scheduling/URLs/timezones, stale edits after legacy API writes, private-link confirmation, archive behavior, escaping, profile privilege injection, password confirmation, target-only session invalidation, retained token records and audit-failure transaction rollback.
- **10 Edge browser checks passed**, using disposable local SQLite fixtures and random temporary credentials. Four screens checked at **320, 375, 390, 768, 1024 and 1440px**, with no page overflow or duplicate element IDs. Actual create/edit/profile/password/logout/relogin flows passed, forged profile writes returned 419, and no browser JavaScript errors occurred. [Browser results](phase4-qa/browser-results.json).
- Visually reviewed [event settings desktop](phase4-qa/event-settings-1-edit-1440.png) and [profile mobile](phase4-qa/profile-390.png). Screenshots contain only disposable fixtures.
- Backend Vite production build and Laravel Pint passed. Build used the existing temporary Node 22.22 runtime; global Node 22.11 remains below Vite 8's supported minimum. No dependencies were added.
- Source hashes confirm public frontend files match the pre-phase snapshot. No real database migrations or record/account/password/token changes were made during implementation or QA. `.env`, APP_KEY and existing credentials were preserved. Temporary QA server, database, credentials and fixture script were removed after testing.
- Production routing, physical devices, screen-reader speech, MySQL concurrency and hosted browser behavior are not claimed as tested. React integration is verified through its existing API contract; actual hosting cutover and end-to-end React/admin regression belong to Phase 5.

## Rollback

- Verified pre-phase source snapshot: `C:\Users\Jayjay\Documents\WorkProjects\planned_rsvp-redesign-backups\laravel-admin-phase4-before-20261018-214819`. **193 files** SHA-256 verified with dirty-status capture and a restore-copy probe. Secrets, database files, dependencies and build output excluded. Earlier source/database restore points remain intact.
- Existing files changed: `app/Http/Controllers/Api/AdminEventController.php`, `app/Models/EventProject.php`, `app/Policies/UserPolicy.php`, `routes/web.php`, the Event Settings/Profile index views, admin layout and shared input component. This report and the main plan status are documentation changes.
- Added: EventSettings/Profile controllers; EventSettings/Profile/ProfilePassword requests; `Services/Events/{EventRules,EventService}`; `Services/Admin/ProfileService`; Event Settings form; `tests/Feature/AdminSettingsProfileTest.php`; `tests/Browser/admin-settings-profile.cjs`; Phase 4 screenshots/results and this report.
- To revert only Phase 4, review later edits, restore the listed existing source files from `files/<relative path>` in the snapshot, and remove only the listed additions after removing references. Rebuild Blade assets. Do not reset/clean the working tree or revert unrelated changes. The browser harness requires freshly provisioned isolated fixtures and the existing temporary Playwright installation.
- No schema rollback is required. A source rollback must not undo settings/profile/password changes subsequently made by real users. Preserve audit records and existing password/session protections. Data recovery needs separate explicit authorization and a verified database recovery procedure.

## Next checkpoint

Obtain approval for **Phase 5 - Cutover and QA**. Verify actual frontend/backend hosting ownership of `/admin`, public React RSVP and Sanctum sessions before removing the legacy React admin route. Deployment requires separate approval under the original plan. No deployment or React admin removal occurred in Phase 4.

## Follow-up: simplified event form

At the user's request, removed Publishing & replies, Appearance & images, and the change-reason input from the Blade event form. Omitted settings use existing event values on update; existing cover/gallery URLs remain untouched. New events default to published/public with one guest, matching the simplified RSVP workflow. Audit entries still record the actor and an automatic reason. Existing organizer API controls remain compatible.

Full regression suite after this change: **101 tests, 578 assertions passed**; Pint and whitespace checks passed. Two added regression tests verify omitted-value preservation and simplified creation. The browser harness was updated for the removed inputs; earlier screenshots/browser results show the original Phase 4 form and were not regenerated for this follow-up. A copy of the pre-change form/request is retained at `C:\Users\Jayjay\AppData\Local\Temp\rsvp-event-form-before-20261018-232559`; the original Phase 4 restore point remains intact.

## Follow-up: two occasions and venues

Implemented two paired occasion groups, such as Christening and 1st Birthday, each with its own venue, address, HTTP/HTTPS map link and start/end time. Second group is optional. Names are saved as the existing occasion labels. The invitation slug remains automatic and is not shown as an editable field. Public React invitations render the saved occasion stops; the sample page retains its existing content.

Added nullable JSON `event_projects.event_stops` through migration `2026_10_18_153310_add_event_stops_to_event_projects_table.php`. Existing single-venue rows remain unchanged and fall back to the original public rendering. The first stop mirrors the original venue/time fields for existing clients; the public API adds `event_stops` without removing existing keys. Event duplication copies the stops. Existing single-venue API clients retain their fields; advanced paired edits should use `event_stops` to update those details together.

Validation: **104 tests / 610 assertions passed**, frontend TypeScript and production build passed, backend production build and Pint passed. Two feature tests cover paired venues/public output, optional second group and invalid/more-than-two stops. **10 browser checks passed**, including real two-occasion creation/edit and all six screen widths; screenshots/results in `phase4-qa` now reflect the latest form. Browser fixtures were removed. Public React rendering was type-checked/built; a hosted React end-to-end test is still Phase 5 work.

Rollback: verified eight-file pre-change source snapshot at `C:\Users\Jayjay\AppData\Local\Temp\rsvp-two-occasions-before-20261018-233309`. Private database snapshot at `C:\Users\Jayjay\AppData\Local\Temp\rsvp-two-occasions-db\before.json` is access-restricted to the current Windows user; contains schema and records, not application environment secrets. All 16 table hashes matched after restore into a disposable database. Migration and rollback rehearsed there before applying the additive migration to the configured database. All original record values were verified unchanged after applying; disposable database removed. Prior restore points remain intact. Do not commit/share the private snapshot or remove the JSON column after real occasion data is saved. Prefer leaving the nullable column during a code rollback.
