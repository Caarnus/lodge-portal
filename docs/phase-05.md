# Phase 5 — Volunteer Staffing and Commitments

## Outcome

Each lodge can add named volunteer needs to an event series or one occurrence, show eligible signed-in members how many openings remain, accept independent volunteer commitments, give event managers a private occurrence roster, and send one idempotent staffing reminder before each active commitment.

This is a third event interaction with its own records and lifecycle:

- A reservation is a commitment to attend and may consume event capacity.
- A reminder subscription is consent to receive ordinary event notifications and does not imply attendance.
- A volunteer commitment is an authenticated agreement by one linked person to fill one named position for one occurrence.

Creating or changing any one of these records must never create, authorize, cancel, or infer either of the others. Phase 5 extends the existing Phase 4 event domain; it does not replace its reservation, reminder, eligibility, recurrence, or occurrence contracts.

## Repository Baseline and Scope

Implementation starts from the repository as it exists after Phase 4:

- Event models and domain services use `app/Models` and `app/Domain/Events`.
- Event management controllers currently use the flat `app/Http/Controllers` namespace and event pages use `resources/js/pages/events`. Follow those existing paths instead of moving Phase 4 files during this phase.
- Public event details are served by `PublicEventController` and `resources/js/pages/public/EventDetail.vue`.
- The authenticated dashboard is currently a placeholder at `resources/js/pages/Dashboard.vue`; Phase 5 may replace only the content needed for upcoming volunteer commitments.
- Lodge event management uses the existing `events.manage` permission. Phase 5 does not add a second staffing permission.
- `EventScheduleReconciler`, event schedule-change confirmation, event/occurrence cancellation, and occurrence restoration must be extended to account for volunteer records.
- The existing scheduler runs every minute and queue jobs carry stable database identifiers. Volunteer reminder work follows the same operational pattern but uses dedicated delivery records and jobs.
- PostgreSQL is the production database. Database-level partial unique indexes are required where this plan names them; SQLite-only behavior is not an acceptable substitute for concurrency invariants.

Before implementation, run the full existing test and frontend gates to establish a clean baseline. If Phase 4 behavior is failing, repair or document that prerequisite separately rather than weakening Phase 5 tests.

## Locked Product Decisions

These decisions are implementation requirements:

- Volunteer staffing is available only on an existing event. A draft event may be configured, but members can see or join positions only after the event is published and the occurrence is scheduled and in the future.
- A series position applies to every scheduled occurrence of that event. An occurrence position applies only to its named occurrence. Both kinds may appear together.
- An occurrence-specific position does not override or hide a series position with the same name. They are distinct records. The management UI warns about duplicate names within the same effective occurrence but does not merge them.
- Positions use a positive `needed_count` as a hard signup capacity. Self-service and manager-created commitments both fail when active commitments equal the needed count.
- Reducing `needed_count` below the current active committed count is rejected. Deactivating a position is allowed and preserves its active commitments, but blocks new ones.
- Deactivating a position means the staffing need is no longer offered. It removes that position's commitments from ordinary member upcoming views and suppresses unsent staffing reminders without rewriting commitment history. Managers can still see the inactive position and its commitments on the roster and may remove them administratively.
- A person may commit to multiple different positions for the same occurrence. The system does not impose a total-position limit, detect overlapping duties, or decide how much a volunteer can handle.
- A person may have only one active commitment to the same position and occurrence. After withdrawing or administrative removal, the person may commit again; the new commitment is a new historical row.
- Commitments require an authenticated, approved, email-verified user linked to a non-merged person with an active membership in the event-owning lodge. Public event visibility does not make an unlinked account or guest eligible to volunteer.
- Cross-lodge volunteering is not included. A membership in another lodge, `allows_cross_lodge_reservations`, a reservation, a reminder subscription, or event visibility never substitutes for an active membership in the event-owning lodge.
- For protected events, the linked person must also satisfy the event's configured qualification. For a public event, an active membership in the owning lodge is sufficient.
- Event managers may create a commitment for another person only when that person has a linked approved account and currently passes the same volunteer eligibility rules. Management authority is not an eligibility bypass for the target volunteer.
- A manager may not create a commitment for an arbitrary name, email, guest, or person without an account in Phase 5.
- Managers may create or administratively remove commitments. A member may withdraw only their own active commitment and only before the occurrence starts. Managers may remove an active commitment after the start for data correction, with an audit event.
- Occurrence or event cancellation does not rewrite an active commitment to a new status. It preserves the historical agreement, makes the position unavailable, removes it from ordinary upcoming views, and suppresses unsent staffing reminders. Restoring the occurrence makes still-active commitments upcoming again only when their position is active.
- Position and commitment history is retained. Positions with commitment history are deactivated rather than deleted. A position without commitments may be deleted only while its event is a draft.
- Volunteer filled and remaining counts include only `committed` rows. `remaining = max(needed_count - committed_count, 0)`.
- Position names, descriptions, instructions, counts, and the current user's own commitment state are visible only to an eligible authenticated member or event manager. Volunteer names and contact details appear only on the authorized manager roster. No volunteer staffing data is serialized to an anonymous public response.
- An event manager can see the volunteer's display name on the staffing roster. Email and phone are included only when that manager also passes the existing lodge-scoped Person contact-access rule (normally through `people.view` and reachability); an `events.manage`-only custom role receives no contact fields.
- One staffing reminder is sent 24 hours before the occurrence. The initial offset is application configuration, not a lodge-editable rule. A commitment made less than 24 hours before a future occurrence is due immediately. No reminder is created after the occurrence starts.
- A volunteer commitment is sufficient authorization for its operational staffing reminder. It does not subscribe the volunteer to ordinary event reminders. Withdrawal or administrative removal stops any unsent staffing reminder.
- Staffing reminders are at-most-once after a delivery is claimed. Queue retries do not resend a claimed delivery. Failed delivery requires the existing style of explicit manager retry.
- Phase labels such as `PhaseFive`, `P5`, or `phase_05` must not appear in implementation filenames, class names, route names, database names, or code comments.

