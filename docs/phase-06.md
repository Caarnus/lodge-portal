# Phase 6 — Member Portal and Directory Privacy

## Outcome

An approved, verified member with a linked person can use one personal portal across all active lodge memberships, maintain permitted canonical profile fields, set lodge-email preferences per membership, and control one person-wide directory presentation. Ordinary lodge directories show only server-authorized fields. Cross-lodge discovery is opt-in, never exposes family information, and cannot be bypassed through search terms, direct identifiers, cached data, photo URLs, or active-lodge switching. Phase 9 extends the safe projection to include active WorkingTools lodge affiliations for authorized directory users.

This phase separates two existing concerns:

- The member directory is a privacy-filtered peer view authorized by `directory.view`.
- Administrative people management is an operational recordkeeping view authorized separately by `people.view` and existing reachability rules.

See [ADR 0005](decisions/0005-person-membership-and-lodge-ownership.md) and [ADR 0007](decisions/0007-person-owned-directory-privacy.md).

## Repository Baseline and Scope

Implementation starts from the repository after Phase 5:

- `Person` is the global canonical identity and optional one-to-one account link. `Membership`, lodge roles, officer assignments, notes, and degree records remain explicitly lodge-owned.
- `PersonAccess` currently implements administrative reachable-person rules. Extend or rename it only where needed for clarity; ordinary directory reads must use a separate contract and must not broaden `PersonAccess`.
- The built-in Member role currently carries `people.view` and `relationships.view`. Phase 6 replaces those administrative permissions with `directory.view` for that built-in role. Existing custom roles are preserved.
- Profile settings currently update only `users.name` and `users.email`. The Phase 6 flow must distinguish login identity from canonical `Person` data and keep linked emails consistent.
- Private profile-photo processing and administrative delivery already exist. Reuse processing/storage conventions, but add self-service and directory-authorized derivative delivery rather than public URLs.
- The dashboard already lists upcoming volunteer commitments through an inline route closure. Replace it with a controller/read service that composes memberships, roles, events, reservations, reminder subscriptions, volunteer commitments, profile state, and permitted lodge tools.
- Phase 4 event eligibility remains authoritative. Dashboard code must call backend event read/eligibility contracts and must not reproduce qualification rules in Vue.
- PostgreSQL is the production database. SQLite behavior does not define privacy, normalization, or concurrency contracts.

Before implementation, run existing backend and frontend gates. A failing Phase 5 baseline is a prerequisite defect; do not weaken Phase 6 privacy tests to accommodate it.

## Locked Product Decisions

- Directory access is authenticated only. There is no public, token-based, or search-engine-visible member directory.
- A directory subject must be non-merged, not soft-deleted, not deceased, and have at least one active membership in an active lodge.
- Directory scope is person-owned: `hidden`, `own_lodge`, or `participating_lodges`. Default is `own_lodge`.
- Missing privacy data is interpreted conservatively as `own_lodge` with all optional fields hidden. Backfill creates the same values.
- Name is always returned for a listed subject. Hiding name means selecting `hidden`, which removes the subject from ordinary directory list, search, detail, and photo routes.
- Email, phone, full mailing address, profile-photo derivative, and degree are independently opt-in. All optional fields default hidden.
- Field choices apply to both ordinary own-lodge and cross-lodge views. Phase 6 does not offer separate per-field values for each audience.
- `own_lodge` means every active lodge membership held by the person, not only primary lodge, current UI lodge, first membership, or account registration lodge.
- `participating_lodges` is the retained internal value for the user-facing **WorkingTools lodges** scope. It includes own-lodge visibility and permits discovery by an authorized directory user from any active WorkingTools lodge. There is no separate lodge participation toggle.
- An ordinary directory requester must be approved, verified, linked to a non-merged person, actively belong to the explicit requesting lodge, and hold `directory.view` there. Phase 9 permits a platform administrator to browse the same privacy-filtered directory projection without a lodge membership; this does not grant access to hidden subjects or hidden optional fields.
- Cross-lodge results may reveal all active WorkingTools lodge names/numbers as a bounded affiliation projection. They do not reveal primary lodge, member numbers, roles, officer history, or membership dates/statuses.
- An opted-in own-lodge degree comes from the active membership in the requesting lodge. An opted-in cross-lodge degree is the highest current Masonic degree among active memberships. Past Master is not returned as a degree.
- Family relationships and related-person information are never serialized by directory endpoints, even within the subject's own lodge. Existing administrative relationship tools remain separate.
- Administrative `people.view` access may show maintained fields needed for lodge recordkeeping regardless of ordinary directory settings, subject to existing lodge reachability. Directory preferences never delete or redact source records.
- Officer assignment does not grant a permission. Current officers receive broader record access only through an explicit lodge role that carries `people.view`, consistent with Phase 3.
- Members may edit only their own linked, non-merged person. They may change preferred name, canonical email, phone, mailing address, profile photo, directory preferences, and per-membership general lodge-email preference.
- Legal name, suffix, birth/death data, deceased state, memberships, degree, primary lodge, member numbers, dates, honors, notes, family relationships, officer assignments, roles, approval state, and account linking remain locked from self-service.
- Changing canonical email updates both linked `people.email` and login `users.email` in one transaction, rechecks both uniqueness constraints, invalidates email verification, and sends the existing verification flow. Partial updates are not allowed.
- Per-membership `receives_lodge_email` controls future general lodge/newsletter mail only. It does not cancel or suppress authentication/security mail, registration decisions, reservation confirmations, requested event reminders, event cancellation mail, or volunteer staffing reminders.
- A member may edit a communication preference only for a current active membership. Historical preference is retained when membership ends, but no member-facing endpoint may mutate it until eligibility returns.
- Dashboard sections are independent summaries with bounded result counts and explicit links to full tools. Failure or emptiness in one section must not imply another engagement type.
- Active-lodge selection affects navigation convenience, not membership proof, directory visibility, dashboard ownership, or cross-lodge authorization.
- Search and direct detail use one backend visibility predicate and one explicit projection. Eloquent models are never serialized directly.
- Search by a hidden field must not return or count the subject. Search result counts, pagination, suggestions, and empty states must not leak hidden-field matches.
- Phase 6 uses database-backed search without an external index or directory result cache. A successful privacy/profile/membership change is visible on the next request.
- Phase labels such as `PhaseSix`, `P6`, or `phase_06` must not appear in implementation filenames, class names, route names, database objects, or code comments.

