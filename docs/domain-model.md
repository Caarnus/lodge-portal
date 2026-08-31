# Domain Model

## Ownership Categories

Every persistent domain model must be classified as platform-owned, lodge-owned, person-owned, membership-owned, or shared/reference data.

## Phase 1 Core

- `User`: a global authenticated account. It may be linked to one person.
- `Person`: a global human identity. Phase 1 stores only fields needed for safe account matching; the full profile arrives in Phase 3.
- `Lodge`: a tenant with identity, branding, status, slug, and settings.
- `Membership`: the relationship between a person and lodge. Phase 1 establishes only the minimum authorization-compatible structure.
- Lodge role assignment: grants a user or linked person a platform-defined permission set within one lodge.
- Registration: captures a selected home lodge for approval routing. It does not grant membership or authorization.
- Feature definition/assignment: the Phase 1 platform-owned foundation that Phase 10 retrofits into the optional-module contract below. It is not authorization or CMS publication state.
- Audit event: immutable record of a sensitive action and relevant before/after state.

Lodge slugs are globally unique but may be changed by an authorized administrator. Person email addresses, when present directly on people, are globally unique.

## Public Website

- `WebsitePage`: a stable lodge-owned page identity.
- `WebsitePageVersion`: lodge-owned draft, published, or archived page metadata, slug, and navigation placement. A public request reads only a published version.
- `WebsiteSection`: an ordered, typed, lodge-owned content section belonging to one page version. Its configuration is validated against the platform contract for that section type.
- `MediaAsset`: a lodge-owned or intentionally platform-shared file with explicit visibility and storage metadata.
- Website template: a platform-owned, versioned content recipe that creates ordinary lodge-owned pages and sections.

Published content is immutable through ordinary editing. Editing occurs in a draft, and transactional publication replaces the current published version while retaining archived history. Page, version, section, navigation-parent, and media relationships must all agree on lodge ownership.

The lodge remains the owner of public identity fields including seal, logo, tag line, colors, public contact details, and meeting information.

## People and Membership Administration

- `Person`: one global, person-owned human identity with structured legal/preferred names, contact fields, optional demographic/deceased fields, private profile-photo metadata, and at most one linked user. A person may exist without an account or membership. The legacy full-name column is a temporary migration fallback only.
- `Membership`: the lodge-owned relationship between one person and one lodge. It contains membership type/status/degree references, primary and local lodge numbers, milestone/end dates, and lodge-private notes.
- Membership reference values: platform-owned records with stable keys, labels, ordering, active state, and database-controlled defaults where applicable. Historical membership rows may retain inactive references.
- `PersonRelationship`: a connection between two global people with an owning lodge for provenance. It is visible to lodges with an active member at either endpoint and editable by a lodge when a qualifying endpoint membership names that lodge number as primary. It grants no membership, account authorization, or directory visibility.
- `OfficerAssignment`: a lodge-owned historical term with required start/end dates and an optional label, connecting a membership to a platform-owned officer position. The membership, assignment, and lodge must agree.
- Account link: the unique optional `users.person_id` association. Revoking a lodge role does not delete this global link.

Shared person identity/contact data is updated globally only through an authorized active-membership relationship. Memberships, notes, relationship provenance, current officer assignments, Past Master years, and role assignments retain a lodge owner. Family relationship visibility/editing follows its explicit active-member and primary-lodge collaboration rules instead of unrestricted sharing. The global people table is never an unscoped directory.

One person may have memberships in multiple lodges, but at most one membership per lodge. Ending one membership preserves the person, other memberships, account link, relationships, and history. Primary lodge is a membership string value containing a lodge number, not a required foreign key to a hosted lodge.

Manual person merge selects one survivor, moves compatible dependent records transactionally, rejects unresolved membership/account conflicts, retires the source, and records a platform-admin audit event. Email is a hard uniqueness signal; a matching name is review information only.

## Events

- Event category: platform-owned reference data enabled explicitly by each lodge.
- Event: a lodge-owned one-time or recurring series containing content, location, time zone, RRULE, visibility, qualification, reservation configuration, reminder configuration, and publication state.
- Event occurrence: a bounded, materialized lodge-owned instance with a stable original recurrence identity, effective times, cancellation state, and optional content/location overrides.
- Reservation field: an event-owned custom attendance-response definition with an immutable key.
- Event reservation: an occurrence-owned attendance commitment with optional user/person links, attendee snapshot, party size, validated responses, status, and a hashed cancellation token. It consumes capacity when active.
- Reminder rule: an event-owned offset definition.
- Reminder subscription: an event-owned notification request scoped to one occurrence or a recurring series, with optional user/person links, recipient snapshot, status, and a hashed unsubscribe token. It does not imply attendance or consume capacity.
- Reminder delivery: an idempotency and status record connecting one subscription, rule, occurrence, and normalized recipient.
- Volunteer position: an event-owned named staffing need, scoped to every occurrence or one occurrence, with a hard needed count and active state.
- Volunteer commitment: immutable historical agreement by one linked person to fill one position for one occurrence. Only one active commitment exists for a person, position, and occurrence.
- Volunteer staffing reminder delivery: one at-most-once delivery per commitment, independent of ordinary reminder subscriptions and deliveries.

