# Phase 9 — Lodge Groups and Regional Discovery

## Outcome

WorkingTools gains a public lodge directory, a public and member-aware aggregated event calendar, configurable overlapping lodge groups, group landing pages, and group filters for existing member-directory and ritual-assistance discovery.

Phase 9 is implemented in development. The specification below records the implemented slice and its locked contracts; Phase 10 is the next planned phase.

Groups organize and narrow results. They never grant visibility, membership, event eligibility, directory consent, ritual consent, reservation rights, reminder rights, lodge administration, or access to tenant-owned records.

Phase 9 also replaces remaining user-facing “Participating lodges” language with “WorkingTools lodges,” while retaining existing internal enum and API values where renaming would add migration risk.

## Repository Baseline and Scope

Implementation was based on the completed Phase 1–8 development baseline:

- `Lodge` is tenant-owned identity/configuration with active, disabled, and disabled-and-locked lifecycle states.
- Public lodge sites resolve through `/l/{lodge-slug}` and expose only published content from active lodges.
- `Event`, `EventOccurrence`, and event children are lodge-owned. Materialized occurrences provide stable discovery identities.
- `EventEligibility` owns protected-event visibility and reservation qualification, but must be hardened before regional queries reuse it.
- `DirectoryAccess` owns person visibility, hidden-field-safe search, and safe projection. Cross-lodge scope uses internal value `participating_lodges`.
- `RitualAssistanceAccess` owns ritual-assistance requester/subject eligibility and safe projection. Cross-lodge ritual scope also uses internal value `participating_lodges`.
- General directory results currently omit lodge affiliations and require a non-empty cross-lodge query. Phase 9 intentionally changes both behaviors for authorized users.
- Ritual-assistance results already expose active hosted-lodge affiliations after explicit ritual consent.
- PostgreSQL is production storage; Redis supports ordinary cache, queue, and rate-limit infrastructure.

Phase 9 validation includes the Phase 1–8 backend/frontend gates. Earlier privacy, tenancy, recurrence, reservation, reminder, and ritual tests remain authoritative regression contracts.

## Locked Product Decisions

- **WorkingTools lodge** means any lodge hosted as a tenant. No separate participation enrollment controls ordinary cross-lodge discovery.
- Public lodge directory lists every active WorkingTools lodge, including lodges in no group and lodges without a published homepage.
- Disabled and disabled-and-locked lodges are absent from public and active group discovery.
- Published WorkingTools homepage is lodge directory’s website link. Omit link when no published homepage exists.
- Lodge meeting schedule is optional free text, separate from meeting location. Example: “First and third Tuesdays at 7:00 PM.”
- Groups are platform-owned organization/filtering records, not tenant boundaries.
- One lodge may belong to zero, one, or many groups.
- Platform administrators manage groups, group membership, and group-type reference values through CRUD interfaces.
- Group types are database records, not authorization enums.
- Active groups with discoverable landing pages appear publicly. Active non-discoverable groups remain usable by authorized authenticated users as filters, but do not appear in public group lists or filters.
- Archived groups are unavailable for new filtering or membership edits. Historical membership/audit records remain.
- Public event discovery contains only published, scheduled, eligible occurrences owned by active lodges.
- Anonymous users see public events only. Eligible authenticated members may also see Masons-only events permitted by hardened Phase 4 rules. Lodge-only events remain restricted to eligible members of owning lodge.
- Regional discovery creates no new event visibility or interaction modes. Reservation and reminder actions continue through owning event/lodge routes.
- Cross-lodge directory browsing is allowed without text query. Results remain paginated, rate limited, private/no-store, and privacy filtered.
- Authorized cross-lodge directory results show all active WorkingTools lodge affiliations for subject. Affiliation projection contains only safe lodge identity; no member numbers, dates, statuses, notes, roles, officer records, or primary-lodge state.
- Ordinary directory users remain approved, verified members with active membership and `directory.view` in explicit active requesting lodge.
- Platform administrators may browse same privacy-filtered cross-lodge directory without lodge membership. Platform role does not reveal hidden subjects or optional fields.
- Member directory and affiliation information is never public.
- Group-filtered directory and ritual results are deduplicated by person.
- Group filters may reveal that displayed person has membership in selected group; this inference is accepted for authorized users.
- Existing internal values such as `participating_lodges` remain unchanged. New user-facing copy says “WorkingTools lodges.”
- Shared protected-event eligibility must require approved/verified account, eligible linked person, active membership in active WorkingTools lodge, active event-owning lodge, and required qualification.
- Phase labels such as `PhaseNine`, `P9`, or `phase_09` must not appear in implementation filenames, class names, routes, database objects, or code comments.