## Domain Model

### Directory Privacy

Add `person_directory_privacy_settings` as person-owned data with:

- `person_id`, unique and foreign-keyed to `people` with cascade delete.
- `scope`: `hidden`, `own_lodge`, or `participating_lodges`.
- `show_email`, default false.
- `show_phone`, default false.
- `show_address`, default false.
- `show_profile_photo`, default false.
- `show_degree`, default false.
- Nullable `updated_by` user identifier for audit/support display.
- Timestamps.

Use a dedicated `DirectoryVisibilityScope` enum. Add a PostgreSQL scope check. Treat the row as a one-to-one extension of `Person`; it is not lodge-owned and must not carry `lodge_id`.

Seed/backfill one row for every existing non-deleted person using the conservative defaults. Person creation must create the default row in the same transaction, or the domain service must guarantee the same effective default until a row is materialized. Tests must cover both paths so a missed hook cannot become public-by-null behavior.

The privacy row is not copied during person merge. The merge service must resolve privacy explicitly:

- The destination person's existing settings win.
- When the destination has no row, materialize its conservative defaults rather than copying a more permissive source row.
- Delete the retired source row through the source-person lifecycle.
- Record the resolution in immutable merge audit metadata.

This deliberately avoids making a merge expand directory exposure.

### Membership Communication Preferences

Add `membership_communication_preferences` as membership-owned data with:

- `membership_id` and matching `lodge_id`.
- `receives_lodge_email`, default true.
- Nullable `updated_by` user identifier.
- Timestamps.

Require unique `membership_id`, unique `(membership_id, lodge_id)` for downstream ownership checks, and a composite foreign key to `memberships (id, lodge_id)`. A missing row has effective default `true`; backfill the explicit default for existing memberships.

This table does not contain event subscription, reservation, volunteer, authentication, or transactional-mail consent. Future communications work must name the exact preference it consumes.

### Existing Person and User Fields

No directory copy of identity/contact values is allowed. Projection reads canonical values from `people`:

- Display name from existing preferred/legal-name behavior.
- Canonical email.
- Phone.
- Mailing-address fields as one all-or-nothing address projection.
- Ready profile-photo derivative only.

`users.email` remains login and verification identity. For a linked account, self-service email writes keep it equal to `people.email`. `users.name` should mirror the resulting display name for framework surfaces but is not authoritative directory data.

Do not add JSON privacy blobs, duplicated contact snapshots, public profile slugs, family visibility flags, or membership-level directory scopes.

## Directory Visibility and Projection

Add a dedicated service under `app/Domain/Directory` or the repository's equivalent domain path. It owns query eligibility, field searchability, and projection. Controllers only validate input, authorize the explicit requesting lodge, invoke this service, and return explicit arrays/resources.

### Subject Eligibility

A subject is eligible for any ordinary directory result only when all are true:

1. Person is not soft-deleted, merged, or deceased.
2. Person has at least one current active membership in an active lodge.
3. Effective privacy scope is not hidden.
4. For own-lodge treatment, person has a current active membership in the requesting lodge and scope is own-lodge or participating-lodges.
5. Otherwise, scope is participating-lodges.

