# Ready Event Website Integration - Implementation Report

Date: October 19, 2026.

## Outcome

Implemented the approved integration and event-form plan. The user clarified that no real event record should be created. The configured application database returned no events during read-only inspection, so live homepage event selection remains intentionally unset. No real records, schema, credentials or environment files were changed.

### Website

- Added `GET /api/website-event`, resolving the explicit `PUBLIC_EVENT_SLUG` backend configuration through the existing public-event lookup. Missing configuration, missing events, drafts and private events return 404. The endpoint does not enumerate organizer data.
- The homepage now loads a configured real event instead of a hardcoded, non-submitting sample. Direct slug and private invitation routes retain their existing lookups; private invitation query parameters take precedence over the default homepage event.
- Landing and invitation share one resolved event state: title, date and occasion names agree. The opening action remains disabled while loading or unavailable; unavailable invitations offer retry. In-flight event requests are cancelled on cleanup.
- Existing public RSVP endpoints, one-guest payloads, private invitation behavior, per-slug response identity storage and non-submitting admin previews remain intact.
- Shared presentation helpers format occasion stops, dates, deadlines and countdown target. Countdown uses the earliest valid saved occasion start, independent of array order; configured stops without a valid start have no invented target. Legacy events without stops retain their single-venue/start fallback.
- RSVP deadline dates follow the event's internal timezone, rather than the visitor's timezone. Public details no longer show technical timezone identifiers. Existing artwork, carousel/lightbox, animations and layout remain.

### Blade editor

- Removed the timezone input. New Blade events receive the server-side `Asia/Manila` default; existing stored timezones remain unchanged. Existing organizer API timezone validation is retained. UTC deadline storage and local deadline display/conversion remain.
- Centered the editor within a readable maximum width. Date & replies groups the event date and optional RSVP deadline. Internal-name versus invitation-title guidance clarifies which content guests see.
- Occasion cards retain on-demand Add/Remove controls, independent venue/time fields and required names for included groups. Disabled removed fields are excluded from submission. Nested field errors and old values use Laravel's dot notation and accessible input/error associations.
- Event list and dashboard venue summaries include both occasion names/venues. Existing single-venue fallback remains. Removed slug, publishing, appearance and change-reason inputs stay removed.

## Configuration when a real event is available

1. Save the intended event through the existing editor; this implementation did not create one.
2. Use the generated slug from its View/Published invitation link and set `PUBLIC_EVENT_SLUG=<that-generated-slug>` in the backend environment. This value is a public identifier, not an authentication token.
3. Refresh the application's configuration through the normal environment/deployment process if configuration is cached. Keep the existing frontend API-origin setting pointed at the intended backend.
4. Confirm the chosen event is published and public. Private events must continue to use their private invitation URLs; this feature does not change their access mode.

Until configured, `/` displays Invitation unavailable and cannot submit an RSVP. Existing valid direct invitation URLs continue to work. No automatic newest-event selection is used.

## Verification

- Full isolated backend suite: **107 tests, 628 assertions passed**. Three added integration tests cover explicit published/public event selection, missing Blade timezone/default and preserved existing timezone, UTC deadline conversion and nested error associations.
- Frontend tests: **7 passed**, including earliest-occasion countdown, no-start behavior, single/two-stop mapping, event-local deadline calendar date, timezone offsets and countdown boundaries.
- Frontend TypeScript and production build passed; backend production build and Laravel Pint passed. Whitespace checks passed. Existing supported temporary Node runtime used; no dependencies installed.
- Actual React/Laravel browser verification used a **disposable SQLite database**, local QA servers, reduced motion and a US visitor timezone. Checks cover the configured root, real attending/declined RSVP writes with stable response identity, two venues, six widths, Add/Remove focus and persistence, direct refresh and unavailable/retry behavior. The database was checked to contain one updated response with declined attendance and zero attendees, rather than duplicate responses.
- Public invitation and Blade editor checked at **320, 375, 390, 768, 1024 and 1440px**, with no horizontal overflow. Screenshots and results: [QA artifacts](ready-event-qa/browser-results.json). Carousel images are allowed to load before screenshots.
- Private-invitation contracts remain covered by the existing backend suite; a hosted private-invitation browser run, physical-device testing, full screen-reader speech and production hosting cutover are not claimed. Browser checks used reduced motion; existing animation code was preserved.

## Rollback and scope

- Pre-change source snapshot: `C:\Users\Jayjay\AppData\Local\Temp\rsvp-ready-integration-before-20261019-005511`. **161 files SHA-256 verified**, with manifest and original dirty status. Prior restore points remain intact.
- Existing scope: public event controller, API routes, app configuration, EventSettings request, event form/list/dashboard views, shared input, InvitationExperience/Page, EventCountdown and EventDetails.
- Additions: `frontend/src/eventPresentation.ts`, its frontend tests, `backend/tests/Feature/WebsiteEventIntegrationTest.php`, `backend/tests/Browser/ready-event-integration.cjs`, QA artifacts and this report.
- No migration or real data conversion was needed. No real event, RSVP, account or invitation was created or modified. Temporary QA fixtures and servers are removed after verification.
- To revert, review later edits, restore only the listed existing files from the snapshot and remove only new unreferenced additions. Rebuild frontend/Blade assets. Preserve unrelated dirty files and all later real records. Do not reset the repository or restore the database blindly.
- This work does not remove React admin, change production URL ownership or deploy the website. Those approval checkpoints remain under the original Laravel admin plan.