## Domain Model

### Lodge Meeting Schedule

Add nullable `meeting_schedule` string to `lodges`:

- Maximum 255 characters.
- Plain text only.
- Managed through existing platform/lodge settings forms according to existing lodge-edit authorization.
- Public because it is explicitly intended for public lodge discovery.
- No recurrence parsing, time-zone inference, or calendar generation.

Keep `meeting_location` unchanged. “Where” and “when” remain separate fields.

### Lodge Group Types

Add platform-owned `lodge_group_types`:

- `id`.
- Stable unique `key`, lower snake case, immutable after creation or changed only through explicit migration-safe service rules.
- Unique display `name`.
- Optional description.
- `sort_order`.
- `is_active`.
- Timestamps.

Seed idempotent initial values:

- `region` — Region.
- `district` — District.
- `county` — County.
- `informal` — Informal.
- `other` — Other.

Inactive types remain attached to historical groups. They cannot be selected for new groups or ordinary edits unless preserving current value.

Platform CRUD may hard-delete a type only when no group references it. Referenced types must be deactivated instead.

### Lodge Groups

Add platform-owned `lodge_groups`:

- `id`.
- `lodge_group_type_id`.
- Unique `name`.
- Globally unique URL `slug`.
- Optional description with bounded length.
- `is_active`.
- `has_public_landing_page`.
- Nullable `archived_at`.
- Nullable `created_by` and `updated_by` user identifiers.
- Timestamps.

Archive is distinct from inactive:

- Inactive group is retained and editable by platform administrators but absent from discovery/filter options.
- Archived group is read-only outside restoration/support workflow and absent from discovery.
- Archive does not delete memberships or audit history.
- Slugs remain reserved while group exists, including inactive/archived records.

Use explicit query scopes such as active/discoverable only where they improve consistency; never use a global scope that prevents platform administration from loading inactive/archived records.

### Lodge Group Memberships

Add many-to-many `lodge_group_memberships`:

- `lodge_group_id`.
- `lodge_id`.
- Nullable `created_by` user identifier.
- Timestamps.
- Composite primary/unique key on group and lodge.
- Foreign keys with restrictive or deliberate cascade behavior matching historical retention policy.

Membership carries no permission, tenant ownership, visibility, or consent fields. Do not add group roles, group admins, inherited settings, or resource-sharing flags.

Add Eloquent relationships on `Lodge`, `LodgeGroup`, and `LodgeGroupType`. Controllers must not serialize these models directly into public or protected responses.

## Discovery and Authorization Contracts

### Shared Group Filter Contract

Build one reusable group-filter resolver/query helper that:

1. Validates group existence and lifecycle for viewer context.
2. Returns lodge IDs belonging to active WorkingTools lodges in group.
3. Applies those IDs only as additional narrowing predicate.
4. Never removes existing visibility, eligibility, consent, or permission predicates.

Public contexts accept only active groups with public landing pages. Protected directory/ritual contexts may use any active, non-archived group. Inactive, archived, missing, or unauthorized group identifiers produce validation failure or 404 consistently; they never silently broaden to all lodges.

Do not cache authorized result sets in Phase 9. Small reference/filter lists may be cached only when group/lodge lifecycle writes synchronously invalidate them and cache keys distinguish public from authenticated context.

### Public Lodge Directory

Create a public read service with explicit lodge projection:

- ID or safe slug needed for links.
- Name and number.
- City, state, and jurisdiction.
- Meeting location.
- Meeting schedule.
- Public email and phone.
- Safe logo derivative/URL under existing media rules.
- Published homepage URL or null.
- Publicly discoverable active group summaries.

Base query begins with active lodges. It does not depend on published CMS content or group membership.

Support bounded filters:

- Publicly discoverable group slug/ID.
- Active group type.
- Lodge name/number text.
- City.