## Domain Model

### Volunteer Positions

Add `event_volunteer_positions` as a lodge-owned event child with:

- `id`.
- `event_id` and `lodge_id`.
- Nullable `event_occurrence_id`; null means series scope, non-null means occurrence scope.
- `name`, limited to 120 characters.
- Nullable plain-text `description`, limited to 2,000 characters. Do not render this field as HTML.
- Positive unsigned `needed_count`.
- Unsigned `sort_order`, default zero.
- `is_active`, default true.
- Nullable `created_by` and `updated_by` user identifiers for manager display and audit support.
- Timestamps.

Required database structure:

- Composite foreign key from `(event_id, lodge_id)` to `events (id, lodge_id)`.
- Composite foreign key from `(event_occurrence_id, event_id, lodge_id)` to `event_occurrences (id, event_id, lodge_id)`. The nullable occurrence component permits series scope.
- Unique `(id, event_id, lodge_id)` for downstream composite ownership references.
- Index `(event_id, event_occurrence_id, is_active, sort_order)`.
- PostgreSQL check constraint `needed_count > 0`.
- PostgreSQL check constraint that occurrence scope is null or belongs to the same event/lodge, reinforced by the composite foreign key.

There is deliberately no uniqueness constraint on position name. Identity comes from the position identifier, not editable display text.

Available member-facing positions for an occurrence are the union of:

1. Active series positions for its event where `event_occurrence_id` is null.
2. Active occurrence positions whose `event_occurrence_id` matches it.

Order by `sort_order`, then name, then identifier. Always calculate counts for a specific occurrence; a series position does not have one count shared across the series.

Management rosters use the same scope union but also include inactive positions that have commitment history, clearly labeled inactive. Inactive positions are never returned as available signup choices.

Editing a series position changes its display data and capacity for every occurrence, subject to the invariant that `needed_count` cannot fall below the largest active count on any occurrence. The edit UI must report the blocking occurrence when this validation fails. Moving a position between series and occurrence scope after creation is not allowed. A manager creates a new position and deactivates the old one instead.

### Volunteer Commitments

Add `event_volunteer_commitments` with:

- `id`.
- `event_volunteer_position_id`.
- `event_occurrence_id`, `event_id`, and `lodge_id`.
- `user_id` and `person_id` captured from the eligible linked account.
- Status: `committed`, `withdrawn`, or `administratively_removed`.
- `committed_at`.
- Nullable `withdrawn_at`.
- Nullable `administratively_removed_at`.
- Nullable `created_by`; null is not used in normal HTTP flows, and self-service stores the volunteer's own user identifier.
- Nullable `removed_by` for administrative removal; self-withdrawal leaves it null.
- Timestamps.

Required database structure:

- Composite foreign key from `(event_occurrence_id, event_id, lodge_id)` to the occurrence.
- Composite foreign key from `(event_volunteer_position_id, event_id, lodge_id)` to the position.
- Foreign keys to `users` and `people`. Use `restrictOnDelete` for people and `nullOnDelete` for users only if existing account deletion semantics require it. Creation always requires both identifiers. A historical row with a deleted user remains attributable to the person but cannot be reactivated or mailed.
- Unique `(id, event_volunteer_position_id, event_occurrence_id, event_id, lodge_id)` for delivery ownership validation.
- Indexes `(event_occurrence_id, status)`, `(person_id, status, event_occurrence_id)`, and `(lodge_id, status)`.
- PostgreSQL partial unique index on `(event_volunteer_position_id, event_occurrence_id, person_id)` where status is `committed`.
- PostgreSQL status and timestamp consistency checks where practical: committed has neither removal timestamp; withdrawn has `withdrawn_at`; administratively removed has `administratively_removed_at`.

The composite keys prove that the position and occurrence share an event and lodge. Application validation must additionally prove the position is either series-scoped or scoped to the exact occurrence. A position scoped to occurrence A can never be used with occurrence B even when both belong to the same event.

Commitment rows are immutable with respect to lodge, event, occurrence, position, user, and person after creation. Status transitions are:

| From | Action | To |
|---|---|---|
| none | eligible member or manager commits | committed |
| committed | same member withdraws before start | withdrawn |
| committed | manager removes | administratively_removed |

No transition returns a historical row to `committed`. Recommitting creates a new row and is protected by the partial active unique index.

### Staffing Reminder Deliveries

Add `event_volunteer_reminder_deliveries` with:

- `id`.
- `event_volunteer_commitment_id` and `event_volunteer_position_id`.
- `event_occurrence_id`, `event_id`, and `lodge_id`.
- Snapshot `recipient_email` and `normalized_recipient_email`, populated when the delivery is created or refreshed before claim.
- `due_at`, calculated from the occurrence effective `starts_at` minus the configured offset.
- Status: `pending`, `claimed`, `sent`, `skipped`, or `failed`.
- Nullable `skip_reason`: `commitment_inactive`, `event_inactive`, `occurrence_cancelled`, `occurrence_started`, `account_unavailable`, or `ownership_invalid`.
- Claim, sent, skipped, and failed timestamps.
- Nullable `attempted_at`, written atomically immediately before invoking the mail transport.
- Nullable bounded `last_error`.
- Timestamps.

Required database structure:

- Composite foreign keys that bind the commitment, position, occurrence, event, and lodge ownership chain.
- Unique `event_volunteer_commitment_id`. Phase 5 sends exactly one reminder per commitment.
- Index `(status, due_at)` for dispatch.
- Index `(event_occurrence_id, status)` for lifecycle hooks.

Use dedicated `VolunteerCommitmentStatus` and `VolunteerReminderDeliveryStatus` enums. Do not add volunteer values to reservation, reminder-subscription, or ordinary reminder-delivery enums.

