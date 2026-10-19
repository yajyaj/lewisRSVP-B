# Ready Event - Website Integration and Event Form Plan

Date: October 19, 2026. Status: implemented and verified; live event selection intentionally unset because the user requested no record creation. See [implementation report](READY_EVENT_WEBSITE_IMPLEMENTATION_REPORT.md).

## Objective

Use the event saved in Laravel throughout the public RSVP website, including the landing screen, invitation, countdown, occasion names, venues, schedules and RSVP submission. Simplify the Blade event editor and remove the need to enter a timezone.

Work as a senior Laravel/React developer and UI/UX designer. Inspect before editing, reuse existing components, keep controllers thin and implement in reviewable stages. This document does not authorize deployment or the separate Phase 5 admin cutover.

## Verified starting point

- `/` currently uses a hardcoded sample event and disables RSVP submissions. Slug URLs and private invitation URLs already load real event data from the API.
- `InvitationExperience.tsx` supplies the opening animation and generic landing copy. `InvitationPage.tsx` currently owns event loading and RSVP state.
- Hero title/date/labels and welcome text already accept event data. Dynamic invitations render `event_stops`; the sample still has separate hardcoded venues and times.
- Laravel stores up to two occasion stops, with independent name, venue, address, map link and start/end time. The first stop mirrors the legacy single-venue/time fields.
- The Blade editor initially shows one occasion, with Add/Remove controls for the second. Stored occasions appear when editing.
- The timezone field remains editable. Deadline conversion and countdown calculations depend on the stored timezone. Public details currently display its technical identifier.
- The dashboard snapshot currently shows only the legacy first venue. Removing the field alone would not update all event summaries.
- The prepared event's identity and current values have not been queried for this plan. Do not assume the most recent event or overwrite its times from earlier conversation examples.

## Decisions and boundaries

1. **The selected saved event becomes the source of public content.** Keep its generated slug and existing invitation URLs unchanged.
2. **Timezone becomes automatic.** For this Philippine event, use `Asia/Manila` internally and ordinary local date/time labels in the interface. Remove the editable timezone control and technical timezone text from guest-facing sections.
3. **Keep timezone-aware calculations.** Retain the database/API timezone field and UTC deadline storage. Visitors abroad must see the same remaining countdown duration.
4. **Keep the approved underwater design.** Preserve artwork, carousel/lightbox, opening animation, typography, accessibility and reduced-motion behavior. No image generation or visual rebranding is required.
5. **Keep the current limit of two occasions**, added on demand. Unlimited occasions require a separate scope change.
6. Keep one-guest RSVP behavior, real records, private-link restrictions, authentication, tokens, response identities and API contracts intact.

## Stage A - Confirm the ready event and establish rollback

- Read project instructions and only the relevant event, API, frontend and Blade files.
- Inspect published/public event metadata through authorized read-only access: ID, title, slug, date, occasions and publication/access state. Do not dump guest records, tokens or credentials.
- If more than one event could be the intended website event, request its title or ID before wiring `/`. Do not choose by latest creation date or first database row.
- Confirm that the selected event is intended for public access. A private event must not become public merely to populate the landing page.
- Capture a hash-verified source snapshot and dirty-file inventory. Before any real data/schema changes, create and verify a protected database backup.
- Check that the event-stops migration is installed in the target environment. Reuse its existing nullable JSON column; no new event tables are planned.

Acceptance: the exact event and access model are known, current values are recorded without sensitive data, and rollback is verified.

## Stage B - Connect the homepage and invitation to the saved event

- Use an explicit configured public-event slug for `/`, fetched through the existing public event endpoint. This is a public identifier, not a token. Validate availability and respect published/public restrictions.
- Never embed an organizer token, database credential or private invitation token in frontend configuration. Do not enumerate private or unpublished events publicly.
- Preserve direct `/{slug}` and `?invite=...` behavior; a private invitation takes precedence over the homepage default.
- Resolve event data once for the opening screen and invitation, with shared loading/error state. Avoid separate requests that can show conflicting event content.
- Pass saved title, date and occasion labels into the landing screen. Keep decorative marketing copy generic unless an existing event field supplies it.
- Replace the root sample branch with real-event behavior only when configuration resolves successfully. Missing/unavailable events show a clear unavailable/retry state; never fall back to a sample and imply RSVP success.
- Keep explicit preview mode non-submitting. Sample/demo content, if retained, must have an explicit preview entry point and must not be the live root page.
- Submit root-page RSVPs against the resolved event's actual slug, using the existing API and response-key mechanism. Preserve per-slug local storage keys and duplicate-submission protection.

Acceptance: `/`, the saved slug URL and authorized private links show their correct events; the live homepage accepts real RSVPs and preview mode does not.

## Stage C - Apply names, schedules and venues consistently