Use stable ordering by normalized lodge name/number and ID. Paginate server-side. Exact street/geospatial proximity search is not required.

Do not expose physical/mailing fields beyond explicitly selected public projection. Do not expose administrators, membership counts, feature flags, internal status history, unpublished site state, storage paths, or group records not public-discoverable.

### Regional Event Discovery

Create shared `EventDiscovery`-style read service over materialized occurrences. It must accept nullable viewer and return only explicit event cards.

Base predicates:

- Owning lodge active.
- Event published and not archived/cancelled.
- Occurrence scheduled and within bounded requested date range.
- Effective occurrence start/end/location/title respected.
- Current viewer passes hardened `EventEligibility::canView` equivalent expressed without N+1 per-row queries.

Filters:

- Publicly available group for anonymous viewer; active group for authorized viewer where protected events may appear.
- Lodge.
- Active event category.
- Date range with documented maximum.
- Visibility.
- Required qualification.

Projection includes owning lodge identity and link to existing owning-lodge occurrence page. Do not duplicate reservation/reminder mutations under regional routes.

Provide list and calendar representations from same backend result contract. UI transformation may arrange occurrences by day/month, but client must not receive occurrences viewer cannot access.

Use existing occurrence-extension scheduler/horizon. Regional GET requests should not materialize unbounded recurrence data. If existing lodge event GET behavior materializes on read, isolate or harden it separately without allowing public regional requests to amplify writes.

### Event Eligibility Hardening

Before regional aggregation, update shared event eligibility so:

- Non-public visibility requires approved and email-verified user.
- User links to live, non-deleted, non-merged, non-deceased person.
- Qualifying membership has no end date, active status, and belongs to active lodge.
- Event-owning lodge is active.
- Lodge-only event requires qualifying membership in owning lodge.
- Masons-only event may use qualifying active membership in any active WorkingTools lodge.
- Qualification hierarchy remains EA < FC < MM < PM.
- Past Master remains derived separately from degree.
- Cross-lodge reservation flag affects cross-lodge reservation, not basic Masons-only visibility or reminder eligibility.
- Platform/lodge administrative permission does not create attendee eligibility.

Apply same contract to list, detail, ICS/calendar, reservation, reminder, volunteer-adjacent reads where applicable. Add regression tests before adding regional controllers.

### Cross-Lodge Member Directory Extension

Extend `DirectoryAccess`, not controller-side queries.

For WorkingTools-wide audience:

- Allow empty query.
- Add optional group filter through active memberships in active lodges.
- Continue subject scope requirement `participating_lodges`.
- Deduplicate by person.
- Eager-load only active WorkingTools affiliations needed for projection.
- Return affiliations as safe arrays: lodge ID, name, number, slug.
- Preserve hidden-field-safe query, degree filter, pagination, direct-detail, and private-photo authorization.
- Apply private/no-store cache headers and authenticated enumeration throttling.

Requester paths:

- Ordinary user: existing explicit requesting active lodge, approved/verified account, eligible linked person, active membership, and `directory.view` there.
- Platform administrator: may use cross-lodge directory without membership. Still receives ordinary privacy-filtered projection, never `PersonAccess` administrative fields.

Own-lodge audience may omit affiliation list because requesting context already identifies lodge, or show only requesting lodge when UI consistency requires it. Cross-lodge detail/list must use identical affiliation projection.

### Ritual-Assistance Group Filter

Extend `RitualAssistanceAccess::applyFilters` with group filter:

- Candidate must have active membership in active lodge belonging to selected active group.
- Existing ritual scope, proficient/willing part, availability, directory-contact privacy, and requester authorization remain mandatory.
- Group filter does not change returned affiliations: continue showing all active WorkingTools affiliations permitted by ritual consent.
- Deduplicate person when several memberships match.
- Direct detail rechecks base visibility; submitted group does not authorize detail.

Platform administrators do not gain ritual-assistance browsing without existing ritual requester requirements unless separately approved later. Confirmed platform-admin exception applies to general member directory only.

### Group Landing Pages

Public route resolves active, non-archived group with `has_public_landing_page = true` by slug.

Anonymous projection:

- Group name, type, and description.
- Public member-lodge cards.
- Upcoming public event cards.
- Links to filtered public lodge directory and event calendar.