## Eligibility and Authorization

Add `VolunteerEligibility` under `app/Domain/Events`. It may reuse qualification logic extracted from `EventEligibility`, but it must expose an explicit volunteer decision and must not call `canReserve` as a proxy.

`canVolunteer(User $user, Event $event)` requires all of:

- Approved and email-verified authenticated user.
- A linked `person_id` whose person is not merged or soft-deleted.
- Event is published.
- Event-owning lodge is active.
- Active membership for that person in the event-owning lodge.
- Event qualification met when configured.

The following do not grant volunteer eligibility:

- Active lodge selection.
- Platform administrator status by itself.
- `events.manage` by itself.
- A membership in a different lodge.
- Public event visibility.
- `allows_cross_lodge_reservations`.
- A reservation, reminder subscription, or prior commitment.

Management actions require `events.manage` for the explicit lodge. Platform administrators retain the existing permission behavior for event management, but an administratively selected target volunteer must still pass `VolunteerEligibility`.

Self-service actions require ownership by both `user_id` and the user's current `person_id`. Checking only one identifier is insufficient after account-link changes. If the link changes, managers retain the ability to correct the historical commitment.

All nested routes must load and validate this chain:

`lodge -> event -> occurrence -> position -> commitment/delivery`

A valid identifier from another event, occurrence, position, person, commitment, delivery, or lodge returns 404 for ownership mismatch and 403 for a correctly owned resource lacking permission. Never query a child globally and then trust a submitted parent.

### Authorization Matrix

| Action | Anonymous visitor | Eligible owning-lodge member | `events.manage` user | Platform administrator |
|---|---:|---:|---:|---:|
| See staffing positions/counts | No | Published future occurrence | Assigned lodge, including drafts | Any lodge |
| Create own commitment | No | Yes, while open and available | Only if personally eligible | Only if personally eligible |
| Withdraw own commitment | No | Before occurrence start | Before occurrence start | Before occurrence start |
| View own upcoming commitments | No | Yes | Yes | Yes |
| Manage positions | No | No | Assigned lodge | Any lodge |
| View roster/contact details | No | No | Assigned lodge | Any lodge |
| Add/remove another person's commitment | No | No | Assigned lodge; target must be eligible | Any lodge; target must be eligible |
| Retry failed staffing reminder | No | No | Assigned lodge | Any lodge |

Roster identity and roster contact access are separate decisions. `events.manage` permits seeing the committed person's display name because a named roster is intrinsic to staffing. Email and phone require the existing Person contact-access check for the same lodge; absent that permission/reachability, return null contact fields rather than rejecting the roster.

## Position and Commitment Services

### Position Writes

Use dedicated form requests and a thin `EventVolunteerPositionController`. A position write must:

1. Authorize the explicit lodge and event.
2. If occurrence-scoped, load the occurrence through the same event/lodge.
3. Validate immutable scope on update.
4. Validate plain-text name/description, positive count, sort order, and active state.
5. Lock the position when lowering needed count.
6. For a series position, group active commitments by occurrence and reject a value below the largest group count.
7. For an occurrence position, reject a value below that occurrence's active count.
8. Persist and audit the before/after state.

Deleting a never-used draft position and deactivating a used position are separate endpoints. Do not overload `DELETE` to silently deactivate.

### Commitment Creation

Add `VolunteerCommitmentService` under `app/Domain/Events`. Both self-service and manager flows call the same service.

Creation is one transaction:

1. Reload the lodge, event, occurrence, position, user, and person ownership chain from stable identifiers.
2. Lock the position row. Position-level locking serializes signups to a series position across occurrences; that conservative scope is acceptable for this phase.
3. Recheck event publication, lodge state, future scheduled occurrence, active position, and exact scope.
4. Recheck current target-user/person link and `VolunteerEligibility`.
5. Count `committed` rows for this position and occurrence.
6. Reject when the count is at `needed_count`.
7. Reject an existing active commitment for the same person, position, and occurrence. Convert the database unique violation into the same validation response for a concurrent duplicate.
8. Create a new `committed` row with both target and actor identifiers.
9. Record `volunteer_commitment.created` with lodge, actor, target person, event, occurrence, and position identifiers.

The service returns the created commitment. It never creates a reservation, reminder subscription, ordinary reminder delivery, role, or membership.

### Withdrawal and Administrative Removal

Status change is one transaction that locks the commitment and revalidates its complete ownership chain.

- Self-withdraw requires the current account and person to own the commitment, status `committed`, and `now < occurrence.starts_at`.
- Administrative removal requires `events.manage` and status `committed`. It may occur after the start for correction.
- Repeating the same action is idempotent from a persistence perspective and returns a clear already-inactive result; it must not create a second audit event.
- Withdrawal writes `withdrawn`, `withdrawn_at`, and audit action `volunteer_commitment.withdrawn`.
- Manager removal writes `administratively_removed`, `administratively_removed_at`, `removed_by`, and audit action `volunteer_commitment.administratively_removed`.
- In the same transaction, pending or claimed staffing delivery rows become skipped with reason `commitment_inactive`. A job that already started must also recheck status before mail delivery.

## Event and Recurrence Integration

Phase 5 must modify the existing Phase 4 lifecycle code, not add disconnected cleanup commands.

### Schedule Reconciliation

An occurrence is protected from deletion during series schedule reconciliation when it has any of:

- Existing Phase 4 overrides, cancellation, reservations, reminder subscriptions, or reminder deliveries.
- An occurrence-scoped volunteer position, active or inactive.
- A volunteer commitment in any status.
- A staffing reminder delivery in any status.

Add these relationships to `EventOccurrence` and to both the protected-count confirmation query in `EventController` and the deletion exclusions in `EventScheduleReconciler`. A series-scoped position alone does not protect every generated occurrence because it is not owned by a specific occurrence.