- Use a shared event presentation adapter for labels, stops, formatted dates and countdown target. Avoid repeated hardcoded Christening/Birthday details in components.
- Populate hero badges, landing event information, All the little details, venue/map links and any event-specific confirmation text from the saved event.
- Render only saved occasions, in saved order, with their own names and venue/time values. One occasion must not leave an empty second card.
- Preserve legacy single-venue fallback for older events. Do not invent a second venue when none is saved.
- For the combined countdown, use the earliest valid saved occasion start on the event date; use the legacy start time only when there are no occasion stops. If configured occasions have no valid start, show the existing no-countdown state.
- Show individual start/end times for each occasion. Do not replace the birthday schedule with the first occasion's mirrored legacy end time.
- Format the RSVP deadline in Philippine event time, rather than the visitor's browser timezone, so its displayed date cannot shift abroad.
- Update the admin dashboard snapshot and event list summaries to reflect both saved occasions/venues, with readable fallback for older events.
- Keep the existing carousel/photos and artwork. Image uploads or a media editor are outside this change.

Acceptance: one admin edit is reflected after the next page load throughout the relevant public sections and admin summaries, without duplicate conflicting venue text.

## Stage D - Simplify and refine the Blade editor

### Proposed layout

1. **Header:** Edit event, a short description, back link and View invitation link when accessible.
2. **Invitation details:** Event name used internally, invitation title and welcome message, with clear explanations of their different purposes.
3. **Date and replies:** Event date and optional RSVP deadline. Rename the deadline label to "RSVP deadline" and remove the timezone input. A short "All times are local Philippine time" hint is sufficient; no technical timezone selector.
4. **Occasions and venues:** One structured occasion card initially. Each card groups occasion name/venue, start/end time, address and optional map link. Add occasion reveals the second; Remove excludes its fields from submission and saving removes that second stop.
5. **Actions:** Primary Save event settings, secondary Cancel, clear pending/validation/success states. Keep history below the editor as secondary information.

### Design and accessibility

- Reuse the admin ocean colors, shared Blade controls and established card styles.
- Use a centered, readable content width. Two-column field groups on larger screens; single-column fields on small screens. Long venue/address values must wrap without forcing page overflow.
- Keep field labels visible, identifiers unique, errors associated with the correct nested field, and keyboard focus predictable after adding/removing an occasion.
- Require an occasion name for every included occasion. Preserve submitted values and the expanded second card after validation failure.
- Ensure the disabled second group never triggers hidden required-field errors or submits stale values.
- Preserve stale-edit protection, policy checks and automatic audit reasons. Keep the removed slug, publishing, appearance and change-reason controls out of this form.

### Backend timezone handling

- Supply the configured `Asia/Manila` default server-side for new events; do not require a hidden browser field.
- Preserve an existing event's stored timezone on ordinary edits. If the selected event already uses Asia/Manila, no data conversion is necessary.
- If it uses another timezone, pause that conversion for clarification: decide whether to preserve the instant or its displayed wall-clock time, then preview the change and back up affected data.
- Continue interpreting the local deadline in the event's internal timezone and storing UTC. Do not change Laravel's global application timezone or rewrite unrelated historical events.
- Keep existing organizer API timezone validation compatible; removing a Blade control does not remove an API field.

Acceptance: the editor has no timezone input, existing records save safely without it, and occasion controls work with keyboard and mobile input.

## Stage E - Verification and handoff

- Backend tests: missing Blade timezone defaults correctly, existing timezone/deadline preserved, selected event access enforced, two-stop save/remove works, and existing public/private RSVP contracts remain valid.
- Frontend checks: root event resolution, landing/invitation consistency, one/two occasions, legacy fallback, countdown before/at/after the event and missing-start handling.
- Check countdown/deadline display from Philippine and non-Philippine browser timezones. Confirm the same absolute countdown target.
- Use disposable fixtures for actual attending/declined submissions, repeat submissions and validation failures. Verify the expected records/counts without submitting test RSVPs to the real event.
- Test Add/Remove, validation retry, keyboard focus, loading/error states, direct URL refresh and reduced motion.
- Verify 320, 375, 390, 768, 1024 and 1440px; capture screenshots using fixtures and check page overflow and duplicate IDs.
- Run affected tests, the existing regression suite, Laravel Pint, frontend type checking, both production builds and whitespace checks.
- Document actual results, changed files, public-event configuration and any untested hosting behavior. Mark completion only after the relevant checks pass.

## Expected files in scope

- Blade: `resources/views/admin/event-settings/{index,form}.blade.php`, dashboard snapshot and shared fields if nested-error association needs improvement.
- Laravel: EventSettings request/controller, shared event service/rules only where necessary, application event defaults/configuration, focused feature tests.
- React: `InvitationExperience.tsx`, `InvitationPage.tsx`, event types/presentation helper and affected hero/countdown/details/success components.
- Browser QA harness and implementation report.

## Rollback and approval

- Original planning request created this document only. Implementation was subsequently approved; see the report for changes and verification. No real event records or environment files were changed.
- After approval, implement the stages sequentially and preserve the existing restore points. Clarify only unresolved event selection, private-access expectations or timezone conversion semantics.
- Revert reviewed source files individually; preserve later RSVPs, token records, audit history and saved event data. Never use a destructive Git reset or blind database restore.
- Actual hosting `/admin` cutover, removal of React admin and deployment remain separate approval checkpoints under `LARAVEL_RSVP_ADMIN_MVP_PLAN.md`.