An ended, inactive-status, suspended, expelled, demitted, or deceased membership does not create own-lodge treatment. A disabled lodge does not make its membership a source of cross-lodge participation. Reevaluate these facts on every request.

### Requester Eligibility

Ordinary member list, search, detail, and photo requests require all of:

1. Approved and email-verified user.
2. Linked person is present, non-merged, non-deleted, and not deceased.
3. Current active membership in the explicit requesting lodge.
4. `directory.view` in that same lodge.
5. Requesting lodge is active.

The route's lodge is the authorization context. `current_lodge_id`, a submitted lodge ID, or membership in another lodge is never substituted.

Phase 9 adds a platform-administrator exception to requester eligibility. A platform administrator may browse the same privacy-filtered cross-lodge projection without an active lodge membership. Subject privacy, active-affiliation, direct-detail, photo, and hidden-field rules still apply.

### Projection Shapes

List and detail responses use a stable allowlist. At most they contain:

- Opaque person identifier used only by the authorized detail/photo routes.
- Display name.
- Email, nullable by privacy.
- Phone, nullable by privacy.
- Structured mailing address, nullable as a whole by privacy.
- Degree display label, nullable by privacy or unavailable data.
- Authorized profile-photo URL, nullable by privacy or readiness.
- For authorized cross-lodge views after Phase 9, a bounded list of active WorkingTools lodge affiliations containing only lodge identifier, name, number, and safe route slug.

Do not include raw privacy flags for another person. The presence/null state of each output is enough. Do not include legal-name parts, birth/death values, raw membership objects, primary-lodge flags, member numbers, membership status/dates, account IDs, role IDs, relationship counts, audit fields, media paths, or timestamps.

Self profile endpoints may return the user's own editable values and privacy controls. They are not directory projections.

### Search Contract

The directory page supports:

- Audience filter: requesting lodge or WorkingTools lodges.
- Trimmed query with a two-character minimum when non-empty and a bounded maximum.
- Name search over preferred and legal name fields.
- Email search only for subjects whose `show_email` is true.
- Phone search only for subjects whose `show_phone` is true, using existing normalized-phone behavior.
- Optional degree filter only for subjects whose `show_degree` is true and using the context-specific degree rule.
- Stable ordering by display surname/name and person identifier.
- Server-side pagination with a maximum page size of 25.

Address is displayed when opted in but is not searchable in Phase 6. This reduces accidental household/location discovery. Hidden values cannot affect match inclusion, match highlighting, counts, pagination, or suggestions.

Participating-lodges search deduplicates a multi-lodge person to one result. Own-lodge results use the requesting membership's degree; cross-lodge results calculate highest active degree in SQL or a bounded, eager-loaded projection without N+1 queries.

Empty queries may list the requester's own-lodge directory. Phase 9 permits empty-query WorkingTools-wide and group-filtered browsing, with the same privacy predicate, stable pagination, bounded page size, private/no-store responses, and rate limiting.

### Direct Details and Photos

`GET /lodges/{lodge}/directory/{person}` reruns the same requester and subject visibility checks. A well-formed but invisible person returns 404, including hidden, ended, cross-lodge-not-opted-in, deceased, merged, and foreign-context cases.

Directory photo delivery reruns visibility plus `show_profile_photo`, serves only the processed derivative, sets private cache headers, and never reveals storage paths. Missing/not-ready photo returns 404. Privacy changes take effect before a subsequent response; clients must not receive a long-lived publicly reusable URL.

Self-service photo preview uses a separate own-profile route and may display the current derivative regardless of directory photo visibility.

## Profile and Privacy Management

### Editable Profile

Replace the current account-only profile update with explicit sections or endpoints:

- Account email and verification status.
- Preferred name.
- Phone.
- Structured mailing address.
- Profile photo upload/remove.
- Directory scope and optional-field choices.
- Per-active-membership general lodge-email preference.

Use form requests and a transactional profile service. The service locks/reloads the user and linked person, rejects a stale or changed link, verifies neither person is merged/deleted/deceased, and audits changed fields without storing secrets or unnecessary full values.

Email change must:

1. Normalize and validate once.
2. Verify uniqueness across both people and users, excluding the linked pair.
3. Lock both records.
4. Update both records atomically.
5. Clear `email_verified_at` only when value changed.
6. Preserve approval status and roles.
7. Send the existing verification notification after commit.

Do not allow a profile request to change `person_id`, user approval, active lodge, memberships, roles, or administrative person fields.

### Privacy Updates

Privacy updates validate enum and explicit booleans, upsert the one-to-one row transactionally, and record `directory_privacy.updated` with actor/person IDs and before/after settings. A user cannot update another person by submitting an identifier.