Moving an occurrence retains its commitments. Pending staffing deliveries receive the new `due_at`. For a claimed but unsent delivery, the job recomputes the configured due time:

- If the new due time is in the future, atomically return it to pending with the new due time and send nothing.
- If it remains due, continue only after all delivery checks pass.
- A sent reminder is not recreated or resent after a move.

### Event and Occurrence State

On event cancellation or occurrence cancellation:

- Preserve positions and commitments unchanged.
- Atomically skip pending or claimed staffing deliveries with `event_inactive` or `occurrence_cancelled`.
- Exclude the occurrence from member upcoming commitments and signup actions.
- The existing attendee cancellation email must not be sent merely because a person is a volunteer. Phase 5 does not add a volunteer cancellation email.

On occurrence restoration:

- Preserve withdrawn and administratively removed commitments as inactive history.
- Still-committed rows become visible in upcoming commitments if the occurrence remains future and the position remains active.
- Reset a staffing delivery skipped only for `occurrence_cancelled` to pending when the occurrence remains future, the commitment is still active, and the position remains active; recalculate its due time.
- Never reset a sent delivery or one skipped for inactive commitment/account/ownership.

Archiving remains permitted only under the existing event rules. Deleting a draft event may cascade positions, commitments, and deliveries only when existing Phase 4 deletion policy permits the event deletion.

## Staffing Reminder Dispatch

Add configuration under an event-specific config file, for example `config/events.php`:

```php
'volunteer_reminder_offset_minutes' => 1440,
```

The value must be a positive integer and is not accepted from an HTTP request.

Add `VolunteerReminderDispatcher`, `DispatchVolunteerReminders`, `SendVolunteerReminderDelivery`, and `VolunteerStaffingReminder` using the repository's existing command/job/notification conventions.

The scheduled command runs every minute with `withoutOverlapping()` and processes bounded batches. It:

1. Finds active future commitments on active positions for published events and scheduled occurrences.
2. Creates the missing unique delivery with a current user-email snapshot and calculated due time.
3. Refreshes recipient/due data only while pending.
4. Marks commitments without a valid linked approved account/email as skipped with `account_unavailable` when due; it does not fall back to public Person contact fields.
5. Atomically changes due pending deliveries to claimed.
6. Dispatches a job containing only the delivery identifier.

The job reloads and checks delivery, commitment, position, occurrence, event, lodge, user, and person. It verifies all duplicated identifiers, current committed status, active position and exact position scope, event publication, lodge state, scheduled future occurrence, current user-person link, eligibility, and recipient address before sending.

Immediately before invoking the mail transport, the job atomically sets `attempted_at` only when status is claimed and `attempted_at` is null. A retry or duplicate job that observes a non-null `attempted_at` sends nothing. This is the at-most-once boundary; an operator-triggered retry of a known failure deliberately clears `attempted_at` and is the only path allowed to make another attempt.

The email includes:

- Lodge name.
- Event/occurrence title.
- Effective date, time, and event time zone.
- Effective location.
- Position name and description/instructions.
- A link to the authenticated event detail or dashboard.
- Clear wording that this is a volunteer staffing reminder, not an attendance reservation confirmation or ordinary event reminder.

Delivery behavior:

- Sending is at-most-once once a claimed job enters the notification call.
- A job returns without work unless status is exactly claimed and `attempted_at` is null.
- Failed sending writes `failed`, `failed_at`, and a bounded error message; the job does not throw for automatic mail retry.
- Explicit manager retry changes failed to pending, clears error/timestamps, recalculates due data, and lets the dispatcher claim it again. Retry is rejected when the commitment or occurrence is no longer eligible.
- A stale claimed delivery with null `attempted_at` may safely return to pending. A stale claimed delivery with non-null `attempted_at` has an unknown transport outcome and must be surfaced for operator review; it is never automatically retried.
- A sent delivery is immutable through ordinary UI.

## Routes and UI

Use current repository naming and placement. Do not reorganize Phase 4 controllers or Vue pages as part of this work.

### Management Routes

Add authenticated, verified, approved, admin-2FA routes under the existing lodge/event hierarchy:

```text
POST   lodges/{lodge}/events/{event}/volunteer-positions
PUT    lodges/{lodge}/events/{event}/volunteer-positions/{position}
PATCH  lodges/{lodge}/events/{event}/volunteer-positions/{position}/deactivate
DELETE lodges/{lodge}/events/{event}/volunteer-positions/{position}

GET    lodges/{lodge}/events/{event}/occurrences/{occurrence}/volunteers
POST   lodges/{lodge}/events/{event}/occurrences/{occurrence}/volunteers
PATCH  lodges/{lodge}/events/{event}/occurrences/{occurrence}/volunteers/{commitment}/remove
POST   lodges/{lodge}/events/{event}/occurrences/{occurrence}/volunteer-reminders/{delivery}/retry
```

Route names use `lodges.events.volunteer-positions.*`, `lodges.events.occurrences.volunteers.*`, and `lodges.events.occurrences.volunteer-reminders.retry`.

The manager creation request submits a target `person_id`; the server resolves the linked user and rejects unavailable or ineligible people. The selectable person query starts from active memberships in the explicit event lodge and returns only the minimal display name and identifier needed for selection.

### Member Routes

Add authenticated routes that still include the explicit public lodge and occurrence context:

```text
POST  l/{lodge:slug}/events/{occurrence}/volunteer-commitments
PATCH l/{lodge:slug}/events/{occurrence}/volunteer-commitments/{commitment}/withdraw
```

These routes require `auth`, `verified`, and `approved`, but not admin 2FA. They use public-site lodge resolution and return 404 when the event is not published/visible. They must not accept event, lodge, person, user, status, or capacity fields in the request body.

### Event Editor

Extend `resources/js/pages/events/Edit.vue` with a Volunteer Staffing panel:

