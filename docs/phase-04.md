# Phase 4 — Events, Reservations, and Reminder Subscriptions

## Outcome

Each lodge can publish and manage one-time or recurring events, make occurrence-specific changes, accept capacity-controlled reservations where needed, let interested people subscribe to reminders without claiming attendance, send idempotent reminder email, and expose standards-based calendar links. Every event, occurrence, reservation, reminder subscription, job, query, and public route remains bound to an explicitly resolved lodge.

Event engagement uses three distinct terms:

- Reservation: a commitment to attend that consumes limited event capacity.
- Reminder subscription: a request for notifications that does not claim attendance or capacity.
- Volunteer commitment: a future agreement to fill a named event-help position. Volunteer positions and commitments are deliberately deferred and must never be represented as reservations or reminder subscriptions.

The implementation must remain usable without paid services. Recurrence uses the MIT-licensed [rlanvin/php-rrule](https://github.com/rlanvin/php-rrule) package behind an application-owned interface. Email continues through Laravel notifications and the configured queue transport. Calendar support uses RFC 5545 iCalendar output and ordinary Google Calendar links.

See [ADR 0006](decisions/0006-materialized-event-occurrences.md).

## Product Decisions

These defaults are part of the implementation contract unless changed before coding begins:

- Event lifecycle states are draft, published, cancelled, and archived. Only published events are discoverable or eligible for reservation or reminder subscription. A draft may be deleted; a published event is cancelled or archived so engagement and audit history remain.
- Public events are visible without authentication. Masons-only and lodge-only events require authentication and eligibility before details are returned.
- Reservations are optional and are intended for events with limited capacity. Enabling reservations requires a positive capacity.
- Public events may enable unauthenticated reservations explicitly. It is disabled by default.
- Masons-only events may enable cross-lodge reservations explicitly. It is disabled by default. Without it, only eligible members of the owning lodge may reserve.
- Lodge-only events never accept another lodge's members as reservation holders.
- Reminder subscriptions are independent of reservations and enabled by default for published events.
- A recurring event allows a subscriber to choose one occurrence or the entire series. A series subscription produces reminders for each future scheduled occurrence until unsubscribed.
- Public events may enable unauthenticated reminder subscriptions. It is enabled by default. Protected events require an eligible authenticated account.
- Completing a reservation never creates a reminder subscription silently. The reservation form may offer a separately labeled, explicit reminder opt-in.
- Protected events default to Entered Apprentice qualification. Public events have no qualification requirement.
- Qualification rank is EA < FC < MM < PM. PM is derived from at least one Past Master term and is not written into the Masonic degree reference table.
- An active membership is always required for protected-event eligibility. PM qualification still requires an active membership somewhere; historical Past Master service alone does not make an ended member eligible.
- Capacity counts people, not reservation rows. Party size consumes that many spaces.
- One active reservation per normalized email per occurrence is allowed. A cancelled reservation may be replaced by a new reservation.
- Reservations always belong to one materialized occurrence, including one-time events.
- One active reminder subscription per normalized email and subscription scope is allowed. Subscription scope is either one occurrence or the recurring series.
- Recurring series may be open-ended, but occurrence generation is bounded operationally.
- Event descriptions use the existing sanitized rich-text boundary.
- Dates are entered in the event's IANA time zone and stored as UTC instants. The original local recurrence identity is retained separately.
- Published-event edits take effect immediately and are audited. Full event content versioning is outside this phase.
- Guest reservation cancellation and reminder unsubscription use separate single-purpose random tokens. Only SHA-256 hashes are stored.
- Reminder delivery is at-most-once after a delivery row is claimed. Automatic mail retries must not resend a claimed reminder; a failed delivery requires an explicit administrative retry.
- Volunteer positions, staffing counts, and volunteer commitments are outside Phase 4 implementation. The future contract in this document reserves their semantics and prevents schema shortcuts.
- Existing sites keep their current pages. The CMS Upcoming Events section becomes data-backed when published events exist. Newly initialized sites continue to receive an Events page.

## Domain Model

### Platform-Owned Reference Data

event_categories contains:

- Stable key.
- Display name.
- Optional description.
- Sort order.
- Active state.

Initial values are Stated Meeting, Degree, Practice, Education, Fellowship, Community Service, Fundraiser, and Other. A lodge enables a subset through event_category_lodge. Inactive categories remain resolvable for historical events but cannot be newly selected.

### Events

events is lodge-owned and contains:

- Lodge and optional enabled event-category identifiers.
- Stable lodge-scoped slug.
- Lifecycle status.
- Title and sanitized rich-text description.
- Location name and optional address/details.
- Contact name, email, and optional phone.
- IANA time zone.
- First local start represented as a UTC instant plus time zone.
- Duration in minutes.
- Nullable canonical RRULE text. Null means one-time.
- Visibility: public, masons, or lodge.
- Required qualification: ea, fc, mm, or pm; null for public events.
- Cross-lodge reservation flag.
- Reservations-enabled flag.
- Guest-reservations flag.
- Nullable capacity and maximum party size.
- Reminders-enabled flag, default true.
- Guest-reminders flag, allowed only for public events.
- Nullable ready public cover-media identifier belonging to the same lodge.
- Occurrence generation watermark.
- Published and archived timestamps.
- Created/updated actor identifiers where useful for audit display.
- Timestamps and soft deletion for draft cleanup only.

Database constraints and request validation enforce:

- Lodge-scoped unique slug.
- Positive duration.
- Positive capacity and party-size limits when present.
- Maximum party size cannot exceed capacity, and capacity cannot be reduced below currently confirmed party-size usage.
- Reservations require a positive capacity.
- Guest reservations only for public events.
- Cross-lodge reservations only for masons-only events.
- Guest reminder subscriptions only for public events.
- Protected visibility always has a qualification, defaulting to EA.
- Selected categories are active and enabled for the event's lodge.
- Cover media is ready, public, and lodge-owned.
- RRULE is canonical and accepted by the recurrence adapter.
- Lodge-scoped slugs cannot use the reserved public-route segments listed below.

Cancelling an event is a transaction that changes the event to cancelled, changes active reservations on its future occurrences to event-cancelled, skips unsent reminder deliveries, and sends one deduplicated cancellation notice per normalized recipient. It retains occurrences and subscriptions for history. Archiving removes a series from ordinary management lists without sending cancellation mail and is allowed only when it has no future scheduled occurrence; otherwise the administrator must cancel it. Re-publishing a cancelled or archived event is outside this phase.

### Materialized Occurrences

event_occurrences is lodge-owned and contains:

- Event and matching lodge identifiers.
- Stable recurrence key derived from the original scheduled local start in the series time zone.
- Original scheduled start in UTC.
- Effective start and end in UTC.
- Status: scheduled or cancelled.
- Nullable occurrence overrides for title, sanitized description, location, and contact fields.
- Override and cancellation timestamps.
- Generation timestamps.

The event/lodge relationship uses a composite ownership constraint. Event plus recurrence key is unique. Occurrence identifiers never come from a client without verifying both event and lodge.

One-time events receive exactly one occurrence. Recurring events are materialized from three months in the past through eighteen months in the future. A daily scheduled command extends the horizon. Requests that need a later bounded range may extend it through the same service, subject to an expansion cap.

The recurrence key is the unmodified RFC-style local occurrence identity, not the moved effective start. Moving an occurrence therefore does not change its identity or detach reservations or reminder subscriptions.

### Occurrence Reconciliation

Series schedule edits run transactionally:

1. Lock the event and its future occurrences.
2. Validate and canonicalize the new schedule.
3. Preserve past occurrences.
4. Preserve future occurrences that have reservations, occurrence-scoped reminder subscriptions, generated reminder deliveries, or explicit overrides/cancellations.
5. Remove untouched future generated occurrences.
6. Generate the new bounded window.
7. Report preserved occurrences that no longer match the new series as explicit exceptions.

Changing title, description, location, contact, category, visibility, reservation settings, or reminder settings updates the series fallback without rewriting occurrence overrides. Changing recurrence, first start, time zone, or duration requires a confirmation showing how many future protected occurrences will remain.

Cancelling one occurrence preserves reservations and reminder subscriptions. Reservations become event-cancelled for display and notification purposes, and unsent deliveries become skipped. Restoring the occurrence restores its scheduled state but does not silently restore attendee-cancelled reservations or unsubscribed reminder subscriptions.

### Reservation Field Definitions

event_reservation_fields is lodge-owned through its event and contains:

- Stable event-scoped key.
- Label and help text.
- Type: short_text, long_text, select, or checkbox.
- Required state.
- Ordered options for select fields.
- Sort order and active state.

Core name, email, optional phone, and party size fields are not custom fields. Custom responses are stored in a validated JSON object on the reservation, keyed by immutable field keys. Removing a field deactivates it so historical responses remain intelligible.

### Reservations

event_reservations contains:

- Event occurrence, event, and lodge identifiers that must agree.
- Nullable authenticated user and linked person identifiers.
- Name, normalized email, optional phone, and party size.
- Validated custom responses.
- Status: confirmed, attendee_cancelled, event_cancelled, or administratively_cancelled.
- Cancellation-token hash.
- Reservation and cancellation timestamps.

Reservation creation locks the occurrence, rejects cancelled or unpublished events, requires reservations to be enabled with capacity, rechecks eligibility, and calculates confirmed party-size capacity inside one transaction. It never trusts event, occurrence, lodge, qualification, user, or person identifiers from hidden fields.

Authenticated reservation pre-fills but still snapshots contact values for event administration. Deleting an account does not delete historical reservation rows. Reservation responses and rosters are never included in CMS or public-event serialization.

Reservation status is not used as a reminder opt-in, volunteer indicator, membership record, or authorization signal.

### Reminder Subscriptions

event_reminder_subscriptions contains:

- Event and matching lodge identifiers.
- Nullable occurrence identifier. Null means the entire recurring series; one-time events always store their sole occurrence.
- Nullable authenticated user and linked person identifiers.
- Snapshot name and normalized email.
- Status: active or unsubscribed.
- Unsubscribe-token hash.
- Subscription and unsubscription timestamps.

The database enforces one active normalized email for the same event/scope. Application validation provides the equivalent rule where a test database cannot express the partial index.

Subscription creation:

- Requires reminders to be enabled.
- Requires the event to be published and either the target occurrence to be scheduled or, for series scope, at least one future scheduled occurrence.
- Reuses event-detail visibility eligibility.
- Allows unauthenticated email subscriptions only for public events with guest reminders enabled.
- Does not require reservations to be enabled or available.
- Does not consume capacity or appear on the reservation roster.
- Sends an initial confirmation containing a secure unsubscribe link.

A recurring-series subscription applies to occurrences generated after subscription as well as those already materialized. An occurrence subscription applies only to that occurrence. When both would match the same email and occurrence, delivery is deduplicated by normalized recipient, occurrence, and reminder rule.

### Reminder Rules and Deliveries

event_reminder_rules belongs to an event and stores a positive offset in minutes. Initial UI presets are 10,080 minutes, 1,440 minutes, and 60 minutes. Event and offset are unique.

event_reminder_deliveries belongs to one active reminder subscription, one occurrence, and one reminder rule and contains:

- Lodge, event, occurrence, reminder-subscription, and rule identifiers.
- Normalized recipient email used for delivery deduplication.
- Calculated due time.
- State: pending, claimed, sent, skipped, or failed.
- Claim, send, and failure timestamps.
- Last error summary.

Subscription plus occurrence plus reminder rule is unique. A second unique delivery key covering event, occurrence, rule, and normalized recipient prevents a series subscription and an occurrence subscription from generating duplicate mail.

The dispatcher inserts missing deliveries idempotently, claims due rows atomically, and dispatches a job carrying only the delivery identifier. The job reloads and revalidates every relationship before sending. Unsubscribed subscriptions and cancelled occurrences become skipped.

Reminder subscriptions and deliveries are included in Phase 4. If delivery scheduling must be split for review, the subscription schema, consent UI, confirmation/unsubscribe flow, and delivery contract still ship before Phase 4 is considered data-model complete.

### Future Volunteer Extension Contract

Volunteer staffing is not implemented in Phase 4, but later work uses separate models:

- event_volunteer_positions: event- or occurrence-scoped position name, description, number needed, sort order, visibility/eligibility, and active state.
- event_volunteer_commitments: one user/person commitment to one position and occurrence, with committed, withdrawn, or administratively-removed status.

Later volunteer rules must:

- Allow multiple named positions such as Setup, Registration Table, Kitchen, Cleanup, or Driver.
- Support more than one volunteer per position through a needed-count value.
- Show filled and remaining counts without exposing private contact details.
- Require authentication for commitments.
- Recheck event visibility and any position eligibility.
- Keep a volunteer commitment independent from a reservation and reminder subscription.
- Permit one action to create separate records only when the UI labels and confirms each intent.

Phase 4 must not add volunteer booleans, volunteer text fields, or volunteer position responses to event_reservations.

## Recurrence Service Contract

Add an application interface such as RecurrenceExpander with operations to:

- Validate and canonicalize an RRULE.
- Expand an event between two UTC boundaries using its local start and IANA time zone.
- Return immutable occurrence candidates containing recurrence key, original UTC start, effective UTC start, and end.
- Explain the supported rule in human-readable form for previews.

The first adapter uses rlanvin/php-rrule 2.x. The package remains behind the interface so event code does not depend directly on vendor types.

Initial editor support is intentionally narrower than the full RFC:

- Does not repeat.
- Daily with interval.
- Weekly with interval and selected weekdays.
- Monthly by day of month.
- Monthly by ordinal weekday.
- Yearly.
- Optional ending after a count or on a local date.

Imported or advanced RRULE text is not exposed in the ordinary UI. Server validation rejects unsupported frequency combinations, rules producing no dates, invalid counts, and expansions beyond the safety cap.

DST behavior follows local wall-clock intent. A 7:00 PM weekly event remains at 7:00 PM in its configured zone while its UTC offset changes. Tests cover spring-forward, fall-back, nonexistent times, ambiguous times, and a non-DST zone. The chosen policy for nonexistent local time is to reject the schedule or affected override with a validation message; ambiguous times select the earlier offset consistently and record it in tests.

## Authorization and Eligibility

Add platform-owned permission events.manage. The built-in Administrator receives it. The built-in Officer receives it. Member and Non-member do not receive management access.

Management authorization requires events.manage for the explicitly loaded lodge. Platform admins retain platform override behavior but do not become event attendees automatically.

Visibility, reservation eligibility, and reminder-subscription eligibility are separate from management permission:

- Public: anyone may view. Reservations and reminder subscriptions follow their separate guest settings.
- Masons-only: an authenticated user linked to a person with an active platform membership meeting the qualification may view. Cross-lodge reservations additionally require the reservation flag; reminder subscription requires only visibility eligibility.
- Lodge-only: authenticated user linked to a person with an active membership in the event lodge meeting the qualification.

Degree qualification uses the highest qualifying active membership allowed by the visibility rule. PM is satisfied by Past Master history for the person after the active-membership gate succeeds. Missing user-person links, inactive memberships, unknown degree, or insufficient rank deny protected details, reservations, and reminder subscriptions.

Managers may view reservation rosters and reminder-subscription counts for their lodge, but subscriber email lists are shown only when needed for administration. Ordinary attendees may view only their own reservation/subscription and token-authorized result. Guest cancellation and unsubscribe endpoints return non-enumerating success responses for valid already-used tokens.

## Routes and UI

### Lodge Administration

Add an Events item under the active-lodge navigation.

Management routes follow the existing lodge-resource convention:

- `GET lodges/{lodge}/events`, `GET lodges/{lodge}/events/create`, `POST lodges/{lodge}/events`, `GET lodges/{lodge}/events/{event}/edit`, and `PUT lodges/{lodge}/events/{event}` for list, create, and edit, named under `lodges.events.*`.
- `POST lodges/{lodge}/events/{event}/publish|cancel|archive` for explicit lifecycle transitions.
- `GET lodges/{lodge}/events/{event}/occurrences` plus `PUT .../occurrences/{occurrence}` and `POST .../cancel|restore` for occurrence exceptions.
- `GET lodges/{lodge}/events/{event}/occurrences/{occurrence}/reservations` and `POST .../reservations/{reservation}/cancel` for the roster and administrative cancellation.
- Event-nested reminder-rule create/delete, subscription-count, and failed-delivery retry routes.
- `GET/PUT lodges/{lodge}/event-categories` for lodge category enablement.

The event editor is divided into Details, Schedule, Audience, Reservations, Reminders, and Publication sections. Reservations and Reminders have separate enablement, guest, and policy controls. No control uses one umbrella label for both intents. Date/time controls display in the selected event time zone. The recurrence builder shows a plain-language preview and the next five occurrences before save.

The occurrence screen uses a paginated date range rather than rendering an unbounded series. Exception rows show original and effective date/time, cancellation state, reserved capacity, reminder-subscriber count, and actions. Destructive or schedule-reconciliation actions use accessible confirmation modals.

The reservation roster shows attendee name, email, phone, party size, responses, status, and occurrence. It is labeled Reservations throughout the UI. CSV export is not part of this phase.

### Public and Attendee Routes

Public URLs are lodge-explicit:

- `GET l/{lodge:slug}/events` lists public occurrences.
- `GET l/{lodge:slug}/events/{occurrence}` displays an occurrence after public/protected eligibility.
- `POST l/{lodge:slug}/events/{occurrence}/reservations` creates a permitted reservation.
- `GET` then `POST l/{lodge:slug}/reservations/cancel/{token}` displays confirmation and performs token-based cancellation.
- `POST l/{lodge:slug}/events/{event}/reminders` creates an occurrence- or series-scoped subscription.
- `GET` then `POST l/{lodge:slug}/reminders/unsubscribe/{token}` displays confirmation and performs token-based unsubscription.
- `GET l/{lodge:slug}/events/{occurrence}.ics` downloads an occurrence and `GET l/{lodge:slug}/calendar.ics` returns the public lodge feed.

Register every reserved public event/calendar prefix before the existing `l/{lodge:slug}/{pageSlug}` CMS catch-all. Event slugs are not accepted as top-level route segments, and `events`, `calendar.ics`, `reservations`, and `reminders` become reserved CMS page slugs.

Protected event routes authenticate before returning title, description, location, or occurrence data. A 404 response is preferred when disclosure of another lodge's protected event would reveal its existence.

### CMS Integration

Replace the Upcoming Events placeholder behavior with a data-backed section while retaining the same section type for existing content. Its configuration supports:

- Heading and empty-state message.
- Optional enabled-category filter.
- Maximum items from 1 through 20.
- Optional link to the complete public event list.

Only published public occurrences for the public site's resolved lodge are returned. Cancelled, archived, protected, past, and cross-lodge occurrences are excluded. The renderer uses the established public-site theme, responsive layout, dark mode, and lodge branding.

## Calendar Integration

Use an application-owned ICalendarBuilder and no paid calendar SDK.

- Occurrence download emits one VEVENT.
- Series download emits a stable UID, DTSTART in the event zone, RRULE, EXDATE values for cancelled occurrences, and RECURRENCE-ID overrides.
- Public lodge calendar feed contains only public published occurrences.
- Protected occurrence download rechecks current eligibility.
- Google Calendar uses a generated add-event URL for a single effective occurrence.
- Apple Calendar, Android clients, and Outlook use the ICS endpoint.

Output escapes text according to RFC 5545, folds long lines, uses CRLF line endings, and never includes reservation data, reminder-subscriber data, private contacts, internal identifiers beyond opaque stable UIDs, or cancellation/unsubscribe tokens.

## Notifications and Scheduling

Add queued notifications for:

- Reservation confirmation and cancellation-management link.
- Reminder-subscription confirmation and unsubscribe link.
- Administrative reservation cancellation where an email exists.
- Occurrence cancellation or material change.
- Scheduled reminder.

Notification jobs carry database identifiers, not serialized models or active lodge state. Every job reloads the lodge, event, occurrence, reservation or reminder subscription, and verifies ownership. Occurrence-change notifications merge reservation and reminder audiences by normalized email so one person receives one message. Reply-to may use the event contact email or lodge public email; the configured system sender remains authoritative.

Register:

- An every-minute reminder-dispatch command with overlap prevention.
- A daily occurrence-horizon command.

Commands are safe to rerun. Queue workers are not assumed to execute exactly once.

## Validation, Security, and Privacy

- Sanitize event and occurrence rich text with WebsiteHtmlSanitizer.
- Apply route throttles to guest reservation, guest reminder subscription, cancellation, and unsubscribe routes.
- Normalize reservation/subscription email and reservation phone consistently with person/account workflows.
- Use separate cryptographically random 256-bit cancellation and unsubscribe tokens and store only hashes.
- Never place tokens in logs, audit payloads, analytics, or reminder-delivery errors.
- Use generic cancellation responses to reduce token and email enumeration.
- Escape custom responses and calendar fields.
- Do not expose reservation or reminder-subscription counts publicly unless a later requirement explicitly enables them.
- Audit publication, schedule reconciliation, occurrence changes, reservation administrative changes, category enablement, and reminder retries.
- Do not audit guest token hashes or full custom response contents.
- Cache keys, if introduced, include lodge, event, occurrence, visibility, and publication state. Publication and occurrence changes invalidate relevant public output.

## Database and Migration Order

Use domain-oriented filenames without Phase labels.

1. Create event categories and lodge enablement.
2. Create events with lodge ownership and lifecycle constraints.
3. Create materialized occurrences and ownership constraints.
4. Create reservation field definitions and reservations.
5. Create reminder rules, subscriptions, and deliveries.
6. Seed categories and extend the role-permission catalog.

PostgreSQL partial indexes enforce one active normalized-email reservation per occurrence, one active normalized-email reminder subscription per scope, and efficient upcoming-occurrence/reminder queries. SQLite test behavior receives equivalent application validation where an index feature differs. Migration rollback order is the reverse of ownership dependencies.

## Automated Tests

### Unit

- RRULE validation and canonicalization.
- Daily, weekly, monthly, ordinal-monthly, yearly, count, and until expansion.
- UTC conversion and DST boundaries.
- Stable recurrence keys after occurrence movement.
- Qualification ordering including PM.
- ICS escaping, folding, recurrence, cancellation, and override output.
- Cancellation-token hashing and comparison.

### Laravel Feature

- Event CRUD, category enablement, publication, archive, and permissions.
- Two-lodge ownership tests for every nested event and occurrence route.
- One-time and recurring materialization.
- Series reconciliation with untouched, overridden, cancelled, reserved, and subscribed occurrences.
- Occurrence cancel, restore, move, location change, and rich-text sanitization.
- Public, masons-only, and lodge-only visibility.
- Same-lodge and permitted cross-lodge reservation.
- Inactive membership, missing person link, insufficient degree, and PM cases.
- Guest reservation and guest reminder-subscription enabled/disabled behavior.
- Capacity and party-size race protection.
- Custom-field validation and retained inactive-field responses.
- Duplicate reservation prevention and cancellation/re-reservation.
- Reminder subscription without reservation, occurrence/series scope, deduplication, and unsubscribe.
- Secure cancellation/unsubscribe tokens and non-enumerating responses.
- Reminder dispatch, claim, skip, failure, manual retry, and retry deduplication.
- Event-change and cancellation notifications.
- Public CMS projection and dark/light rendering props.
- ICS occurrence, series, and lodge feed isolation.
- Audit payload boundaries.

All lodge-owned feature tests create Lodge A and Lodge B and attempt route, payload, occurrence, category, reservation, reminder-subscription, and media identifier substitution.

### Playwright

The critical browser path:

1. Enable event categories for Lodge A.
2. Create and publish a weekly recurring event spanning a DST boundary.
3. Verify the next-five-occurrences preview.
4. Cancel one occurrence.
5. Move a second occurrence.
6. Change the location of a third.
7. Enable capacity-controlled reservations and reminders.
8. Reserve capacity for an eligible member on one occurrence and explicitly opt into reminders.
9. Subscribe a different eligible member to reminders without making a reservation.
10. Complete a permitted guest reservation and a separate guest reminder subscription for a public event.
11. Cancel the reservation and unsubscribe through their separate emailed secure links.
12. Verify the public Upcoming Events section and event detail.
13. Download and inspect an ICS response.
14. Switch to Lodge B and prove Lodge A identifiers are rejected.

## Manual Acceptance

1. Create one-time, bounded recurring, and open-ended recurring events.
2. Confirm local times remain correct through DST.
3. Confirm series edits explain and preserve protected exceptions.
4. Cancel and restore individual occurrences without losing reservation or reminder-subscription history.
5. Exercise each visibility and qualification level with eligible and ineligible accounts.
6. Fill the final capacity slot and verify the next request cannot exceed capacity.
7. Confirm guest reservations are unavailable unless explicitly enabled.
8. Subscribe to reminders without reserving, then confirm reminder dispatch can run twice without duplicate delivery.
9. Verify cancellation and changed-event notifications in Mailpit.
10. Import ICS files into Apple Calendar and Outlook and open the Google Calendar link.
11. Verify public event cards and details in light, dark, mobile, and desktop layouts.
12. Confirm no reservation roster, reminder-subscriber data, protected-event detail, or cross-lodge identifier is exposed publicly.
13. Confirm the UI never presents reservation, reminder subscription, or future volunteer commitment as interchangeable concepts.

## Implementation Work Packages

Every package is intentionally bounded for Terra. Complete and review one package before starting a dependent package. The contracts below are locked by this plan; if implementation proves one invalid, stop and amend the plan rather than improvising a different schema or security rule.

### Locked Implementation Map

Use these names unless an existing project convention requires a namespace-only adjustment:

- Enums: `EventStatus`, `EventVisibility`, `EventQualification`, `EventOccurrenceStatus`, `EventReservationStatus`, `ReminderSubscriptionStatus`, `ReminderDeliveryStatus`, and `ReservationFieldType`.
- Models: `EventCategory`, `Event`, `EventOccurrence`, `EventReservationField`, `EventReservation`, `EventReminderRule`, `EventReminderSubscription`, and `EventReminderDelivery`.
- Recurrence contracts: `RecurrenceExpander`, `RruleRecurrenceExpander`, `OccurrenceKey`, `EventOccurrenceMaterializer`, and `EventScheduleReconciler` under `app/Domain/Events`.
- Interaction services: `EventEligibility`, `EventReservationService`, `EventReminderSubscriptionService`, and `EventReminderDispatcher` under `app/Domain/Events`.
- Calendar service: `ICalendarBuilder` under `app/Domain/Events`.
- HTTP controllers live under `app/Http/Controllers/Lodge/Events` for management and `app/Http/Controllers/PublicSite/Events` for public/protected interaction. Validation belongs in matching form requests; controllers must remain orchestration-only.
- Management pages live under `resources/js/pages/lodge/events`; public event components live under `resources/js/components/website/events` and reuse the established public-site shell.
- Focused tests use `tests/Unit/Domain/Events`, `tests/Feature/Events`, and `tests/Browser/events.spec.ts`.

All mutations that combine an event with a child identifier must load the child through the event and lodge relationship. Services return domain results or throw validation/authorization exceptions; they do not return partially successful state.

### P4-01 Dependency, Schema, and Reference Data — Terra

Prerequisite: none.

Deliver:

- Install the locked MIT recurrence dependency and record its version in the dependency/license documentation.
- Add the enums, migrations, models, factories, relationships, casts, database indexes, ownership constraints, and reference-category seeder defined above.
- Add `events.manage` to permission reference data and existing built-in lodge-administrator role synchronization.
- Keep reservations, reminder subscriptions, and future volunteer commitments distinct; do not add a generic event-response table.

Tests and gate:

- Migration/model relationship tests, factory smoke tests, seeder idempotency, role synchronization, `composer audit`, and the full Laravel suite.

### P4-02 Category Configuration — Terra

Prerequisite: P4-01.

Deliver:

- Lodge category enable/disable form request, controller, routes, audit event, and responsive settings UI.
- Treat platform reference categories as read-only in lodge UI; inactive categories remain readable on old events and unavailable for new selection.

Tests and gate:

- Enablement, inactive-category behavior, authorization, validation, audit data, and cross-lodge identifier rejection.

### P4-03 Recurrence and Occurrence Materialization — Terra

Prerequisite: P4-01.

Deliver:

- Implement `RecurrenceExpander` and the dependency-backed `RruleRecurrenceExpander`; no controller or Vue component may parse recurrence rules.
- Implement immutable `OccurrenceKey` from the original local start plus event time zone.
- Implement bounded generation in `EventOccurrenceMaterializer` and transactional schedule changes in `EventScheduleReconciler` using the preservation rules in this plan and ADR 0006.
- Add the rolling-horizon Artisan command and scheduler entry. The command must be safe to rerun and process events in bounded chunks.

Tests and gate:

- Unit fixtures for one-time, daily, weekly, monthly, bounded, and open-ended schedules; spring/fall DST; invalid rules; horizon extension; stable keys after moves; preservation of exceptions, reservations, subscriptions, and deliveries; transaction rollback.

### P4-04 Event Management — Terra

Prerequisites: P4-01 and P4-03.

Deliver:

- Policies, form requests, thin CRUD controllers, lodge routes, audit events, list/filter page, editor panels, publication controls, and recurrence preview.
- Use the existing sanitized rich-text pipeline and only existing ready public media owned by the lodge.
- Present distinct Details, Schedule, Audience, Reservations, Reminders, and Publication panels.

Tests and gate:

- CRUD, validation, permission matrix, tenant identifier attacks, sanitization, category availability, media ownership/readiness, recurrence preview, responsive UI, typecheck, and lint.

### P4-05 Occurrence Exceptions — Terra

Prerequisites: P4-03 and P4-04.

Deliver:

- Date-range occurrence management, edit/move, cancel, and restore actions through the reconciliation service.
- Before a destructive series change, return counts of protected occurrences and require explicit confirmation.
- Record audits and emit the occurrence-change hooks consumed by later notification work.

Tests and gate:

- Stable identities, protected-row preservation, state transitions, confirmation requirement, direct identifier attacks, audits, and transaction rollback.

### P4-06 Eligibility and Protected Details — Terra

Prerequisite: P4-01.

Deliver:

- Implement `EventEligibility` for visibility, active membership, configured cross-lodge access, degree rank, and Past Master qualification.
- Apply it consistently in policies/controllers for protected event detail, reservation, and reminder subscription. Active-lodge selection is never proof of eligibility.

Tests and gate:

- The complete visibility/qualification matrix with at least three lodges, ended/inactive memberships, cross-lodge flags on and off, and direct protected URLs.

### P4-07 Reservations — Terra

Prerequisites: P4-05 and P4-06.

Deliver:

- Implement custom reservation fields and `EventReservationService`.
- Lock the occurrence row while checking active reserved party size and creating a reservation. Enforce normalized-email uniqueness, guest policy, positive capacity, qualification, and occurrence state inside one transaction.
- Generate and store only a hash of a high-entropy cancellation token; return the raw token only for the confirmation message/link.
- Add authenticated/public reservation forms, confirmation, secure cancellation, administrative occurrence roster, filters, and separate reservation-confirmation email.
- A reservation must never create or stand in for a reminder subscription. P4-08 adds the optional, separately labeled opt-in after the subscription service exists.

Tests and gate:

- Capacity race using two independent database connections, duplicate email, party size, custom fields, guest policy, eligibility, token hashing, cancellation/reservation-again, cancelled occurrence, tenant attacks, and roster authorization.

### P4-08 Reminder Subscriptions — Terra

Prerequisites: P4-05, P4-06, and P4-07.

Deliver:

- Implement `EventReminderSubscriptionService` with occurrence and series scopes, normalized-email uniqueness per scope, eligibility, guest policy, explicit consent, and hashed unsubscribe tokens.
- Add authenticated/public reminder-only forms, subscription confirmation email, status UI, and secure unsubscribe route. These flows must work without a reservation.
- Add the separately labeled reminder opt-in to the reservation confirmation flow; it invokes the same subscription service and stores a separate subscription record.
- Show managers subscription counts, not subscriber personal data on public views.

Tests and gate:

- Occurrence/series scope, one-time scope normalization, duplicate subscription, reservation independence, protected-event eligibility, guest policy, token hashing, unsubscribe/resubscribe, cancellation behavior, and tenant attacks.

### P4-09 Reminder Delivery — Terra

Prerequisite: P4-08.

Deliver:

- Implement reminder rules, `EventReminderDispatcher`, scheduled dispatch command, atomically claimed delivery rows, queued email, failure state, and explicit retry.
- Resolve a series subscription plus an occurrence subscription for the same normalized recipient to one delivery per rule/occurrence/recipient.
- Reload and verify the complete ownership chain in queued work; skip cancelled/unpublished events, cancelled occurrences, and inactive subscriptions.

Tests and gate:

- Repeated dispatch, overlapping subscription scopes, concurrent claims, queue retry, stale claim recovery, cancellation races, failed and explicit retry states, and cross-lodge job payload attacks.

### P4-10 Public Events CMS — Terra

Prerequisites: P4-04 and P4-05.

Deliver:

- Add the data-backed Upcoming Events section configuration, public event list/detail, empty state, cover image, light/dark responsive styling, and public-only projection.
- Reuse the backend eligibility and occurrence read contracts; never expand recurrence or infer protected eligibility in Vue.

Tests and gate:

- CMS validation, public visibility, cancelled/past exclusion, tenant isolation, protected-data exclusion, responsive/browser coverage, typecheck, lint, and build.

### P4-11 Calendar Output — Terra

Prerequisites: P4-03, P4-05, and P4-06.

Deliver:

- Implement `ICalendarBuilder`, occurrence and series ICS endpoints, public lodge feed, Google Calendar URL, correct content headers, and UI actions.
- Public feeds contain only published public occurrences. Protected endpoints require current eligibility and never include reservation or subscriber information.

Tests and gate:

- RFC fixture tests, UTF-8 escaping/folding, stable UID, time zones, overrides/cancellations, authentication, protected disclosure, lodge isolation, and calendar import spot checks.

### P4-12 Integration and Hardening — Terra

Prerequisites: all prior packages.

Deliver:

- Verify scheduler/queue configuration, complete the Playwright critical path and direct-identifier attack matrix, reconcile all affected documentation, and add operational notes.
- Resolve every type, lint, IDE, security-audit, test, and build finding; request approval before suppressing any warning.
- Do not implement volunteer staffing. Verify that no reservation or reminder field is mislabeled or reserved for volunteer use.

## Dependency and Parallelization Map

- P4-01 blocks all implementation.
- After P4-01, P4-02, P4-03, and P4-06 are independent.
- P4-04 waits for the recurrence contract from P4-03.
- P4-05 waits for P4-03 and the basic event routes from P4-04.
- P4-07 waits for P4-05 and P4-06; P4-08 then adds independent subscriptions and the reservation-flow opt-in on top of P4-07.
- P4-09 waits only for P4-08.
- P4-10 waits for stable event and occurrence read contracts from P4-04/P4-05.
- P4-11 waits for P4-03, P4-05, and P4-06 and may proceed independently of reservation/reminder UI work.
- P4-12 is the final integration gate.

## Agent Handoff Contract

Every delegated package prompt must include:

- The package identifier and exact deliverables from this document.
- Explicit prerequisite commits or verified contracts.
- Files/directories the package may modify.
- Required focused tests and the existing full-suite command.
- A reminder to preserve unrelated dirty-worktree changes.
- A prohibition on Phase labels in implementation filenames, class names, and comments.
- A requirement to report any needed schema, authorization, recurrence, reservation, subscription, delivery, or token-contract change instead of silently making it.
- A requirement to resolve warnings or request approval before suppressing any.

Each handoff should direct Terra to implement only one package, read this whole document and ADR 0006 first, and stop at that package's gate. Architectural choices in this document are already resolved; escalation is required only when repository constraints contradict a locked contract.

## Definition of Done

Phase 4 is complete only when:

- All work-package tests and the full Laravel suite pass.
- Frontend typecheck, lint, production build, browser typecheck, and Playwright critical path pass.
- Composer and npm audits have no unreviewed findings.
- Queue commands are idempotent and documented.
- Public and protected output has been inspected for tenant/privacy leakage.
- Manual acceptance is complete at mobile and desktop widths.
- Architecture, domain, authorization, tenancy, testing, README, and ADR documents match the implementation.
- No implementation artifact uses a Phase label in its filename, class name, or code comment.

## Non-Goals

- Regional event discovery.
- Paid tickets, dues, or payment processing.
- Waitlists.
- Seating charts.
- Two-way calendar synchronization.
- Calendar-provider OAuth.
- Broad member portal or directory changes.
- SMS reminders.
- Push notifications.
- Public attendee lists.
- Volunteer position definitions, staffing commitments, staffing reminders, and volunteer rosters.
- CSV exports.
- Historical import tooling.