The UI must explain:

- Hidden removes the member from ordinary directories but not authorized lodge records.
- Own lodge applies to every current active lodge membership.
- WorkingTools lodges is the user-facing cross-lodge opt-in.
- Optional fields apply to both audiences.
- Family information is never shared by the directory.

### Communication Preference Updates

The request accepts membership identifier plus boolean, but reloads that membership through the current user's linked person and verifies active status and matching lodge. It never trusts submitted person or lodge identifiers. Record `membership_communication_preference.updated` with person, membership, lodge, actor, and before/after boolean.

### Profile Photo

Reuse existing validation, image processing, private storage, and bounded upload rules. Self-service upload is authorized by linked-person ownership, not `people.manage`. Replacing/removing a photo preserves recoverability and audit behavior already established by the media pipeline. Queue work carries the person identifier and revalidates that it still resolves to the same live person before writing derivative metadata.

## Administrative People Access

Phase 6 must preserve administrative workflows while closing ordinary-member bypasses:

- Add `directory.view` to permission catalog.
- Built-in Administrator: all permissions.
- Built-in Officer: retain administrative permissions and add `directory.view`.
- Built-in Member: `directory.view`; remove `people.view` and `relationships.view` during idempotent role synchronization.
- Built-in Non-member: no directory permission by default.
- Custom roles: preserve their assigned permissions; administrators may explicitly add `directory.view`.

Existing people-management routes continue to use `PersonAccess`, `people.view`, `people.manage`, and relationship permissions. Their UI must label directory choices as member-presentation settings and not imply that hidden data is erased from lodge records.

Do not silently route a member denied administrative people access into the administrative People page. Navigation must point ordinary members to Directory.

## Member Dashboard

Replace the inline dashboard route with a thin `DashboardController` and a read-focused service. Build all sections from the authenticated user's stable user/person identifiers, not `current_lodge_id`.

### Memberships and Roles

For every active membership in an active lodge, show:

- Lodge name/number and safe lodge branding derivative where available.
- Membership type/status and degree from that membership.
- Primary-lodge indicator derived from lodge number.
- Explicit lodge role names assigned to the user for that lodge.
- Safe links to lodge site, directory, and tools the user can access.

Do not show notes, member number, family data, or officer history in the dashboard summary. Ended memberships may appear in a collapsed history only if explicitly added later; they are excluded in Phase 6.

### Upcoming Lodge Events

Show bounded, deduplicated future scheduled occurrences from the user's active lodges. Reuse event visibility/eligibility and effective occurrence fields. Exclude draft, cancelled, archived, past, disabled-lodge, and ineligible occurrences. Do not create regional or cross-lodge event discovery.

### Personal Event Activity

Keep distinct sections and records for:

- Active future reservations belonging to the current user/person, with status and party size.
- Active reminder subscriptions belonging to the current user/person, distinguishing occurrence and series scope.
- Active future volunteer commitments using the Phase 5 contract.

Never infer one section from another. Use ownership by both current `user_id` and current `person_id` where Phase 4/5 historical schemas carry both. If the account link changes, omit ambiguous historical activity and leave administrative correction to existing tools.

### Profile and Tools

Show profile-completion cues for missing preferred name/contact/photo without revealing directory choices to another user. Show current directory scope and a settings link. Lodge tools come from server-authorized permission checks and use explicit lodge routes. Hiding a navigation link is not authorization.

Each dashboard query is bounded and eager-loaded. Define deterministic ordering and return counts plus a short first page rather than loading unlimited history.

## Routes and Controllers

Use repository-consistent namespaces and these semantic endpoints; exact controller grouping may follow current flat conventions without moving unrelated files:

| Method | Route                                               | Purpose                                          |
| ------ | --------------------------------------------------- | ------------------------------------------------ |
| GET    | `/dashboard`                                        | Composed personal portal                         |
| GET    | `/settings/profile`                                 | Own profile, privacy, and membership preferences |
| PATCH  | `/settings/profile`                                 | Own canonical editable fields/account email      |
| PUT    | `/settings/directory-privacy`                       | Own person-wide privacy settings                 |
| PUT    | `/settings/memberships/{membership}/communications` | Own active membership preference                 |
| POST   | `/settings/profile-photo`                           | Upload/replace own photo                         |
| DELETE | `/settings/profile-photo`                           | Remove own photo                                 |
| GET    | `/settings/profile-photo`                           | Serve own derivative                             |
| GET    | `/lodges/{lodge}/directory`                         | Own/cross-lodge search and list                  |
| GET    | `/lodges/{lodge}/directory/{person}`                | Authorized directory detail                      |
| GET    | `/lodges/{lodge}/directory/{person}/photo`          | Authorized derivative                            |

