# Phase 15 — Newburgh Migration and Production Cutover

## Outcome

Newburgh Lodge No. 174 is transformed into the new domain model, validated, and cut over without loss of required behavior or historical data.

## Scope

Migrate applicable lodge identity/branding, public pages/sections/navigation/media, People/memberships/family/officers/history, events/recurrence/exceptions/reservations/reminders/volunteer data, newsletters, galleries, communications, Ritualist data, accounts where safe, and optional-module data where present:

- module availability and lodge enabled preferences;
- Scholarship cycles, applications, reviews, assignments, and private documents;
- Store products, photos, variants, inventory, orders, order lines, customer/fulfillment snapshots, and payment/fulfillment state;
- Fundraising campaigns, manual progress/history, media, and Store associations; and
- Games banks/questions and lodge session history.

When a future online-payment capability exists in the source, migrate only the lodge-specific provider connection configuration and safe reconciliation metadata needed to preserve the lodge-owned business history. Never migrate raw payment credentials. Validate that imported provider account/transaction references remain attached to the correct lodge and never create a shared or cross-lodge merchant fallback.

The legacy Store and fundraiser are primitive inputs, not normative schema. Document transformations into the new aggregates and preserve business meaning/history without copying obsolete implementation quirks. Source data is never mutated.

## Cutover and Validation

Migration is repeatable in staging, uses explicit ownership mappings, records cleanup/transformation decisions, validates counts and invariants, protects private media, and supports a final maintenance/read-only synchronization window and rollback plan. `newburghlodge174.org` resolves to the Newburgh tenant and important legacy URLs redirect or remain functional.

Validate module state separately from data presence: imported records do not imply availability or lodge enablement. Verify every imported optional-module record remains inaccessible when its effective state is false and returns under normal permissions/publication rules when re-enabled.

Automated validation covers counts, required mappings, orphans, duplicate people, memberships, recurrence comparison, reservations/reminders/volunteers, media, private Scholarship ownership, Store totals/inventory/order history, Fundraiser progress/associations, Games ownership, account links, module states, direct identifiers, and cross-lodge isolation. If provider configuration exists, include per-lodge connection/account identity, safe transaction correlation, encrypted-secret migration policy, and no cross-lodge merchant-account mapping.

## Manual Acceptance

Verify representative current and historical records in every migrated domain, public URLs/branding, private files, module controls, disabled-state preservation, Store order/payment/fulfillment history, Fundraiser progress, Games data, and cutover/rollback runbooks. Obtain domain-owner sign-off for deliberate transformations.

## Non-Goals

- Migrating other lodges.
- Reproducing obsolete implementation quirks as new requirements.
- Mutating the legacy source during import.
- Introducing new optional-module functionality during cutover.