- Explain the distinction from reservations and reminders.
- List series positions and their current active state.
- Add a series position.
- Add an occurrence position by selecting one materialized occurrence.
- Edit name, plain-text description, needed count, sort order, and active state.
- Show scope clearly and keep it immutable after creation.
- Link to upcoming occurrence rosters.
- Confirm deactivation when future active commitments exist.
- Never display volunteer contacts in the general editor payload.

Keep forms keyboard accessible, show server validation next to fields, and support current light/dark and mobile/desktop layouts.

### Occurrence Roster

Add `resources/js/pages/events/Volunteers.vue`:

- Event and effective occurrence details.
- Every position applicable to the occurrence, including inactive positions with history, with needed/filled/remaining counts and active state.
- Active commitments first, then withdrawn and administratively removed history.
- Volunteer display name and authorized contact email/phone sourced through the current Person access rules.
- Managers holding only `events.manage` see roster names and status but null email/phone fields; do not query around `PersonAccess` to populate them.
- Target-member search and manager add action.
- Administrative removal with confirmation.
- Reminder status, failure summary, and retry action where applicable.
- Explicit links to the separate reservation roster; do not combine rows or totals.

Do not expose lodge-private membership notes, family information, birth dates, profile-photo originals, unrelated memberships, or full Person serialization.

### Member Event Detail

Extend the public event detail payload only when the request has an eligible authenticated viewer. Include:

- Effective position identifier, name, description, needed, filled, and remaining.
- Scope (`series` or `occurrence`) for explanatory display.
- The current person's active commitment identifier/status for each position.
- `can_commit` and `can_withdraw` booleans calculated by backend rules.

Anonymous and ineligible responses omit the staffing key entirely. The Vue component must not infer eligibility from event visibility or remaining count.

Show independent actions:

- Reserve attendance, when available.
- Subscribe to ordinary reminders, when available.
- Volunteer for a named position, when eligible.

Each action has separate explanatory text and submits a separate request. Volunteering must not auto-check, auto-submit, or hide the other actions.

### Dashboard Integration

Replace the relevant dashboard placeholder with an Upcoming Volunteer Commitments card/query:

- Query by current `user_id` and `person_id`, committed status, active position, scheduled future occurrence, and published event.
- Show position, event, owning lodge, effective date/time/time zone, effective location, and status.
- Provide event-detail navigation and withdrawal while allowed.
- Sort by occurrence start, then position sort order/name.
- Include commitments across all lodges in which the person is eligible; do not depend on `current_lodge_id` for the personal list.
- Do not implement Phase 6 profile editing, member directory, privacy preferences, or a broader dashboard redesign.

## Validation, Privacy, and Security

- Position descriptions are plain text. Vue escapes them; do not use `v-html`.
- Never accept lodge/event/occurrence/position ownership, target user, actor, status, counts, filled counts, reminder status, or delivery due time from hidden client fields.
- Manager person search begins with active memberships in the owning lodge; it cannot globally search people by exact name/email fallback.
- Contact information on rosters follows existing authorized Person access. No contact detail is present in position/count payloads.
- The member endpoint never reveals other volunteer identities, emails, phones, user IDs, or person IDs.
- Filled counts use aggregate queries and must not serialize commitment collections accidentally.
- Use database transactions and locks for capacity and state transitions. Client-side disabled buttons are convenience only.
- Normalize reminder recipient email using the same application convention as Phase 4.
- Queue payloads contain only delivery identifiers, not email addresses, names, or serialized models.
- Audit payloads identify records and state changes but do not copy unnecessary contact data.
- Logs and validation errors must not expose another lodge's position, commitment, person, or delivery details.
- Rate-limit member commitment and withdrawal routes consistently with other public-site interactions, while authentication and the database remain the authority.

## Model Relationships and Merge/Deletion Effects

Add relationships without broad serialization:

- `Event::volunteerPositions()` and `Event::volunteerCommitments()`.
- `EventOccurrence::volunteerPositions()`, `volunteerCommitments()`, and `volunteerReminderDeliveries()`.
- `EventVolunteerPosition::event()`, `occurrence()`, `commitments()`, and `lodge()`.
- `EventVolunteerCommitment::position()`, `occurrence()`, `event()`, `lodge()`, `user()`, `person()`, and `reminderDelivery()`.
- Personal relationships on `User` and `Person` only where needed for bounded dashboard and merge operations.

Extend `PersonMergeService` so compatible source-person commitments move to the survivor. If both source and survivor have active commitments to the same position/occurrence, abort the merge and report the conflict; never silently remove a commitment. Historical inactive duplicates may move. Revalidate lodge/event/occurrence/position ownership inside the merge transaction.

Account unlinking or deletion does not delete commitment history. It makes future self-service and unsent delivery unavailable until a valid link exists; managers can remove the commitment. Do not silently reassign commitments to another account by matching email.

## Database and Implementation Order

Use one new timestamped Phase 5 domain migration after `2026_08_21_000610_add_default_event_reminder_rules.php`, but give it a domain name such as `create_event_volunteer_domain.php` without a phase label.

Migration order inside the file:

1. `event_volunteer_positions`.
2. `event_volunteer_commitments`.
3. `event_volunteer_reminder_deliveries`.
4. Composite foreign keys, checks, partial unique index, and query indexes.

Then implement in this order:

1. Enums, models, factories, and relationships.
2. Eligibility and person-merge integration.
3. Position writes and management UI.
4. Commitment service and member endpoints.
5. Manager roster and administrative actions.
6. Event lifecycle and recurrence reconciliation hooks.
7. Dashboard integration.
8. Reminder dispatcher, command, job, notification, retry, and scheduler.
9. Cross-cutting browser, privacy, and tenancy hardening.

The migration must roll back in reverse dependency order. Factories must produce ownership-consistent rows by default and expose explicit states for series/occurrence scope and each commitment/delivery status.

## Automated Tests