Authenticated eligible viewer may additionally receive protected events through same hardened event discovery service. Member-directory and ritual links may be displayed only when viewer can open those tools; no member or ritual records are embedded in public group props.

Set appropriate cache policy. Anonymous output may use short public caching only if group/lodge/event lifecycle changes invalidate it correctly. Viewer-dependent output must be private and vary safely; simplest first implementation is private/no-store for authenticated responses.

## Routes and Controllers

Use thin controllers and form requests. Suggested route surface:

| Method | Route | Purpose |
|---|---|---|
| GET | `/lodges` | Public WorkingTools lodge directory |
| GET | `/events` | Public/member-aware regional event discovery |
| GET | `/groups/{lodgeGroup:slug}` | Public discoverable group landing page |
| GET | `/platform/lodge-groups` | Platform group management |
| POST | `/platform/lodge-groups` | Create group |
| PUT | `/platform/lodge-groups/{lodgeGroup}` | Update group |
| PATCH | `/platform/lodge-groups/{lodgeGroup}/archive` | Archive group |
| PATCH | `/platform/lodge-groups/{lodgeGroup}/restore` | Restore group when supported |
| PUT | `/platform/lodge-groups/{lodgeGroup}/lodges` | Replace/synchronize membership set |
| GET | `/platform/lodge-group-types` | Group-type management |
| POST | `/platform/lodge-group-types` | Create group type |
| PUT | `/platform/lodge-group-types/{lodgeGroupType}` | Update group type |
| PATCH | `/platform/lodge-group-types/{lodgeGroupType}/status` | Activate/deactivate group type |
| DELETE | `/platform/lodge-group-types/{lodgeGroupType}` | Delete an unused group type |

Extend existing directory and ritual query routes with optional `group` parameter. Avoid new mutation routes for event reservation/reminder flows.

Route ordering must prevent public `/lodges` from colliding with lodge-scoped parameter routes. Public binding by slug and platform binding by ID must fail closed for inactive/archived records according to context.

All platform group/type mutations use `platform-admin` middleware, validated requests, transactions, and audit records.

## UI and Navigation

### Public Discovery

Add top-level public discovery navigation where existing public shell permits:

- Lodges.
- Events.
- Discoverable group links or group filter access.

Lodge directory supports responsive cards/list, search, city/type/group filters, pagination, published-site link, and clear no-homepage state.

Event discovery supports:

- List view.
- Calendar-oriented view using existing frontend dependencies where possible.
- Date, lodge, group, category, visibility, and qualification filters appropriate to viewer.
- Clear owning lodge identity.
- Link to existing event detail/action flow.
- Explanation that signing in may reveal additional eligible Masonic events.

Do not expose protected event counts or filter facets to anonymous users.

### Platform Administration

Add platform navigation for “Lodge groups.” UI supports:

- Group list with type, active state, public-page state, lodge count, and archive state.
- Create/edit form.
- Multi-select lodge membership management including lodges in multiple groups.
- Archive confirmation and optional restoration.
- Group-type CRUD with safe inactive-value behavior.
- Links to preview public landing page when discoverable.

Use existing platform form/layout conventions. Validation failures preserve inputs and identify slug/name/type conflicts clearly.

### Member Directory and Ritual Assistance

- Label cross-lodge audience “WorkingTools lodges.”
- Allow blank WorkingTools-wide member browse.
- Add lodge-group selector.
- Show active lodge affiliations on cross-lodge member cards/detail.
- Add lodge-group selector to ritual assistance.
- Explain filters narrow consented results and do not imply assignment or group membership authority.

## Authorization Matrix

| Action | Anonymous | Approved member with required lodge permission | Platform administrator |
|---|---:|---:|---:|
| Browse active lodges | Yes | Yes | Yes |
| View public group page | Yes | Yes | Yes |
| Browse public events | Yes | Yes | Yes |
| Browse eligible protected events | No | Yes, current event eligibility | Only when attendee eligibility passes |
| Browse cross-lodge member directory | No | Yes, `directory.view` and requester eligibility | Yes, without lodge membership |
| See directory affiliations | No | Yes, visible subjects only | Yes, visible subjects only |
| Browse ritual assistance | No | Yes, existing `ritual.search` eligibility | No bypass |
| Manage groups/types/memberships | No | No | Yes |

