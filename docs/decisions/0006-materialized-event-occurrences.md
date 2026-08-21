# ADR 0006: Materialize Event Occurrences from RRULE Series

- Status: Accepted for Phase 4 planning
- Date: 2026-08-21

## Context

Recurring events need occurrence-specific cancellation, movement, location and description overrides, attendance reservations, reminder subscriptions and deliveries, public links, and calendar output. Expanding an RRULE only at request time makes individual occurrences difficult to reference with database constraints. Pre-creating an unlimited recurring series is impossible, while using a mutable effective start as identity would detach reservations and occurrence-scoped subscriptions whenever an occurrence moves.

The platform also needs deterministic local-time recurrence across daylight-saving transitions without implementing RFC 5545 recurrence mathematics from scratch.

## Decision

Store the event series as a lodge-owned event with a canonical RRULE and IANA time zone. Use the MIT-licensed [rlanvin/php-rrule](https://github.com/rlanvin/php-rrule) package behind an application-owned RecurrenceExpander interface.

Materialize bounded event_occurrences rows. Each occurrence stores:

- The event and matching lodge.
- A recurrence key derived from the original scheduled local start.
- Original scheduled UTC start.
- Effective UTC start and end.
- Cancellation and override data.

All one-time and recurring reservations reference a materialized occurrence. Occurrence-scoped reminder subscriptions, reminder deliveries, and occurrence URLs use the occurrence database identity after verifying the event and lodge relationship; a series-scoped subscription instead references the event and expands through its materialized occurrences at dispatch time.

Generate occurrences for a rolling window of three months past through eighteen months future. A scheduled command extends the window. Series schedule reconciliation preserves past rows and future rows with reservations, subscriptions, deliveries, or explicit exceptions, while untouched future rows may be regenerated.

The recurrence adapter treats local wall time as authoritative. It rejects nonexistent DST local times and resolves ambiguous local times to the earlier offset consistently.

## Consequences

- Occurrences have stable relational identities for reservations, occurrence-scoped subscriptions, reminder deliveries, overrides, and routes.
- Moving an occurrence does not change its recurrence identity.
- Upcoming-event queries and reminders do not repeatedly expand every series.
- Open-ended recurrence remains bounded operationally.
- Series schedule edits require explicit transactional reconciliation.
- The application owns the recurrence contract and may replace the vendor package later.
- Occurrence-horizon maintenance becomes an operational scheduled task.
- Tests must cover generation, reconciliation, DST, and tenant ownership.

## Alternatives Considered

### Virtual occurrences only

Storing only event plus RRULE reduces rows but leaves reservations, occurrence-scoped subscriptions, and exceptions attached to application-generated string identifiers without strong foreign keys. Reminder and public queries also repeat expansion work.

### Materialize an entire series

This is unsuitable for open-ended recurrence and makes large schedule changes expensive.

### Custom recurrence implementation

Implementing RFC 5545 correctly, especially around monthly rules and DST, adds risk without product value. An open-source library behind a local interface is safer and remains replaceable.