All routes use `auth`, `verified`, and `approved`. Directory routes additionally verify active requester membership and `directory.view` for the explicit lodge. Settings routes require a current valid person link. Use throttling on participating-lodges search if normal application throttling does not already cover authenticated enumeration.

Route model binding never proves directory visibility. Controllers must pass bound identifiers through the access service before projection. Nested ownership mismatch or invisible subject returns 404; authenticated requester lacking directory permission/membership for the correctly resolved lodge returns 403.

## UI Views and Components

### Dashboard

Upgrade `resources/js/pages/Dashboard.vue` into responsive portal cards for Memberships, Upcoming Events, Reservations, Reminder Subscriptions, Volunteer Commitments, Profile, and Available Tools. Preserve independent labels and actions. Provide useful empty states and avoid a single giant table.

### Profile Settings

Keep password, appearance, and two-factor settings separate. Profile page uses clear panels for editable identity/contact, photo, directory privacy, and lodge-specific email preferences. Explain global effects of canonical person/contact edits before save.

Changing email must warn that verification is required again. Privacy controls must be operable by keyboard, not rely on color, and state current effective audience in text.

### Directory

Add `resources/js/pages/directory/Index.vue` and `Show.vue` or equivalent established casing. Directory UI includes:

- Requesting-lodge context.
- Own lodge / WorkingTools lodges selector.
- Debounced server search with shareable query parameters.
- Paginated result cards/list.
- Explicit placeholders for hidden optional fields; do not imply missing versus private where that distinction leaks data.
- Mobile and desktop layouts, light/dark styles, focus states, labels, and accessible result counts.

Do not return all records to Vue for client filtering. Do not embed hidden contact values in Inertia props, page HTML, data attributes, debug payloads, or client-side search indexes.

Navigation shows Directory only for lodge contexts where server-shared permissions allow it. Administrative People remains separate and appears only with its existing administrative authorization.

## Authorization Matrix

| Action                               | Anonymous | Active member with `directory.view` |        `people.view` administrator/officer |                               Platform administrator |
| ------------------------------------ | --------: | ----------------------------------: | -----------------------------------------: | ---------------------------------------------------: |
| View own profile/settings            |        No |                   Own linked person |                          Own linked person |                                    Own linked person |
| Edit own permitted profile/privacy   |        No |                   Own linked person |                          Own linked person |                                    Own linked person |
| Edit own membership email preference |        No |               Own active membership |                      Own active membership |                                Own active membership |
| Search requesting lodge directory    |        No |               Yes, privacy-filtered | Yes, privacy-filtered when using Directory | Only with active member context and `directory.view` |
| Search WorkingTools lodges           |        No |            Yes, opt-in results only |                   Yes, opt-in results only | Yes after Phase 9; same privacy-filtered projection, no membership required |
| View directory detail/photo          |        No |           Same visibility as search |       Same visibility when using Directory |                 Same visibility when using Directory |
| View administrative person record    |        No |          No by directory permission |        Existing `people.view` reachability |                         Existing platform-admin rule |
| Edit administrative person fields    |        No |                                  No |      Existing `people.manage` reachability |                         Existing platform-admin rule |
| View family relationships            |        No |             Never through Directory |          Existing relationship permissions |                         Existing platform-admin rule |

Administrative authorization and ordinary directory projection must not be combined into a response that changes shape based only on role. Use distinct endpoints/pages so tests and users can tell which context they are in.

## Audit, Logging, Caching, and Operations

Audit:

- Self profile changes with changed-field names; avoid duplicating full address/email values unless existing audit policy requires them.
- Directory privacy before/after values.
- Membership communication preference changes.
- Self-service profile photo upload/remove and processing outcome.
- Built-in role permission synchronization where existing role audit conventions support it.

Ordinary directory searches and views are not individually audit-logged in Phase 6. Application logs for failures include requester user/person ID, requesting lodge ID, route/action, and subject ID where safe. Never log search terms that may contain email, phone, or personal data at info level.

Do not add an external search service, cached result set, autocomplete cache, or browser persistence of directory responses. Send authenticated pages and photo responses with appropriate private/no-store behavior. If framework response caching exists, explicitly exclude directory/settings routes.

## Merge, Membership, and Account Lifecycle

- Person merge applies the conservative privacy resolution defined above and preserves membership communication rows with moved memberships.
- Membership creation creates/defaults its communication row. Ending membership immediately removes own-lodge requester/subject eligibility and dashboard inclusion without deleting history.
- Lodge disablement removes it as a requester context and from active cross-lodge participation; reactivation restores eligibility based on current memberships and unchanged privacy settings.
- Account unlink/revocation blocks self settings and directory requesting access but does not alter subject privacy or membership-owned preferences.
- Account deletion retains person privacy and membership preferences. A later safely linked account inherits that person's existing settings.
- Changing user-person link during a request causes transactional revalidation failure rather than writing the former or new person accidentally.
- Deceased, merged, and soft-deleted people are excluded immediately from ordinary directory and dashboard profile flows.