Platform administration still grants ordinary event/lodge administration through existing policy where defined, but never creates protected-event attendee eligibility.

## Audit, Logging, Caching, and Security

Audit:

- Group created/updated/activated/deactivated/archived/restored.
- Group membership synchronized, with bounded before/after lodge IDs.
- Group type created/updated/activated/deactivated.
- Lodge meeting schedule changed through existing lodge audit path.

Never audit directory query text, returned person lists, private contact values, or full event audiences unless existing security policy explicitly requires minimal metadata.

Security requirements:

- Public DTOs and Inertia props never contain protected group, member, ritual, event, contact, storage, or administrative fields.
- Group IDs/slugs never bypass base authorization.
- Hidden contact values cannot influence directory matching or facets.
- Disabled lodge status removes lodge as discovery source and eligibility source immediately.
- Direct URLs rerun lifecycle and viewer checks.
- Authenticated directory and ritual responses use private/no-store cache policy.
- Public results use only safe image derivatives and published site links.
- Pagination, query lengths, date ranges, and page sizes are bounded.
- Rate-limit blank-query cross-lodge directory browsing and repeated protected searches.
- Avoid N+1 authorization calls. Express bulk eligibility in deliberate query services and verify equivalence against single-record policy tests.

## Lifecycle Behavior

- Disabling/locking lodge removes it, its group-filter presence, and its discoverable events immediately; group pivot remains.
- Reactivating lodge restores discovery according to current group/resource state.
- Removing lodge from group removes it only from that group’s filtered results; platform-wide discovery stays unchanged.
- Deactivating group removes it from all filters/pages without modifying lodge/resource records.
- Archiving group preserves membership/audit history and reserves slug.
- Deactivating group type prevents new selection but does not invalidate existing group.
- Ending subject membership removes that affiliation immediately. If no active affiliation remains, subject disappears from directory and ritual discovery.
- Person merge reuses existing directory/ritual merge contracts; group data contains no person IDs.
- Event cancellation/archive/occurrence move appears immediately according to materialized occurrence rules.
- Publishing/unpublishing lodge homepage changes directory website link without changing lodge inclusion.

## Automated Test Requirements

### Unit and Domain Tests

- Group lifecycle and public/protected visibility predicates.
- Group filter always narrows pre-authorized lodge/person/event sets.
- Lodge public projection and homepage-link selection.
- Event eligibility hardened account/person/membership/lodge/qualification matrix.
- Directory affiliation projection excludes administrative membership fields.
- Empty-query cross-lodge directory pagination and deduplication.
- Ritual group filter preserves all existing consent/willingness/privacy rules.

### Database Tests

- Group/type constraints, unique keys/slugs, active/inactive history.
- Many-to-many membership uniqueness and one lodge in multiple groups.
- Archive retention and slug reservation.
- Inactive type retained by existing group.
- Migration refresh and rollback on PostgreSQL.
- Query plans/indexes for group membership, lodge discovery, upcoming occurrences, directory group filtering, and ritual group filtering.

### Laravel Feature Tests

Use at least Lodges A, B, C, ungrouped Lodge D, disabled Lodge E, and overlapping groups.

Cover:

- Anonymous public lodge directory includes all active lodges and no disabled lodge.
- Lodge without group and lodge without homepage still appear; homepage link omitted only when unpublished.
- Meeting schedule public projection and validation.
- Public group filters expose discoverable groups only.
- Authenticated protected filters may use active non-public groups.
- Anonymous calendar sees public occurrences only.
- Approved eligible Mason sees allowed Masons-only occurrences.
- Lodge-only and qualification failures remain hidden.
- Disabled owning/membership lodge cannot supply event visibility.
- Reservation/reminder routes retain Phase 4 behavior from regional links.
- Cross-lodge member directory allows blank browse, paginates, rate limits, and shows safe active affiliations.
- Hidden/own-lodge-only subjects remain excluded cross-lodge.
- Group-filtered directory returns matching active affiliations without duplicates.
- Platform administrator directory exception works without membership but cannot see hidden subject/fields.
- Ritual group filter cannot restore hidden/unwilling/ineligible subject.
- Group changes take effect on next request.
- Direct group/event/person/photo identifiers cannot bypass visibility.
- Public props/HTML contain no protected member, ritual, event, or contact data.