Tests must use fixed clocks where time affects signup, withdrawal, due time, cancellation, or upcoming lists. Every feature group creates at least Lodge A and Lodge B and attempts direct identifier substitution.

### Unit and Domain Tests

- Volunteer eligibility for approved/unapproved, verified/unverified, linked/unlinked, active/ended membership, own/other lodge, merged person, each qualification rank, and Past Master.
- Available-position and management-roster resolution for series only, occurrence only, combined positions, inactive positions with history, ordering, and duplicate names.
- Filled and remaining count calculation using only committed rows.
- Multiple different positions for one person on one occurrence are accepted.
- Duplicate active commitment to the same position/occurrence/person is rejected.
- Recommit after withdrawal/removal creates a new row.
- Position scope matching and immutable scope.
- Status transition matrix and self-withdraw start-time boundary.
- Volunteer reminder due calculation, including a commitment inside the 24-hour window.

### Database and Concurrency Tests

- Composite event/lodge/occurrence ownership constraints for all three tables.
- Positive needed-count check.
- Partial unique active commitment index on PostgreSQL.
- Two independent database connections race for the last opening; exactly one commitment succeeds.
- Two independent requests race to create the same person's commitment; exactly one active row succeeds.
- Needed-count reduction locks and rejects below active count, including the busiest occurrence for a series position.
- Transaction rollback leaves no partial commitment, audit, or delivery state.

Do not mark concurrency tests skipped merely because SQLite lacks the required behavior. Run them against the configured PostgreSQL test service.

### Laravel Feature Tests

- Position create/update/deactivate/delete rules for draft and published events.
- Series and occurrence position visibility on the correct occurrences.
- Member position payload omitted for anonymous, unlinked, ineligible, cross-lodge, cancelled, past, draft, and archived cases.
- Self commitment creation and withdrawal.
- Multiple different positions per person without an artificial limit.
- No reservation, reminder subscription, or ordinary reminder delivery created by volunteer actions.
- A reservation or ordinary reminder subscription does not create a volunteer commitment.
- Manager add/remove with target eligibility and minimal member search projection.
- Manager cannot add a guest, unlinked person, ended member, other-lodge member, or under-qualified member.
- Position full capacity and capacity reopening after withdrawal/removal.
- Roster authorization and contact privacy.
- Member payload contains counts and own state but no other-volunteer identity.
- Dashboard includes all eligible lodge commitments independent of active lodge and excludes cancelled/past/inactive commitments.
- Event/occurrence cancellation and position deactivation suppress deliveries without mutating commitment status.
- Occurrence restoration re-exposes still-active commitments and resets only cancellation-skipped unsent delivery.
- Schedule reconciliation preserves occurrences with occurrence positions, any commitments, or staffing deliveries.
- Occurrence move recalculates pending/claimed due time and never resends sent delivery.
- Person merge moves compatible commitments and rolls back on active duplicate conflict.
- Account unlink/delete preserves history and suppresses unsafe mailing.
- Audit events for position create/update/deactivate/delete, commitment create/withdraw/remove, and manager-created target.

For every mutation, substitute a Lodge B event, occurrence, position, commitment, target person, and delivery while authenticated only for Lodge A. Also test mismatched but individually valid identifiers within Lodge A.

### Reminder Tests

- Repeated dispatcher runs create one delivery and queue one claimed job.
- Concurrent dispatcher claims queue one job.
- Delivery due 24 hours before effective occurrence time.
- Late commitment becomes immediately due only while occurrence is future.
- Withdrawn/removed commitment is skipped before send.
- Draft/cancelled/archived event, cancelled/past occurrence, inactive or wrong-scope position, invalid ownership, changed account link, ended membership, and unavailable account are skipped.
- Queue job reloads and validates every owner; a forged cross-lodge delivery payload sends nothing.
- Queue retry after sent/failed/skipped does not duplicate mail.
- Failure records bounded error and explicit authorized retry works only while still eligible.
- Notification content uses effective overrides, event time zone, position data, and volunteer-specific wording.
- A staffing reminder does not create or require an ordinary reminder subscription.

### Playwright Critical Path

Extend the event browser suite to:

1. Sign in as a Lodge A event manager.
2. Add Setup and Cleanup as series positions with different needed counts.
3. Add Registration Table only to one occurrence.
4. Publish the event and sign in as an eligible Lodge A member.
5. Commit the same person to Setup and Cleanup for that occurrence.
6. Confirm no reservation or reminder-subscription success state appears.
7. Attempt a duplicate Setup commitment and see a stable validation message.
8. Confirm filled/remaining counts and view both commitments on the dashboard.
9. View the private roster as manager and administratively add another eligible member.
10. Withdraw one commitment as its owner and verify capacity reopens.
11. Switch to Lodge B and confirm Lodge A routes and identifiers are inaccessible.
12. Verify anonymous event HTML/Inertia props contain no staffing or volunteer data.
13. Exercise mobile and desktop layouts, keyboard focus, light mode, and dark mode.

Mail delivery itself may be asserted in Laravel tests and spot-checked in Mailpit; the browser suite need not wait 24 hours.

### Required Gates

At each work-package gate run the focused tests plus relevant static checks. At final integration run:

```text
php artisan test
vendor/bin/pint --test
npm run typecheck
npm run typecheck:e2e
npm run lint
npm run build
npm run test:e2e
composer validate --strict
composer audit
npm audit --audit-level=low
git diff --check
```

Also run `php artisan route:list`, `php artisan schedule:list`, and migration refresh against the PostgreSQL test environment. Resolve every warning or request explicit approval before suppressing it.

## Manual Acceptance

A tester using at least three lodges and Mailpit can:

1. Create a recurring Lodge A event and add a series Setup position needing two people.
2. Add an occurrence-only Driver position needing one person.
3. Publish the event and confirm anonymous visitors receive no volunteer data.
4. Sign in as an active Lodge A member and see both available positions.
5. Commit to both Setup and Driver, proving multiple different positions are allowed.
6. Confirm no attendance reservation or ordinary reminder subscription was created.
7. Attempt to commit twice to Setup and receive a clear duplicate response.
8. Fill the remaining Setup opening with another member and confirm a third signup cannot overfill it.
9. View accurate filled/remaining counts without seeing identities as an ordinary member.
10. View names and authorized contact details on the manager roster.
11. Administratively add and remove an eligible linked Lodge A member.
12. Fail to add a Lodge B-only member, unlinked person, guest, or under-qualified member.
13. See all personal upcoming commitments on the dashboard while a different lodge is active.
14. Withdraw before the occurrence and see the opening become available.
15. Cancel and restore the occurrence without losing still-active commitments.
16. Move the occurrence and verify the pending reminder due time follows the effective date.
17. Receive one staffing reminder identifying the position and occurrence.
18. Run dispatch repeatedly and confirm no duplicate email.
19. Withdraw/remove a commitment before dispatch and confirm no staffing reminder is sent.
20. Attempt direct Lodge B position, commitment, roster, target-person, and delivery identifiers and receive no data or mutation.
21. Verify the event detail, roster, editor, and dashboard at mobile and desktop widths in light and dark modes.

## Implementation Work Packages

Complete and review one package before beginning a dependent package. Each package is intentionally bounded for a GPT-5.6-Terra handoff.

### Locked Implementation Map

Use these names unless an existing repository convention makes a small namespace adjustment necessary:

- Enums: `VolunteerCommitmentStatus`, `VolunteerReminderDeliveryStatus`.
- Models: `EventVolunteerPosition`, `EventVolunteerCommitment`, `EventVolunteerReminderDelivery`.
- Domain services: `VolunteerEligibility`, `VolunteerCommitmentService`, `VolunteerReminderDispatcher` under `app/Domain/Events`.
- HTTP: `EventVolunteerPositionController`, `EventVolunteerController`, `PublicEventVolunteerController`, and `EventVolunteerReminderDeliveryController` under the repository's existing flat controller namespace.
- Queue/notification: `DispatchVolunteerReminders`, `SendVolunteerReminderDelivery`, and `VolunteerStaffingReminder`.
- Vue: extend `events/Edit.vue`, add `events/Volunteers.vue`, extend `public/EventDetail.vue`, and replace only the relevant `Dashboard.vue` placeholder.
- Focused backend tests belong in a new `tests/Feature/EventVolunteerTest.php` plus focused unit tests where useful. Browser coverage extends the event suite or adds `tests/Browser/event-volunteers.spec.ts` without Phase labels.

All writes go through requests/services, not model mutation in Vue-shaped controllers. Any departure from the schema, eligibility, capacity, lifecycle, delivery, or privacy contracts requires a documentation amendment before implementation.

### P5-01 Schema, Enums, Models, and Factories — Terra

Prerequisite: verified Phase 4 baseline.

Deliver:

- Migration, PostgreSQL constraints/indexes, enums, models, casts, factories, and relationships for all three volunteer tables.
- No routes or UI.
- Ownership-consistent factory states and migration rollback.

Tests and gate:

- Migration/constraint tests, relationship tests, enum casts, factory smoke tests, partial unique behavior, rollback, full Laravel suite, and Pint.

### P5-02 Eligibility, Merge, and Read Contracts — Terra

Prerequisite: P5-01.

Deliver:

- `VolunteerEligibility` and any safe extraction of shared qualification logic from `EventEligibility`.
- Effective-position/count query contract with no contact data.
- Person merge conflict/move behavior and account-link history behavior.

Tests and gate:

- Full eligibility matrix, available positions, management roster scope, count privacy, merge success/conflict rollback, account unlink behavior, tenant attacks, and existing event eligibility regression tests.

### P5-03 Position Management — Terra

Prerequisites: P5-01 and P5-02.

Deliver:

- Position form requests, controller, routes, audit events, event-editor Staffing panel, immutable scope, safe delete, and deactivation.
- Needed-count reduction validation for occurrence and series scope.

Tests and gate:

- CRUD/state rules, ownership, counts, busiest-occurrence reduction, draft deletion, history preservation, validation, audits, responsive UI, typecheck, and lint.

### P5-04 Commitment Service and Member Flow — Terra

Prerequisites: P5-02 and P5-03.

Deliver:

- Transactional `VolunteerCommitmentService`, self-service commit/withdraw routes, eligible event-detail projection, and separate volunteer actions.
- Locking, capacity, duplicate handling, multiple different positions, immutable historical rows, and audits.

Tests and gate:

- Eligibility, last-opening race, duplicate race, multiple-position allowance, recommit, start boundary, anonymous/ineligible omission, independence from reservations/subscriptions, tenant attacks, typecheck, and lint.

### P5-05 Manager Roster and Administrative Actions — Terra

Prerequisites: P5-03 and P5-04.

Deliver:

- Authorized roster page, minimal active-member search, manager-created commitment through the shared service, administrative removal, counts, and contact projection.
- Clear navigation between volunteer and reservation rosters.

Tests and gate:

- Permission matrix, target eligibility, contact privacy, person-search scope, add/remove history, capacity, audits, nested identifier attacks, responsive UI, typecheck, and lint.

### P5-06 Event Lifecycle and Recurrence Integration — Terra

Prerequisites: P5-01 and P5-04.

Deliver:

- Extend protected occurrence detection/deletion exclusions.
- Integrate event/occurrence cancel, restore, move, archive/delete behavior, relationships, and transactional delivery hooks expected by P5-08.
- Preserve all commitment history without creating volunteer cancellation email.

Tests and gate:

- Reconciliation preservation, protected confirmation count, cancellation/restoration, schedule moves, status preservation, ownership, rollback, and all Phase 4 recurrence/cancellation regressions.

### P5-07 Dashboard Integration — Terra