Every event child repeats or derives lodge ownership and is validated against its parent. A recurrence key identifies the original scheduled local occurrence and does not change when the effective time moves. Protected-event eligibility is derived at request time from active membership and qualification rather than copied into a reservation or subscription.

## Lodge Groups and Regional Discovery

- `LodgeGroupType`: platform-owned reference data describing group presentation such as region, district, county, informal, or other. Type never changes authorization.
- `LodgeGroup`: platform-owned organizational record with name, slug, description, active/archive lifecycle, type, and optional public landing page.
- Lodge-group membership: many-to-many organizational relationship between active or historical lodges and groups. It grants no permission, visibility, consent, reservation, reminder, or administrative authority.

One lodge may belong to several groups or none. Group-filtered discovery first builds each domain's authorized result set, then narrows by active lodge membership in selected group. Disabling lodge or group removes it from active discovery without destroying historical group membership.

Lodge identity also includes optional public `meeting_schedule` free text, separate from meeting location. Platform-wide lodge discovery links to lodge's WorkingTools homepage only when published.

## Optional Lodge Modules

Only Scholarship, Store, Fundraisers, and Games use optional-module gating. Website/CMS, People, Events, Volunteer staffing, member portal/directory, Newsletters, Galleries, Ritualist Program, and Lodge groups/regional discovery remain baseline platform capabilities.

- Module definition: platform-owned stable identity for a supported optional module.
- Module availability: platform-owned decision allowing one lodge to use one module.
- Lodge module preference: lodge-specific enabled/disabled choice, mutable only for an available module by an authorized lodge administrator.
- Effective module state: derived as availability and lodge preference; it is not persisted as an independent source of truth.

Module state is platform/lodge configuration. It is distinct from role permissions, resource authorization, and public CMS publication. Removing availability preserves the lodge preference. Either kind of disablement preserves all module records and ownership.

## Scholarship

Scholarship cycles, applications, answers, reviewer assignments, reviews, and applicant documents are lodge-owned and sensitive. Reviewers receive explicit lodge-scoped authority. Documents remain private and every read/download revalidates lodge, module state, and permission.

## Store and Commerce

- Product and product variant are lodge-owned catalog identities with presentation, price, active state, media, option, and inventory behavior.
- Inventory records/adjustments remain attributable to one lodge and product/variant.
- Order and order line are lodge-owned historical aggregates. Lines snapshot purchase-time description, variant, price, and quantity.
- Customer/contact and fulfillment details are order snapshots needed for pickup or shipping, not global Person records.
- Manual payment and fulfillment states belong to the lodge-owned order and allow future provider-neutral transaction associations without storing raw card data.

Payment-provider integration is a separate boundary from Store and Fundraisers. A platform-supported provider/integration may carry platform-level partner credentials where required and have a lodge-specific connection containing the explicit lodge, provider account identity, connection/enabled state, encrypted credentials/tokens, supported method categories, and encapsulated provider-specific options. Store/Fundraiser records reference only the safe provider-transaction/reconciliation information needed for their lodge-owned business records; they do not own credentials. One lodge's connection may never authorize use of another lodge's merchant account.

Disabling Store retains catalog and order history. Public storefront publication remains an independent CMS decision.

## Fundraising

Fundraising campaign is lodge-owned and contains stable identity, content/media, goal, authoritative manual progress, dates, and lifecycle/history. Progress changes are auditable. A campaign may optionally associate lodge-owned Store products, but Store and Fundraisers remain independent modules. Store disablement removes purchasing actions without deleting or invalidating campaign history or associations.

Future contribution/payment records must retain explicit lodge and campaign ownership. Initial manual progress is not a payment ledger and implies neither tax deductibility nor receipt behavior.

Future provider-backed contributions reference the same lodge payment-provider boundary as Store and retain an explicit campaign correlation. Provider availability, lodge connection, payment-method configuration, and Store/Fundraiser online-payment preference remain distinct; no connection is required for manual progress.

## Games and Shared Content

Game sessions and lodge-private question banks are lodge-owned. Platform/shared question banks are explicitly platform/shared reference content. Games module state controls a lodge's use of the engine and content; it does not transfer ownership or destroy/hide shared records at the platform boundary.