### Playwright Critical Path

Cover:

1. Platform administrator creates group types and overlapping groups.
2. Assign lodge to several groups and publish one group landing page.
3. Anonymous visitor browses all active lodges, filters group, sees meeting schedule, and follows published homepage.
4. Anonymous visitor views group page and public regional events only.
5. Approved member signs in and sees additional eligible Masons-only event.
6. Member browses blank WorkingTools-lodge directory, filters by group, and sees active affiliations.
7. Platform administrator without membership browses same privacy-filtered directory.
8. Member filters ritual assistance by group; hidden/unwilling candidate remains absent.
9. Platform administrator removes/disabled lodge and browser-visible discovery updates.

Test mobile/desktop, keyboard controls, focus/error behavior, light/dark themes, empty states, pagination, and calendar/list switching.

### Required Gates

Run narrow checks first, then:

- `php -l` for changed PHP files.
- Focused Phase 9 and regression tests.
- `vendor/bin/pint --test`.
- `php artisan test`.
- `npm run typecheck`.
- `npm run typecheck:e2e`.
- `npm run lint`.
- `npm run build`.
- `composer audit`.
- `npm audit --audit-level=low`.
- `git diff --check`.
- Route and schedule inspection.
- Docker/PostgreSQL migration refresh/rollback where required by repository test protocol.

## Detailed Implementation Work Plan

### P9-01 Eligibility Hardening and Contract Tests

- Lock current Phase 4 visibility/reservation/reminder behavior in tests.
- Harden account, person, active-lodge, and active-membership checks.
- Reuse one eligibility contract across event detail and new aggregate discovery.
- Verify no administrative permission becomes attendee eligibility.

Exit: full protected-event matrix passes before regional event queries exist.

### P9-02 Group Schema, Models, Seed, and Platform Services

- Add group types, groups, memberships, indexes, relationships, and meeting schedule.
- Seed group types idempotently.
- Add transactional platform services, validation, lifecycle rules, and audit events.
- Add domain/database tests.

Exit: overlapping groups and lifecycle behavior work without changing tenant authorization.

### P9-03 Platform Group and Group-Type CRUD UI

- Add platform routes/controllers/forms/pages/navigation.
- Implement group/type CRUD, membership synchronization, archive/state controls, and previews.
- Test authorization, validation, conflict behavior, and audit output.

Exit: platform administrator can manage complete group catalog without direct DB work.

### P9-04 Public Lodge Directory and Group Landing Pages

- Add public lodge read service and projection.
- Add public directory filters/pagination and published-homepage resolution.
- Add group landing projection with lodge cards and safe public links.
- Add responsive UI and public leakage tests.

Exit: anonymous visitor can discover every active WorkingTools lodge and public group without protected data.

### P9-05 Regional Event Discovery

- Build aggregate occurrence query on hardened eligibility.
- Add bounded public/member filters, list/calendar UI, and owning-lodge links.
- Preserve existing reservation/reminder endpoints.
- Add recurrence, cancellation, disabled-lodge, qualification, and N+1 regression tests.

Exit: anonymous/member viewers receive exactly eligible occurrence sets.

### P9-06 Member Directory Extension

- Add empty-query browsing, group filter, affiliations, and platform-admin requester exception in `DirectoryAccess`.
- Update list/detail/photo authorization and projection consistently.
- Add pagination/rate limiting/private-cache behavior.
- Update UI copy/cards/filter and Phase 6 regression tests/docs.

Exit: all directory surfaces enforce same privacy predicate and safe affiliation projection.

### P9-07 Ritual Group Filter

- Extend validation/query service/UI with group filter.
- Preserve requester gates, consent, proficiency, willingness, availability, contact privacy, and deduplication.
- Add direct-ID and multi-membership attacks.

Exit: group narrows ritual candidates and never expands visibility.

### P9-08 Lifecycle, Performance, Documentation, and Final Gate

- Verify group/lodge/type/event/person lifecycle transitions.
- Inspect indexes and query counts with representative multi-lodge data.
- Finish terminology updates and cross-cutting architecture/domain/authorization/tenancy docs.
- Run complete required gates and manual acceptance.