Prerequisites: P5-04 and P5-06.

Deliver:

- Upcoming personal commitment query and Dashboard card across eligible lodges.
- Effective event data, ordering, navigation, and withdrawal action.
- No Phase 6 directory/profile scope.

Tests and gate:

- Multi-lodge personal list, active-lodge independence, cancelled/past/inactive exclusion, account/person ownership, responsive accessibility, typecheck, and lint.

### P5-08 Staffing Reminder Delivery — Terra

Prerequisites: P5-04 and P5-06.

Deliver:

- Configured offset, dispatcher, command, scheduler entry, delivery lifecycle, queue job, notification, manager retry, occurrence-move behavior, cancellation/restoration behavior, and bounded operational processing.
- Full ownership and eligibility reload immediately before sending.

Tests and gate:

- Due calculation, late signup, repeated/concurrent dispatch, at-most-once queue behavior, failure/retry, move/cancel/restore races, inactive commitment/account/eligibility suppression, forged ownership, notification content, scheduler listing, and Mailpit spot check.

### P5-09 Integration and Hardening — Terra

Prerequisite: all earlier packages.

Deliver:

- Complete Playwright path, direct-identifier attack matrix, privacy inspection of anonymous and member Inertia props/HTML, mobile/desktop/light/dark polish, documentation reconciliation, and operational notes.
- Update `architecture.md`, `authorization.md`, `domain-model.md`, `tenancy-rules.md`, `testing-strategy.md`, and README to describe implemented behavior and link this plan.
- Resolve all test, type, lint, build, IDE, and audit findings. Request approval before suppressing any warning.

Tests and gate:

- Every required final gate and manual acceptance item.

## Dependency and Parallelization Map

- P5-01 blocks all other work.
- After P5-01, P5-02 begins; it establishes the eligibility/read contract required by UI and services.
- P5-03 follows P5-02.
- P5-04 follows P5-02 and P5-03.
- P5-05 follows P5-04 and may proceed in parallel with P5-06 once P5-04 is stable.
- P5-07 waits for member commitment and lifecycle behavior from P5-04/P5-06.
- P5-08 waits for P5-04 and the lifecycle hooks from P5-06; it may proceed in parallel with P5-07.
- P5-09 is the final integration gate.

For a single Terra agent, use the numbered order. If work is delegated to multiple agents later, do not parallelize migrations, shared enums/models, `EventController`, `EventOccurrenceController`, `EventScheduleReconciler`, or route edits without explicit file ownership and an integration owner.

## Agent Handoff Contract

Give Terra one work package at a time. Every prompt must:

- Name the package and paste or cite its exact deliverables and gate from this document.
- Require reading this entire document, `docs/phase-04.md`, `docs/architecture.md`, `docs/authorization.md`, `docs/tenancy-rules.md`, `docs/domain-model.md`, and `docs/testing-strategy.md` first.
- Identify completed prerequisite commits or verified contracts.
- State the files/directories the package may modify.
- Require focused tests plus the existing full-suite regression command appropriate to the package.
- Require preserving unrelated dirty-worktree changes. The repository currently may contain user changes, including event UI work; Terra must inspect before editing and never reset or overwrite them.
- Prohibit Phase labels in implementation filenames, symbols, routes, database objects, and comments.
- Prohibit using reservations, reservation fields, reminder subscriptions, ordinary reminder deliveries, or generic event-response JSON for volunteer state.
- Require complete lodge/event/occurrence/position/commitment ownership validation and adversarial Lodge B tests.
- Require reporting a needed contract change instead of silently changing schema, eligibility, capacity, cross-lodge behavior, lifecycle, reminder semantics, or privacy.
- Require resolving warnings or asking for approval before suppressing them.
- Stop after the package gate and report changed files, migrations, tests run, results, remaining risks, and exact prerequisites for the next package.

Architectural decisions in this plan are locked. Terra should make ordinary implementation choices inside them without asking for re-approval. Repository evidence that contradicts a locked decision is a stop-and-report condition.

## Definition of Done

Phase 5 is complete only when:

- All P5 work packages and final gates pass against PostgreSQL.
- A person can hold multiple different positions for one occurrence without a programmatic cross-position limit.
- Duplicate same-position commitments and concurrent overfill are prevented by service logic and database invariants.
- Volunteer records remain independent of reservations and ordinary reminder subscriptions in schema, UI, tests, and language.
- Event reconciliation, cancellation, restoration, and movement preserve history and update/suppress reminders correctly.
- Reminder dispatch is idempotent, queue-safe, bounded, and documented.
- Anonymous/member/manager projections have been inspected for contact and tenant leakage.
- Lodge A/Lodge B direct-identifier attacks cover every new identifier and endpoint.
- Manual acceptance passes at mobile and desktop widths in light and dark modes.
- Architecture, authorization, domain, tenancy, testing, README, scheduler, and operational documentation match the implementation.
- No implementation artifact uses a Phase label.

## Non-Goals

- Paid or compensated staffing.
- Employment or shift scheduling.
- General volunteer opportunities unrelated to event occurrences.
- Cross-lodge or regional volunteer discovery/signup.
- Guest or unauthenticated volunteers.
- Managers adding people without linked approved accounts.
- Waitlists or automatic promotion when a volunteer withdraws.
- Enforcing a maximum number of positions per person.
- Detecting overlapping positions, travel time, availability, or whether a volunteer has taken on too much.
- Automated substitute matching or broadcast requests for replacements.
- Volunteer-hour tracking, check-in, attendance, completion, certification, or performance notes.
- Public volunteer names, rosters, or contact lists.
- SMS, push, or chat staffing reminders.
- Lodge-configurable reminder offsets or multiple staffing reminder rules in the initial release.
- Volunteer cancellation email beyond suppressing unsent staffing reminders.
- Automatically creating, cancelling, or inferring an attendance reservation or ordinary reminder subscription.