## Automated Test Requirements

### Unit and Domain Tests

- Every visibility scope for own-lodge and cross-lodge requesters.
- Effective defaults when row exists and is missing.
- Every optional-field flag and address all-or-nothing behavior.
- Own-lodge degree versus highest active cross-lodge degree; PM exclusion.
- Multi-membership deduplication and own-lodge treatment in each active lodge.
- Requester eligibility and active-lodge/permission checks.
- Hidden-field search non-discoverability for email and phone.
- Stable pagination/order without N+1 queries at representative volume.
- Conservative merge resolution.

### Feature Tests

- Profile updates, locked fields, stale link, merged/deceased person, email dual-uniqueness, atomic rollback, and reverification.
- Privacy and communication writes, ownership, ended membership, audit data, and immediate read consistency.
- Self and directory photo authorization, derivative-only response, hidden photo, not-ready photo, and private cache headers.
- Built-in role synchronization: Member loses administrative permissions and gains `directory.view`; Officer/Administrator retain required access; custom roles remain unchanged.
- Directory list/search/detail parity, 403 versus 404 behavior, malformed filters, pagination caps, throttling, and direct person/lodge ID attacks.
- Family, notes, member numbers, lodge affiliations, role/account identifiers, legal/birth/death data, storage paths, and raw privacy flags absent from props and HTML.
- Administrative People behavior remains available to authorized roles regardless of directory privacy, while ordinary members cannot reach it.
- Dashboard multi-lodge memberships/roles/tools, event eligibility, bounded sections, deduplication, activity ownership, and exclusion of ended/disabled/cancelled/past data.
- Active-lodge switching cannot change visibility or expose another lodge.
- Account unlink/deletion, membership end/reactivation, lodge disable/reactivation, person merge, and privacy changes take effect immediately.

Use at least three active lodges plus one disabled lodge. Fixtures need:

- One person active in Lodges A and B.
- One own-lodge-only person in A.
- One cross-lodge-opted-in person in B with mixed field flags.
- One hidden person.
- One related spouse/child with no independent cross-lodge consent.
- One ended member, one deceased person, and one merged source.
- Requesters with directory-only, administrative, custom-role, no-permission, unlinked, unverified, and platform-admin-only states.

### Browser Tests

Playwright must cover:

1. Member sees all active memberships and distinct event-activity cards.
2. Member edits preferred/contact fields and sees global-effect messaging.
3. Member selects own-lodge scope and another lodge cannot find them.
4. Member opts into WorkingTools lodges, enables phone, leaves address hidden, and another lodge sees only name/phone.
5. Searching hidden address/email does not reveal the result.
6. Member selects hidden and disappears from list, search, direct detail, and prior photo URL.
7. Authorized officer still reaches administrative member record through People, with clear administrative context.
8. Family data never appears in directory network responses or rendered DOM.
9. Directory and settings work at mobile/desktop widths, light/dark modes, keyboard-only navigation, and visible focus.

## Manual Acceptance Checklist

1. Establish clean Phase 5 backend/frontend/browser baseline.
2. Seed at least three lodges and privacy personas listed above.
3. Log in as multi-lodge member; verify memberships, roles, tools, eligible events, reservations, subscriptions, and commitments.
4. Change preferred name, phone, address, and photo; verify linked canonical Person and account display update.
5. Change email; confirm both Person/User update atomically and protected app access requires reverification.
6. Set own-lodge scope; verify Lodges A and B where subject is active can discover them, Lodge C cannot.
7. Set `participating_lodges` scope with phone enabled and address/email/photo/degree disabled; verify Lodge C sees name, phone, and bounded active WorkingTools lodge affiliations only.
8. Search using hidden email/address/phone values; verify no match or count difference.
9. Enable degree; verify own-lodge and cross-lodge derivation without membership/lodge disclosure.
10. Set hidden; verify list, search, detail, and previously copied derivative route return no data.
11. Log in through a directory-only Member role; verify Directory works and administrative People/Relationships do not.
12. Log in through authorized Officer/Administrator role; verify administrative People access still works and is clearly separate.
13. End one membership and disable one lodge; verify requester, subject, dashboard, and directory eligibility update on next request.
14. Change per-lodge email preference; verify no effect on requested event or volunteer notifications.
15. Inspect Inertia props, rendered HTML, logs, and network/photo responses for hidden fields, family data, memberships, storage paths, and cross-tenant identifiers.
16. Repeat critical directory/settings flows on mobile and desktop in light and dark modes.