Exit: deployable Phase 9 slice, clean validation, no unresolved privacy/tenancy conflict.

## Dependency and Parallelization Map

```text
P9-01 Event eligibility hardening ───────────────┐
                                                ├─> P9-05 Event discovery ──┐
P9-02 Group schema/services ─> P9-03 Admin UI ──┤                         │
              │                                 ├─> P9-04 Public discovery ┤
              ├─────────────────────────────────┼─> P9-06 Directory ───────┤
              └─────────────────────────────────┴─> P9-07 Ritual ──────────┤
                                                                           └─> P9-08 Final gate
```

After P9-01 and P9-02 contracts stabilize, public discovery, directory, and ritual work may proceed in parallel if agents own separate files and coordinate shared route/model changes.

## Agent Handoff Contract

Every package handoff must state:

- Files/migrations/routes changed.
- Locked decisions implemented.
- Focused tests added and exact results.
- Remaining prerequisites and risks.
- Any shared file another package must not edit concurrently.
- Confirmation that group predicates only narrowed existing authorized queries.

Stop and surface conflicts involving event eligibility, directory consent/affiliations, ritual consent, tenant ownership, disabled-lodge behavior, or platform-admin exceptions. Do not silently reinterpret them.

## Manual Acceptance Checklist

1. Add meeting schedule to active lodge and confirm public display.
2. Create Region, County, District, and Informal group types through platform UI.
3. Create Southwest Indiana and Warrick County groups.
4. Assign one lodge to both groups and leave another active lodge ungrouped.
5. Browse public lodge directory anonymously; see both active lodges and no disabled lodge.
6. Confirm unpublished-homepage lodge has no broken website link.
7. Filter lodge directory by public group.
8. Open public group landing page; inspect only public lodge/event data.
9. Browse public regional calendar; see public events only.
10. Sign in as eligible Mason; see permitted Masons-only event but no foreign lodge-only event.
11. Follow event and complete existing permitted reservation/reminder flow.
12. Browse WorkingTools member directory with blank query.
13. Confirm active affiliations display and private fields remain hidden.
14. Filter member directory by group; confirm deduplication and accepted affiliation inference.
15. Confirm hidden and own-lodge-only subjects remain absent cross-lodge.
16. As platform administrator without membership, browse same privacy-filtered directory.
17. Filter ritual assistance by group; confirm consent/willingness/privacy rules remain.
18. Remove lodge from group; confirm group-filtered results update while platform-wide results remain.
19. Disable lodge; confirm all active public/group discovery and eligibility sources disappear.
20. Archive group; confirm public page/filter and protected filters stop resolving it.

## Definition of Done

Phase 9 is complete only when:

- Public lodge directory includes every and only active WorkingTools lodge.
- Meeting schedule and published-homepage behavior match locked decisions.
- Platform administrators can manage group types, groups, lifecycle, and overlapping memberships through UI.
- Public/non-public group visibility behaves consistently across direct URLs and filters.
- Regional event discovery exactly matches hardened Phase 4 eligibility.
- Existing reservation/reminder behavior remains unchanged.
- Directory blank browse, group filtering, platform-admin exception, affiliation projection, privacy, deduplication, detail, and photo routes agree.
- Ritual group filtering never expands Phase 8 results.
- Disabled lodge and archived/inactive group transitions take effect without deleting history.
- Public props/HTML/logs/caches reveal no protected data.
- Focused, full, frontend, browser, security, and migration gates pass.
- Cross-cutting docs and user-facing terminology match implementation.

## Non-Goals

- Group membership as permission, tenant, event visibility, reservation, reminder, directory-consent, or ritual-consent boundary.
- Lodge opt-out from platform-wide directory while active.
- Public member directory or public member affiliation data.
- Group-specific administrators or delegated group management.
- Group CMS, custom domains, independent branding, newsletters, galleries, or communications.
- Automated geographic assignment, maps, distance search, or geocoding.
- Grand Lodge hierarchy synchronization or external data exchange.
- New event visibility levels or regional reservation/reminder records.
- Member-to-member or inter-lodge messaging.
- Search engine/index service, offline directory sync, bulk directory export, or printable regional roster.
- Renaming stored `participating_lodges` enum/API values in this phase.