## Detailed Implementation Work Plan

### P6-01 Schema, Enums, and Permission Split

Prerequisite: clean Phase 5 baseline.

Deliver:

- Directory scope enum, privacy/preferences migrations, models, relationships, casts, factories, conservative backfill, and PostgreSQL constraints.
- Add `directory.view`; idempotently synchronize built-in roles exactly as defined while preserving custom roles.
- Extend person creation, membership creation, merge, and deletion lifecycles for the new rows.

Tests and gate:

- Migration/rollback on fresh PostgreSQL, constraints, defaults/missing-row semantics, factories, seeder idempotency, merge behavior, role synchronization/custom-role preservation, focused tests, full Laravel suite, and Pint.

### P6-02 Directory Access, Search, and Projection Contract

Prerequisite: P6-01.

Deliver:

- Requester/subject eligibility objects or service methods.
- Own-lodge and participating-lodges query scopes.
- Field-safe list/detail projection, degree resolution, hidden-field-safe search, ordering, pagination, and query bounds.
- No HTTP or Vue work until contract tests pass.

Tests and gate:

- Full scope/field matrix, three-lodge multi-membership fixtures, disabled/ended/deceased/merged cases, search inference attacks, deduplication, direct projection allowlist, query-count bounds, and PostgreSQL execution.

### P6-03 Self Profile and Email Consistency

Prerequisites: P6-01.

Deliver:

- Transactional own-profile service and form request.
- Preferred/contact/address writes, mirrored display name, dual Person/User email update, reverification, stale-link protection, and audit events.
- Refactor current settings controller without weakening account deletion/password/2FA behavior.

Tests and gate:

- Editable/locked field matrix, uniqueness races/errors, rollback, reverification, stale/merged/deceased links, audits, existing Settings regressions, and Pint.

### P6-04 Privacy, Communication, and Photo Settings

Prerequisites: P6-01 and P6-03.

Deliver:

- Own privacy and per-membership communication endpoints/services.
- Self-service photo upload/remove/derivative route through existing processing pipeline.
- Profile settings panels, explanatory copy, validation, pending-photo state, and accessible controls.

Tests and gate:

- Ownership, active membership, enum/boolean validation, audits, photo security/readiness/cache behavior, queue stale-person protection, responsive UI, typecheck, lint, and focused browser flow.

### P6-05 Directory HTTP and UI

Prerequisites: P6-02 and P6-04.

Deliver:

- Lodge-context directory controllers, form request, routes, list/detail/photo responses, 403/404 semantics, and throttling.
- Directory pages, audience selector, server pagination/search, safe empty states, navigation, and accessible responsive presentation.
- Ensure serialized props use only P6-02 projections.

Tests and gate:

- Route permission matrix, nested/direct identifier attacks, list/detail/search parity, hidden-photo prior URL, payload/HTML leakage checks, browser privacy journey, typecheck, lint, and build.

### P6-06 Administrative People and Role Integration

Prerequisites: P6-01 and P6-05.

Deliver:

- Preserve `PersonAccess` administrative behavior and clearly separate People versus Directory navigation/context.
- Confirm administrative views do not accidentally adopt ordinary directory redaction and ordinary Member role cannot use administrative people/relationship routes.
- Add privacy explanation to appropriate administrative/profile surfaces without granting admins a member-impersonation write path.

Tests and gate:

- Built-in/custom role matrix, officer/administrator administrative override, assignment-without-role denial, Member route denial, family privacy, platform-admin separation, and Phase 3 regression suite.

### P6-07 Dashboard Read Model

Prerequisites: P6-01, P6-02, and stable Phase 4/5 contracts.

Deliver:

- Dashboard controller and compositional read service.
- Bounded memberships/roles/tools, upcoming own-lodge occurrences, reservations, reminder subscriptions, volunteer commitments, and profile/privacy summary.
- Remove inline route query while preserving Phase 5 withdrawal behavior through its existing endpoint.

Tests and gate:

- Multi-lodge and active-context independence, event eligibility, deduplication/order/bounds, dual user/person ownership, disabled/ended/cancelled/past exclusions, independent activity semantics, and query-count assertions.

### P6-08 Dashboard UI and End-to-End Member Portal

Prerequisites: P6-04, P6-05, and P6-07.

Deliver:

- Responsive dashboard cards, profile cues, lodge links, permission-derived tools, and independent empty/error states.
- Complete navigation integration and end-to-end member journey across dashboard, settings, directory, public events, and volunteer withdrawal.

Tests and gate:

- Vue typecheck/lint/build, accessible mobile/desktop/light/dark browser coverage, no hidden-data props, and Phase 4/5 browser regressions.

### P6-09 Lifecycle, Privacy Hardening, and Documentation

Prerequisite: all earlier packages.

Deliver:

- Complete merge, membership end/reactivation, lodge disable/reactivation, account unlink/deletion, email verification, and photo replacement integration.
- Direct-identifier/search-inference attack matrix, log/HTML/network inspection, query/performance review, and no-cache verification.
- Reconcile architecture, domain model, tenancy, authorization, testing strategy, README, ADR index, and operational notes with implemented behavior.
- Resolve every test, type, lint, build, IDE, and audit finding. Request approval before suppressing any warning.

Tests and gate:

- Full Laravel suite, Pint, route inspection, frontend typecheck/lint/build, browser typecheck/Playwright, Composer/npm audits, `git diff --check`, and completed manual checklist.

## Dependency and Parallelization Map

- P6-01 blocks all schema-dependent work.
- After P6-01, P6-02 and P6-03 may proceed independently if they do not edit the same Person/User service files.
- P6-04 waits for self-profile foundations from P6-03.
- P6-05 waits for the read contract from P6-02 and photo/privacy behavior from P6-04.
- P6-06 waits for role migration and directory routes; it owns final People/Directory separation.
- P6-07 may begin after P6-01 while P6-04/P6-05 proceed, but it must consume stable event and volunteer contracts rather than duplicating them.
- P6-08 waits for profile, directory, and dashboard APIs.
- P6-09 is the final integration gate.

For one agent, use numbered order. With multiple agents, do not parallel-edit role catalog/synchronization, `PersonAccess`, Person merge, profile controller/routes, dashboard routes, shared navigation, or the central directory projection without explicit file ownership and an integration owner.

## Agent Handoff Contract

Give an implementation agent one package at a time. Every handoff must:

- Name package and exact deliverables/gate from this document.
- Require reading this full specification, ADRs 0005 and 0007, Phase 5, architecture, domain, tenancy, authorization, and testing documents.
- Identify verified prerequisite commits/contracts and PostgreSQL baseline result.
- State allowed files/directories and preserve unrelated dirty-worktree changes.
- Prohibit Phase labels in implementation artifacts.
- Prohibit direct Eloquent serialization, client-side privacy filtering, public photo URLs, membership/family disclosure, hidden-field search matches, and using `current_lodge_id` as proof.
- Require Lodge A/B/C plus disabled-lodge adversarial tests and exact 403/404 behavior.
- Require reporting any conflict with locked person ownership, role separation, privacy defaults, degree derivation, email atomicity, or event/activity independence rather than silently changing it.
- Require focused tests plus package gates and warning resolution without suppression.
- Stop after package gate and report files, migrations, tests/results, risks, and prerequisites for next package.

## Definition of Done

Phase 6 is complete only when:

- Every package and final gate passes against PostgreSQL.
- Directory scope and optional fields are enforced identically in list, search, detail, photo, counts, pagination, props, and HTML.
- Hidden fields cannot influence discoverability.
- Multi-lodge own-lodge treatment works from membership facts without active-context dependence.
- Cross-lodge results are opt-in and deduplicated. After Phase 9 they reveal only the bounded active WorkingTools affiliation projection, never family data or administrative membership fields.
- Ordinary Member role uses `directory.view` and cannot reach administrative People/Relationships; authorized administration remains functional.
- Profile writes are ownership-safe; Person/User email changes are atomic and require reverification.
- Dashboard shows bounded, correct multi-lodge data while keeping reservations, reminder subscriptions, and volunteer commitments distinct.
- Person merge, membership/lodge lifecycle, account lifecycle, and photos cannot expand exposure.
- Manual acceptance passes at mobile/desktop widths and light/dark modes.
- Cross-cutting docs match implementation and no implementation artifact uses a Phase label.

## Non-Goals

- Public directory pages or public member profiles.
- Public or anonymous member-directory access or lodge-affiliation disclosure.
- Separate privacy choices per lodge or separate field flags for own-lodge versus cross-lodge audiences.
- Family directory, household search, relationship sharing, or address search.
- Regional organization, lodge discovery, or regional event discovery.
- Member-to-member messaging, contact forms, social feeds, presence, or friend/favorite lists.
- External search service, fuzzy/geospatial search, cached directory results, or offline directory sync.
- CSV/PDF directory exports, labels, vCards, mass contact download, or print directory.
- Admin impersonation or administrators changing a person's privacy choices.
- Member editing of legal/administrative/membership/degree/role/officer/family data.
- Replacing explicit event reminder subscriptions or volunteer reminder consent with a global communication preference.
- Newsletter delivery; Phase 6 only stores the future general lodge-email preference.
- Regional ritualist discovery, scholarship, games, or later portal tools.
