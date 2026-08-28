# Phase 8 — Ritualist Program

## Outcome

Every approved, verified user linked to an active member can privately track ritual work, learning interest, proficiency, willingness to assist, performances that qualify for Ritualist Program credit, and broad recurring availability. The portal calculates the member's current point total from active reference data, records automatically achieved Ritualist levels durably, and lets authorized members discover willing ritual assistants in their own lodge or across all active hosted lodges.

Ritual proficiency and program credit are separate facts. A brother may know a part and be willing to perform it without claiming points. Points begin only when he self-reports that he performed the part from memory in an open lodge. The platform records that honor-system statement without testing, certification, lodge approval, or Grand Lodge verification.

Cross-lodge ritual discovery is independently opt-in. It does not depend on general directory scope. When a brother explicitly chooses participating-lodge ritual visibility, the ritual result may identify all of his active hosted-lodge affiliations. This is a deliberate, ritual-specific exception to Phase 6's rule that general cross-lodge directory results omit lodge affiliations. Email and phone remain subject to the brother's existing directory field choices.

## Repository Baseline and Scope

Implementation starts from the repository after Phase 7:

- `Person` is the canonical global identity. Ritual settings, proficiency, availability, and achievements are person-owned and must not be duplicated by membership or lodge.
- An active membership means a membership with no end date, the platform reference status key `active`, and an active owning lodge. Existing degree references are platform-owned and establish EA, FC, and MM ordering.
- Phase 6 provides approved/verified account gates, active-lodge requester authorization, person-wide directory privacy, explicit field projection, dashboard composition, and the initial meaning of participating lodges: all active lodges hosted by the platform. Phase 8 reuses those foundations but has independent ritual visibility.
- There is no regional-group model yet. Phase 8 does not pull Phase 9 regional organization forward. Participating-lodge ritual search spans all active hosted lodges.
- Built-in lodge roles and permissions are synchronized through `LodgeRoleCatalog`; officer assignment never grants application permission.
- `PersonMergeService` already resolves person-owned privacy conservatively and moves person-dependent records transactionally. Ritual rows must be integrated explicitly.
- PostgreSQL is the production database. SQLite behavior does not define checks, partial indexes, case-insensitive uniqueness, aggregate locking, or concurrent achievement insertion.
- The Initial Ritual Reference Catalog in this document is authoritative for the initial 41 part names, group keys, and point values. Referenced PDFs and public program descriptions provide context only and must not override it.

Before implementation, run the existing backend and frontend gates. A failing Phase 7 baseline is a prerequisite defect; do not weaken existing privacy, tenancy, merge, role, or directory tests to accommodate this work.

## Locked Product Decisions

- All active members may use the program. EA and FC members are not excluded, because they may learn or perform work appropriate to degrees they have received.
- Phase 8 does not enforce degree-based access to part names or prevent a member from tracking a part based on current degree. Degree is reference/search metadata, not an authorization boundary.
- Proficiency statuses are `not_known`, `learning`, and `proficient`. A missing proficiency row has the effective status `not_known`.
- `interested_in_learning` is independent of status. It is not a fourth proficiency status.
- `willing_to_assist` is independent of program credit but may be true only while status is `proficient`.
- `performed_for_credit` is a separate honor-system statement. It can be set true only through an explicit confirmation that the part was performed from memory in an open lodge.
- No performance date, performance count, candidate, event, lodge, witness, evaluator, attachment, or approval is recorded. A part earns its configured points at most once.
- Once validly set, `performed_for_credit` remains historical even if the member later changes current proficiency. The member may explicitly clear it to correct an error, but an already recorded level achievement is not silently deleted.
- Current points equal the sum of current point values for active point-bearing parts whose person record has `performed_for_credit = true`. Current proficiency is not an additional point predicate.
- Deactivating a category or part removes its points from current totals and removes it from assistance search and new tracking. Existing records remain historical.
- Editing a point value immediately changes every affected current total. Totals are derived and are never stored on a person.
- Initial automatic levels are Ritualist at 300 points, Senior Ritualist at 700 points, and Master Ritualist at 1,400 points.
- When a member reaches a level, the platform records it automatically. This is application recognition, not proof that a Grand Lodge representative issued or presented a pin.
- Achieved levels are durable. Later point-value, threshold, category, or part activation changes may lower the current total but never remove an achievement.
- Initial reference data contains only the 41 supplied point-bearing parts. The schema and platform UI support adding non-point parts later.
- Ritual visibility is one person-wide scope: `hidden`, `own_lodge`, or `participating_lodges`. It is not configured per part or membership.
- Effective default ritual visibility is `hidden`, including when the settings row is missing. New members do not appear in either own-lodge or cross-lodge assistance search until they opt in.
- `own_lodge` means every active lodge membership held by the person. `participating_lodges` includes own-lodge visibility and cross-lodge discovery from any active hosted lodge.
- General directory scope does not control ritual result inclusion. A person may participate in ritual discovery while hidden from the general directory.
- Participating-lodge ritual consent permits disclosure of the person's display name and all active hosted-lodge affiliations in ritual results. Disabled and historical lodge affiliations are omitted.
- Email and phone are returned only when their existing directory field flags permit them. Ritual search does not return mailing address, family data, profile notes, membership numbers, primary-lodge values, roles, officer history, or administrative fields.
- Availability is an optional person-owned set of weekday/daypart windows using morning, afternoon, and evening. It is informational, not a commitment, booking, reservation, assignment, or calendar entry.
- A short public availability note is visible only to authorized members who can see the person in ritual search. “Public” in this context never means anonymous web access.
- Personal ritual notes are always private and are never serialized to assistance search, lodge administration, audit payloads, logs, or analytics.
- Assistance search returns only active parts for which the subject is currently proficient and willing to assist. Every result labels proficiency as self-reported.
- Lodge administrators, officers, platform administrators, and Grand Lodge representatives cannot edit another person's progress, credit, willingness, availability, or visibility in Phase 8.
- No periodic reconfirmation prompt is required.
- Phase labels such as `PhaseEight`, `P8`, or `phase_08` must not appear in implementation filenames, class names, route names, database objects, or code comments.

## Domain Model

Every table below has timestamps unless explicitly described otherwise. Reference records are platform-owned. All other records are person-owned and contain no `lodge_id`.

### Ritual Categories

Add `ritual_categories`:

- `id`.
- Immutable stable `key`, unique and suitable for imports.
- Human-readable `name`.
- Optional `description`.
- Optional `masonic_degree_id` referencing the existing platform degree table.
- `sort_order`.
- `is_active`, default true.

Seed these categories in order:

| Key | Label | Degree |
| --- | --- | --- |
| `entered_apprentice` | Entered Apprentice | EA |
| `fellow_craft` | Fellow Craft | FC |
| `master_mason` | Master Mason | MM |
| `optional` | Optional and Special Work | None |

Category keys become immutable after creation. A category with parts cannot be deleted; it is deactivated. An inactive category makes all child parts effectively inactive without rewriting each part.

### Ritual Parts

Add `ritual_parts`:

- `id`.
- Indexed `ritual_category_id` foreign-keyed to the owning category.
- Immutable stable `key`, globally unique.
- Human-readable `name`.
- Optional `description`.
- `sort_order` within the category.
- `counts_toward_program`, default false.
- Nullable positive integer `point_value`.
- `is_active`, default true.

Database and request validation require:

- A point-bearing part has `counts_toward_program = true` and `point_value > 0`.
- A non-point part has `counts_toward_program = false` and `point_value = null`.
- Stable keys are normalized and unique; duplicate display names within one category are rejected case-insensitively.
- Referenced parts are never hard-deleted through the application. Deactivation preserves proficiency and achievement history.

The initial seed is an explicit idempotent PHP array derived from the Initial Ritual Reference Catalog below; production code does not parse an external repository data file at request time. Seeding inserts missing stable keys without overwriting later administrator edits.

### Program Levels

Add `ritual_program_levels`:

- `id`.
- Immutable unique `key`.
- `name`.
- Positive `point_threshold`.
- `sort_order`.
- `is_active`, default true.

Seed:

| Key | Name | Threshold |
| --- | --- | ---: |
| `ritualist` | Ritualist | 300 |
| `senior_ritualist` | Senior Ritualist | 700 |
| `master_ritualist` | Master Ritualist | 1,400 |

Thresholds are configurable platform reference data. Level deletion is prohibited after an achievement references it. Deactivation removes the level from future targets but does not hide historical achievements.

### Person Ritual Settings

Add `person_ritual_settings` as a one-to-one extension of `Person`:

- `person_id` as primary key and cascading foreign key.
- `visibility_scope`: `hidden`, `own_lodge`, or `participating_lodges`, default `hidden`.
- Nullable `public_availability_note`, trimmed and bounded to 500 characters.
- Nullable `updated_by` user identifier for support/audit provenance.
- Timestamps.

Missing settings are interpreted as hidden with no public note. Person creation may materialize the default row, but every read query must remain safe if a hook was skipped.

### Person Ritual Proficiencies

Add `person_ritual_proficiencies`:

- `id`.
- `person_id`.
- `ritual_part_id`.
- `status`: `not_known`, `learning`, or `proficient`.
- `interested_in_learning`, default false.
- `willing_to_assist`, default false.
- `performed_for_credit`, default false.
- Optional `first_marked_proficient_on` date.
- Optional private `notes` text.
- Timestamps.
- Unique `(person_id, ritual_part_id)`.

Foreign keys cascade when a person is hard-deleted and restrict deletion of referenced parts. Application validation additionally enforces:

- Setting `willing_to_assist` true requires current `proficient` status.
- Moving away from `proficient` automatically clears willingness but does not erase performed-for-credit history.
- Setting `performed_for_credit` from false to true requires current `proficient` status and an explicit confirmation input; later proficiency changes do not clear it.
- `first_marked_proficient_on` is optional, cannot be in the future, and is retained as historical context when status changes.
- Private notes are bounded to 2,000 characters and omitted from every non-self projection.
- A row that returns completely to effective defaults may be deleted only when it has no notes, proficiency date, or performed-for-credit history. Otherwise retain it.

### Availability

Add `person_ritual_availabilities`:

- `id`.
- `person_id`.
- `day_of_week` using ISO values 1 through 7.
- `daypart`: `morning`, `afternoon`, or `evening`.
- `is_enabled`, default true.
- Timestamps.
- Unique `(person_id, day_of_week, daypart)`.

The UI edits the complete 7-by-3 matrix, while the service upserts changed rows transactionally. Disabled rows may be retained for stable history or removed as an implementation detail; effective reads return only enabled windows. Matching means exact weekday and daypart overlap and never implies a specific hour.

### Durable Level Achievements

Add `person_ritual_level_achievements`:

- `id`.
- `person_id`.
- `ritual_program_level_id`.
- `achieved_at` timestamp.
- `point_total_at_achievement`.
- `level_name_snapshot`.
- `threshold_snapshot`.
- Timestamps.
- Unique `(person_id, ritual_program_level_id)`.

Achievements are append-only through ordinary application behavior. Snapshots preserve what was achieved if a level is renamed or its threshold changes. Foreign-key deletion is restricted. A person merge may consolidate duplicate achievements; hard person deletion cascades them.

## Initial Ritual Reference Catalog

The initial categories contain exactly the following authoritative values:

### Entered Apprentice — 340 available points

| Part | Points |
| --- | ---: |
| Opened Lodge on EA Degree | 15 |
| Worshipful Master 1st Section | 90 |
| E.A. Charge | 30 |
| E.A. Memory Lecture Initial | 85 |
| E.A. Lecture 2nd Section (slide #1) | 50 |
| E.A. Lecture 3rd Section (slide #2) | 70 |

### Fellow Craft — 610 available points

| Part | Points |
| --- | ---: |
| Opened Lodge on FC Degree | 15 |
| 1st Section Worshipful Master | 90 |
| F.C. Middle Chamber Lecture Traditional | 225 |
| F.C. Middle Chamber Lecture Abbreviated Version | 135 |
| F.C. Letter 'G' Lecture | 30 |
| F.C. Memory Lecture Initial | 85 |
| F.C. Charge | 30 |

### Master Mason — 685 available points

| Part | Points |
| --- | ---: |
| Opened Lodge on MM Degree | 15 |
| Worshipful Master 1st Section | 90 |
| M.M. Charge | 30 |
| M.M. Memory Lecture Initial | 85 |
| M.M. 2nd Section Lecture (slide #1) | 60 |
| M.M. 3rd Section Lecture (slide #2) | 60 |
| Senior Deacon (Conducts candidate to 3rd R) | 15 |
| 1st Ruffian | 30 |
| 2nd Ruffian | 30 |
| 3rd Ruffian | 15 |
| Sea Captain | 10 |
| 1st Fellow Craft | 75 |
| 2nd Fellow Craft | 30 |
| 3rd Fellow Craft | 20 |
| 4th Fellow Craft | 10 |
| 5th Fellow Craft | 10 |
| 6th Fellow Craft | 10 |
| Hiram King of Tyre | 20 |
| King Solomon | 30 |
| Wayfaring Man | 10 |
| Graveside Prayer | 30 |

### Optional and Special Work — 365 available points

| Part | Points |
| --- | ---: |
| Master Mason Bible Presentation | 60 |
| E.A. Apron Lecture | 30 |
| 3rd Ruffian Soliloquy | 30 |
| M.M. Optional Charge (Yonder Book) | 45 |
| Memorial Service | 90 |
| Past Masters Degree Initial | 90 |
| Grand Lodge Vault Ritual Review | 20 |

The initial catalog totals 41 parts and 2,000 available points: six Entered Apprentice, seven Fellow Craft, 21 Master Mason, and seven Optional and Special Work parts. Preserve the spelling and punctuation above. Stable keys may normalize those labels but must be explicitly declared and covered by a seed regression test.

## Point Calculation and Achievement Reconciliation

Add a `RitualProgress` domain service that owns all calculations. Controllers and Vue code never sum points.

The current total query includes a proficiency row only when:

1. `performed_for_credit` is true.
2. Its part is active.
3. Its category is active.
4. The part counts toward the program.
5. The part has a valid positive current point value.

The service returns:

- Current total.
- Credited active parts with current point values.
- Credited inactive/retired parts excluded from the total.
- Proficient non-point parts.
- Durable achieved levels.
- Highest achieved level.
- Next active unachieved level and remaining points, when one exists.
- A flag when current total is below the threshold snapshot of the highest achieved level because current reference data changed or credit was corrected.

Add a `RitualAchievementService` that inserts every active level at or below a person's current total. It runs in the same domain transaction after a performed-for-credit change. It also reconciles affected people after platform changes to part points, part/category activation, or level thresholds.

Reference mutation reconciliation must be deterministic and bounded:

- Part changes select only people with credit for that part.
- Category changes select people with credit for any child part.
- Level threshold/activation changes scan credited people in chunks.
- Use row locking plus the unique person/level constraint or conflict-safe insertion so concurrent requests cannot duplicate achievements.
- Point reductions and deactivations never delete achievements.
- Current totals are always queried from current data; reconciliation is not a total cache.

If scale later makes synchronous level reconciliation unsuitable, an idempotent command/job may supplement it, but Phase 8 must not introduce an eventual window in which a saved qualifying total can be reduced again without first recording the achieved level.

## Self-Service Progress and Availability

Self-service ritual routes require an approved, verified user linked to a non-deleted, non-merged, living person with at least one active membership in an active lodge. The user's selected lodge does not own the data and does not affect which progress is shown.

The personal dashboard groups active parts by configured category and order. For every part it shows:

- Name and optional description.
- Current point value or “Does not count toward program points.”
- Proficiency status.
- Interested-in-learning control.
- Willing-to-assist control, enabled only for proficient status.
- Performed-for-credit state with the required honor-system confirmation.
- Optional first-proficient date.
- Private notes.
- Inactive/retired marker where an existing historical row references inactive data.

Updates are per part and transactional. Submitted person IDs, point values, categories, totals, and achievement levels are ignored. Ownership comes only from the authenticated user's linked person. Changing one part recalculates the response and reconciles achievements server-side.

The availability editor shows 21 weekday/daypart toggles plus the public note. It states that availability is general, visible only according to ritual scope, and creates no commitment. Saving replaces the effective matrix in one transaction; partial request failure leaves the previous matrix unchanged.

The visibility editor explains:

- Hidden: no assistance-search listing, including within the member's own lodges.
- Own lodges: discoverable from each active lodge in which the member currently belongs.
- Participating lodges: discoverable by authorized members of any active hosted lodge; active hosted-lodge affiliations will be shown.
- Contact fields come from the existing directory email/phone choices and can be changed in Profile settings.

Switching to hidden takes effect on the next request. No search-result cache is introduced.

## Ritual Assistance Authorization and Search

Add a dedicated `RitualAssistanceAccess` service. It must not reuse `DirectoryAccess::visibleQuery()` because ritual scope and lodge-affiliation disclosure intentionally differ. It may reuse small active-membership and contact-projection helpers only if doing so cannot couple the two visibility scopes.

### Requester Eligibility

Every list, filter, and direct-detail request requires:

1. Approved and email-verified user.
2. Linked, living, non-deleted, non-merged person.
3. Active membership in the explicit requesting lodge.
4. `ritual.search` permission in that same lodge.
5. Active requesting lodge.

Platform-administrator status, `current_lodge_id`, a role in another lodge, or a submitted affiliation never replaces these checks. The built-in Administrator, Officer, and Member roles receive `ritual.search`; Non-member does not. Preserve custom roles and let lodge administrators assign the permission normally.

### Subject Eligibility

A candidate is searchable only when:

1. Living, non-deleted, and non-merged.
2. Has at least one active membership in an active hosted lodge.
3. Effective ritual visibility is not hidden.
4. For `own_lodge`, has an active membership in the explicit requesting lodge.
5. For cross-lodge treatment, has `participating_lodges` ritual scope.
6. Has at least one active matching part with status proficient and willingness true.

Directory scope is not tested for inclusion. Missing ritual settings mean hidden. Disabled lodges neither authorize requesters nor supply visible affiliations. A multi-lodge person is returned once.

### Search Contract

Support:

- Audience: requesting lodge or participating lodges; default requesting lodge.
- Active ritual category.
- Active ritual part.
- Degree metadata through the part/category association.
- Active lodge affiliation.
- Weekday.
- Daypart.
- Optional trimmed display-name query with a bounded maximum.
- Stable display-name/person-ID ordering and server pagination capped at 25.

Assistance results always enforce proficient status and willingness; these are not optional bypass filters. If weekday or daypart is supplied, both values are required and an enabled exact overlap must exist. No availability filter means members without availability rows may still appear.

The SQL/query contract filters before pagination and projects after authorization. It must not load the global people table and filter in Vue. Category, part, lodge, and availability filters cannot cause duplicate people. Result counts and pagination cannot include hidden or unwilling candidates.

### Result Projection

List results contain only:

- Opaque person identifier for the protected detail route.
- Display name.
- Active hosted-lodge affiliations: lodge ID, name, number, and public slug only.
- Matching active parts, each with category, name, self-reported label, and relevant proficiency update timestamp.
- Matching enabled availability windows and public availability note.
- Email when `show_email` is true in effective directory privacy.
- Phone when `show_phone` is true in effective directory privacy.
- Ritual-profile and availability update timestamps.

Do not return mailing address, profile photo, degree/member details, primary lodge, membership type/status/dates, roles, officer history, family data, private notes, learning interest, performed-for-credit state, points, totals, ranks, raw privacy settings, user IDs, or audit fields.

The direct detail route reruns requester and subject eligibility and returns the same allowlist, expanded to all active proficient-and-willing parts. A well-formed but invisible person returns 404. Malformed filters return 422. Unauthorized requester context returns 403. There is no public or token route.

## Platform Reference Management

Platform administrators manage categories, parts, and levels through one reference-data screen protected by `auth`, `verified`, `approved`, `admin-2fa`, and `platform-admin` middleware.

The screen supports:

- Add and edit category label, description, degree, order, and active state.
- Add and edit part label, description, category, order, point eligibility/value, and active state.
- Add and edit level label, threshold, order, and active state.
- Clear warnings showing how point, threshold, or activation changes affect current totals while preserving achieved levels.
- Read-only counts of affected progress rows before a material change.

Stable keys are chosen at creation, normalized server-side, and immutable afterward. Referenced records are deactivated, not deleted. Every write uses a form request plus `RitualReferenceService`, performs achievement reconciliation, and records an audit without copying private member data.

## Routes and Controllers

Use route-model binding but reauthorize every ownership and visibility chain in the domain service.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/ritual` | Personal ritual dashboard and progress |
| PUT | `/ritual/settings` | Visibility scope and public availability note |
| PUT | `/ritual/parts/{ritualPart}` | Upsert own progress for one active part |
| PUT | `/ritual/availability` | Replace own effective availability matrix |
| GET | `/lodges/{lodge}/ritual-assistance` | Authorized own/cross-lodge search |
| GET | `/lodges/{lodge}/ritual-assistance/{person}` | Authorized candidate detail |
| GET | `/platform/ritual-reference` | Platform reference-data screen |
| POST | `/platform/ritual-categories` | Add category |
| PUT | `/platform/ritual-categories/{ritualCategory}` | Edit/deactivate category |
| POST | `/platform/ritual-parts` | Add part |
| PUT | `/platform/ritual-parts/{ritualPart}` | Edit/deactivate part |
| POST | `/platform/ritual-levels` | Add level |
| PUT | `/platform/ritual-levels/{ritualProgramLevel}` | Edit/deactivate level |

Suggested controllers are `RitualDashboardController`, `RitualProgressController`, `RitualSettingsController`, `RitualAvailabilityController`, `RitualAssistanceController`, and platform-scoped `RitualReferenceController`. Controllers remain thin and serialize explicit arrays only.

Self-service and assistance routes do not require admin 2FA. They are ordinary member tools. Platform reference writes do require it.

## UI and Navigation

### Personal Ritual Dashboard

Add a first-class “Ritual” member navigation item independent of the active lodge. The page includes:

- Current point total, highest achieved level, and next target.
- A warning when current total is below a durable achieved threshold.
- Credited-part breakdown using current values.
- Grouped filterable part list with compact status/interest/willingness/credit controls.
- Clear honor-system confirmation before first claiming credit.
- Learning and proficient summaries.
- Retired historical parts, visually separated and excluded from totals.
- Availability matrix and public note.
- Ritual visibility selector with lodge-affiliation disclosure copy.
- Repeated “self-reported” and “not a certification” language where needed without overwhelming each row.

Updates should preserve scroll/filter state, show accessible success/error feedback, and work without hover. Mobile uses stacked part cards; desktop may use a dense table or grouped cards. All controls must work by keyboard in light and dark modes.

### Assistance Search

Add “Ritual Assistance” to lodge navigation when the active lodge grants `ritual.search`. The page includes:

- Own lodge / Participating lodges selector.
- Category, part, lodge affiliation, weekday, and daypart filters.
- Self-reported proficiency explanation.
- Result cards showing matching parts, active lodge affiliations, broad availability, freshness, and permitted email/phone.
- A clear statement that the member has not accepted an assignment and must be contacted separately.
- Empty states that distinguish no matches from invalid or unauthorized filters without leaking hidden counts.

Do not add assignment, booking, messaging, bulk export, contact-all, or roster-download controls.

### Platform Reference Screen

Add a platform navigation entry visible only to platform administrators. Use dialogs/forms consistent with event-category and lodge-reference administration. Changes with broad point impact require explicit confirmation showing the affected-record count.

### Dashboard Integration

Extend `DashboardService` with a bounded ritual summary for eligible linked members:

- Current point total and highest achieved level.
- Counts of learning, proficient, and credited active parts.
- Link to personal Ritual dashboard.
- For the active lodge, a Ritual Assistance link only when requester eligibility passes.

Dashboard data remains a backend read model; Vue does not reproduce point or authorization logic.

## Authorization Matrix

| Action | Platform admin alone | Active member | Lodge permission | Subject ownership |
| --- | ---: | ---: | --- | --- |
| View/edit own progress and availability | No | Yes | None beyond active membership | Authenticated linked person only |
| Change own ritual visibility | No | Yes | None beyond active membership | Authenticated linked person only |
| Search from explicit lodge | No | Yes, in that lodge | `ritual.search` in that lodge | Visible opted-in subjects only |
| View candidate detail | No | Yes, in that lodge | `ritual.search` in that lodge | Same search predicate |
| Edit another person's ritual data | No | No | None exists | Prohibited |
| Manage reference catalog | Yes | No | Platform-admin middleware | Platform-owned data |

Add `ritual.search` to `LodgeRoleCatalog`. Synchronization grants it to built-in Administrator, Officer, and Member roles and preserves custom role permission assignments. It does not add ritual editing authority. Administrative People visibility does not expose private ritual notes, credit, availability, or visibility controls.

## Audit, Logging, Caching, and Security

Record audits for:

- Ritual visibility changes.
- Public availability-note changes as `note_changed`, without storing note text.
- Availability matrix changes using bounded weekday/daypart keys.
- Proficiency, learning-interest, willingness, and performed-for-credit flag changes.
- Automatic level achievements with level and threshold snapshots.
- Category, part, point, activation, and level-reference changes with affected-person counts.
- Person-merge ritual conflict resolution.

Never put private notes, public note contents, contact values, search queries, full result lists, or broad member snapshots in audit metadata or logs. Validation exceptions must not dump submitted note bodies.

Use normal authenticated CSRF protection and throttle assistance search consistently with directory search. Do not introduce an external search index or result cache. Responses containing person/contact/availability data should use private/no-store behavior. Browser history and query strings should use reference IDs and filter values, not contact data.

## Person, Membership, Lodge, and Reference Lifecycle

- Ending one membership retains all ritual data. If another active membership remains, eligibility and affiliations are recalculated from those memberships.
- Ending the final active membership immediately removes the person from search and prevents self-service mutation while preserving data for later reactivation.
- Disabling a lodge prevents it from authorizing requesters and removes it from displayed affiliations. A multi-lodge subject remains eligible through other active lodges.
- Marking a person deceased, merging them, or soft-deleting them removes them from search immediately.
- Account unlink/revocation retains person-owned ritual data and prior consent. Without a linked eligible account, no one can self-edit it; a later safe relink restores access.
- Hard person deletion cascades settings, progress, availability, and achievements. Ordinary membership removal must never hard-delete the person or ritual history.
- Inactive parts/categories remain visible only in the owner's historical section. They are unavailable for new tracking and assistance search and contribute no current points.
- Non-point parts use the same progress and discovery model but never contribute points or achievements.

### Person Merge

Extend `PersonMergeService` under the existing transaction and locks:

- Survivor ritual settings win. If absent, create conservative hidden settings with an empty public note; never copy a more permissive source visibility setting.
- Move source-only proficiency rows to the survivor.
- For the same part on both people, merge status by `not_known < learning < proficient`, OR learning interest and performed-for-credit, retain willingness only if the merged status is proficient, and retain the sole non-empty private note/date.
- If both overlapping rows contain different non-empty private notes or conflicting first-proficient dates, reject the merge for explicit resolution rather than discard private data.
- Union availability slots, treating either enabled row as enabled.
- Union achievements by level, retaining the earliest achievement timestamp and its snapshots.
- Delete retired source ritual rows after consolidation and include only bounded resolution codes/counts in merge audit metadata.

After consolidation, recalculate the survivor's current total and reconcile any newly reached levels. Merge must not broaden visibility or contact projection.

## Automated Test Requirements

### Unit and Domain Tests

- Point total uses performed-for-credit, active category/part, point eligibility, and current value.
- Proficient without performed credit earns zero points.
- Performed credit remains counted after a later proficiency downgrade but willingness is cleared.
- Non-point and inactive parts are excluded.
- Multiple credited parts aggregate correctly.
- 300, 700, and 1,400 thresholds create the correct achievements.
- Achievements are idempotent and durable after point reduction, deactivation, threshold increase, and credit correction.
- Point increases, reactivation, and threshold reductions reconcile newly reached levels.
- Availability exact day/daypart matching and no-filter behavior.
- Effective missing settings are hidden.
- Search scope, active-membership, permission, willingness, and proficiency predicates.
- Directory email/phone flags are applied independently of directory scope.
- Result projection contains affiliations and excludes every private/administrative field.
- Merge rules, conflicts, conservative visibility, availability union, and achievement union.

### Database and Concurrency Tests

- Fresh PostgreSQL migration and rollback.
- Enum/check constraints, positive point rules, unique keys, person/part uniqueness, availability uniqueness, and achievement uniqueness.
- Referenced category/part/level deletion restrictions and person cascade behavior.
- Case-insensitive duplicate part names within a category.
- Two simultaneous credit claims cannot duplicate achievements.
- Reference updates and member updates cannot miss or duplicate a threshold crossing.
- Seed regression asserts all 41 labels, group assignments, values, counts, subtotals, 2,000 total, and three thresholds.
- Idempotent seed rerun does not overwrite administrator-edited values.

### Laravel Feature Tests

- Any active EA, FC, or MM member can open and edit only their own ritual dashboard.
- User without linked person, inactive/final-ended member, unverified, pending, deceased, merged, and deleted person denial.
- Status, interest, willingness, first-proficient date, private notes, and credit confirmation validation.
- Submitted person IDs, point totals, level IDs, and inactive part IDs are ignored or rejected.
- Private notes never appear in assistance props, detail, audit, logs, or validation output.
- Hidden default, own-lodge scope, and participating-lodges scope across Lodges A, B, and C.
- Cross-lodge discovery works even when general directory scope is hidden.
- Ritual scope alone does not reveal hidden email/phone.
- Participating-lodge results disclose all and only active hosted-lodge affiliations.
- Disabled lodge, ended affiliation, active-lodge switch, role in another lodge, and platform-admin-only attacks.
- Multi-lodge result deduplication before pagination.
- Part/category/degree/lodge/day/daypart/name filters and stable pagination.
- Unwilling, learning-only, inactive-part, inactive-category, deceased, merged, and no-active-membership subjects are absent from counts and results.
- Candidate direct URL returns 404 after visibility, willingness, membership, person, part, or lodge state changes.
- Platform reference CRUD authorization, stable-key immutability, impact confirmation, auditing, recalculation, and no hard deletion of referenced data.
- Self-service routes do not require admin 2FA; platform management routes do.
- Dashboard and navigation respect linked-person and explicit-lodge permissions.
- Existing general directory behavior remains unchanged, including its own cross-lodge affiliation omission.

### Playwright Critical Path

Use at least three active lodges plus one disabled lodge and distinct single-/multi-lodge members:

1. Member begins hidden and absent from own-lodge assistance search.
2. Member marks one part learning and interested in learning.
3. Member becomes proficient and willing; points remain unchanged.
4. Member confirms performed-for-credit; points update automatically.
5. Repeat across thresholds and verify automatic durable levels.
6. Configure availability and its public note.
7. Enable own-lodge visibility and verify own-lodge discovery only.
8. Enable participating-lodge visibility and verify another lodge sees matching parts, active affiliations, overlap, freshness, and only permitted contact fields.
9. Hide general directory scope and confirm ritual discovery remains while general directory discovery stops.
10. Remove willingness or ritual visibility and confirm immediate disappearance including direct detail.
11. Platform admin changes a point value and deactivates a part; current totals update while achieved rank remains.
12. Add a non-point part, track it, and verify search without point impact.
13. Exercise mobile/desktop, light/dark, keyboard, validation, and empty states.

### Required Gates

Run the narrowest focused tests during each package, followed by:

- `php -l` for changed PHP files.
- `vendor/bin/pint --test`.
- Focused Ritualist feature/domain tests.
- `php artisan test`.
- `npm run typecheck`.
- `npm run typecheck:e2e` when browser coverage changes.
- `npm run lint`.
- `npm run build`.
- Focused Playwright ritual-assistance specification.
- `php artisan route:list --except-vendor`.
- `composer validate --strict` and `composer audit`.
- `npm audit --audit-level=low`.
- `git diff --check`.

Warnings and failures are resolved, not silently suppressed. Database constraint and concurrency tests run against PostgreSQL.

## Manual Acceptance Checklist

1. Confirm the reference screen contains the exact 41 supplied parts and correct 2,000-point total.
2. Confirm level thresholds are 300, 700, and 1,400.
3. Add an active non-point part and confirm it is trackable/searchable.
4. Sign in as an active EA and mark an EA part learning and interested in learning.
5. Mark it proficient and willing without earning points.
6. Confirm the honor-system statement and mark it performed for credit; verify the current value is added once.
7. Toggle credit repeatedly and verify no duplicate points or achievements.
8. Reach each threshold and verify its level appears automatically.
9. Reduce a point value and deactivate a credited part; verify the current total falls and achieved levels remain.
10. Review credited, non-point, learning, proficient, and retired breakdowns.
11. Enter weekday/daypart availability and a public note; verify the no-commitment explanation.
12. Confirm the member is absent from all assistance search while ritual scope is hidden.
13. Select own-lodge visibility and find the member from each lodge where he has an active membership, but not a foreign lodge.
14. Select participating-lodge visibility and find him from another active hosted lodge.
15. Verify all active hosted-lodge affiliations and no inactive/historical affiliations are shown.
16. Enable email, hide phone in directory privacy, and verify ritual results show only email even if general directory scope is hidden.
17. Filter by category, part, lodge affiliation, and matching availability.
18. Verify learning-only, proficient-but-unwilling, and non-overlapping candidates do not match assistance criteria.
19. Disable cross-lodge ritual visibility and confirm list and direct detail stop exposing the member immediately.
20. Attempt requester-lodge, person, part, category, and affiliation identifier substitution across Lodges A, B, C, and a disabled lodge.
21. Confirm no workflow assigns, books, reserves, messages, exports, or certifies a brother.
22. Inspect props, HTML, logs, audits, and network responses for private notes, hidden contact values, membership details, point claims, and unrequested proficient parts.

## Detailed Implementation Work Plan

Complete and review one package before beginning a dependent package.

### Locked Implementation Map

Use these names unless an existing repository convention requires a small namespace adjustment:

- Enums: `RitualProficiencyStatus`, `RitualVisibilityScope`, and `RitualDaypart`.
- Models: `RitualCategory`, `RitualPart`, `RitualProgramLevel`, `PersonRitualSetting`, `PersonRitualProficiency`, `PersonRitualAvailability`, and `PersonRitualLevelAchievement`.
- Domain namespace: `app/Domain/Ritual`.
- Core services: `RitualProgress`, `RitualAchievementService`, `RitualAssistanceAccess`, and `RitualReferenceService`.
- Focused backend tests: `tests/Feature/RitualProgressTest.php`, `RitualAssistanceTest.php`, `RitualReferenceManagementTest.php`, and a bounded merge/lifecycle test.
- Browser coverage: `tests/Browser/ritual-assistance.spec.ts`.

Use one timestamped domain migration after the Phase 7 migration, named `create_ritual_proficiency_domain.php` or equivalent. Do not name it after a phase. Migration rollback proceeds in reverse dependency order.

All writes use form requests and domain services. Any departure from performed-credit calculation, durable levels, person ownership, conservative visibility defaults, explicit ritual affiliation consent, or directory contact-field enforcement requires a documentation amendment before implementation.

### P8-01 Schema, Enums, Models, Reference Seed, and Permission

Prerequisite: clean Phase 7 baseline.

Deliver:

- Domain migration, checks, indexes, enums, models, relationships, casts, and factories.
- Idempotent four-category, 39-part, and three-level reference seed with exact regression fixture.
- `ritual.search` permission and built-in role synchronization while preserving custom roles.
- Default-effective person settings and basic lifecycle relationships without HTTP behavior.

Tests and gate:

- Fresh PostgreSQL migrate/rollback, constraints, uniqueness, seed counts/subtotals/total, seed idempotency/non-overwrite, factories, role synchronization/custom-role preservation, focused model tests, Laravel suite, and Pint.

### P8-02 Progress Calculation and Durable Achievements

Prerequisite: P8-01.

Deliver:

- Server-owned current-total query and explicit progress projection.
- Credit confirmation rules and transactional achievement reconciliation.
- Reference-change impact selection and durable snapshot behavior.
- Concurrency-safe level insertion and audit events without private data.

Tests and gate:

- Proficiency-versus-credit separation, active/non-point rules, current value changes, all thresholds, point decreases, deactivation, correction, idempotency, concurrent threshold crossing, PostgreSQL tests, Laravel suite, and Pint.

### P8-03 Personal Progress, Settings, Availability, and UI

Prerequisites: P8-01 and P8-02.

Deliver:

- Self-service requests/controllers/routes for progress, visibility, and availability.
- Personal Ritual dashboard, grouped controls, credit confirmation, progress/rank presentation, retired parts, availability matrix, and privacy explanations.
- Member-global navigation and bounded DashboardService summary.

Tests and gate:

- Self ownership, active-member eligibility across degrees and multiple lodges, validation/state transitions, private-note handling, availability transactionality, inactive parts, dashboard/navigation, feature tests, typecheck, lint, build, and focused browser personal flow.

### P8-04 Assistance Access, Query, and Projection

Prerequisites: P8-01 and P8-03.

Deliver:

- `RitualAssistanceAccess` requester/subject predicates.
- SQL filters, deduplication, pagination, freshness data, active-affiliation projection, and directory-controlled email/phone.
- Protected list/detail routes with exact 403/404/422 behavior and no cache.

Tests and gate:

- Scope matrix, general-directory independence, active membership/lodge rules, permission context, willingness/proficiency, availability overlap, every filter, deduplication, hidden-field projection, direct URL changes, tenant attacks, focused feature tests, Laravel suite, and Pint.

### P8-05 Assistance and Platform Reference UI

Prerequisites: P8-02 and P8-04.

Deliver:

- Lodge-context assistance search/detail UI and conditional navigation.
- Platform category/part/level management with impact warnings and confirmation.
- Accessible responsive states, self-reported/no-commitment language, and no bulk contact/export behavior.

Tests and gate:

- Reference authorization/mutation/recalculation, search filter UX, affiliations/contact projection, visibility revocation, mobile/desktop/light/dark/keyboard coverage, typecheck, lint, build, and focused Playwright flow.

### P8-06 Merge, Lifecycle, Hardening, and Documentation

Prerequisite: all prior packages.

Deliver:

- Full `PersonMergeService`, membership, lodge, account, deceased, soft-delete, and reference-deactivation integration.
- Adversarial Lodge A/B/C/disabled-lodge browser and feature matrix.
- Reconcile architecture, domain model, authorization, tenancy rules, testing strategy, README, ADR index, and Phase 6 directory exception documentation with implemented behavior.
- Resolve all test, type, lint, build, route, IDE, and dependency-audit findings without suppression.

Tests and gate:

- Merge conflicts/consolidation, every lifecycle transition, full required gates, PostgreSQL refresh/rollback, route inspection, manual checklist, privacy/log/network review, and completed two-lodge plus cross-lodge attack matrix.

## Dependency and Parallelization Map

- P8-01 blocks all schema-dependent work.
- P8-02 begins after P8-01 and blocks any surface that displays totals or achievements.
- P8-03 begins after P8-02.
- P8-04 may begin after P8-01 once the settings/proficiency contracts from P8-03 are fixed; it must not concurrently change those contracts.
- P8-05 waits for both calculation and assistance contracts.
- P8-06 is the final integration gate.

For one agent, use numbered order. With multiple agents, do not parallel-edit the domain migration, reference seed, role catalog, Person merge, shared routes, dashboard, sidebar, ritual visibility predicate, point calculator, or achievement reconciliation without explicit file ownership and one integration owner.

## Agent Handoff Contract

Every package handoff must:

- Name the package and copy its exact deliverables/gate from this document.
- Require reading this specification, its Initial Ritual Reference Catalog, Phase 3 person ownership, Phase 6 directory privacy, ADRs 0005/0007, architecture, domain model, authorization, tenancy rules, and testing strategy.
- Identify verified prerequisite commits/contracts and PostgreSQL baseline results.
- State allowed files/directories and preserve unrelated dirty-worktree changes.
- Prohibit phase labels in implementation artifacts.
- Prohibit client-side point calculation or visibility filtering, raw model serialization, submitted person ownership, lodge-owned proficiency, admin editing of member ritual data, private-note exposure, stored point totals, and contact-field bypass.
- Require Lodge A/B/C plus disabled-lodge adversarial tests and exact 403/404 behavior.
- Require reporting any conflict with person ownership, directory contact flags, explicit affiliation consent, performed-credit semantics, durable achievements, role/assignment separation, or merge safety rather than silently changing it.
- Require focused tests plus package gates and warning resolution without suppression.
- Stop after the package gate and report changed files, migrations, tests/results, risks, and prerequisites for the next package.

## Definition of Done

Phase 8 is complete only when:

- Every package and final gate passes against PostgreSQL.
- The exact 41 initial parts, authoritative values, four groupings, 2,000-point total, and 300/700/1,400 levels are seeded and manageable.
- Proficiency, learning interest, willingness, performed credit, private notes, availability, and ritual visibility remain distinct person-owned facts.
- Current points derive only from current active point rules and performed-for-credit statements; no client or stored total can drift.
- Automatic achievements are concurrency-safe and survive later point/reference changes.
- Missing settings and new members are hidden from ritual assistance by default.
- Any eligible active member, regardless of degree, can maintain only his own data without lodge administration.
- Authorized search returns only proficient, willing, opted-in, active candidates and filters before pagination.
- Participating-lodge consent reveals active hosted-lodge affiliations while existing directory flags continue to protect email and phone.
- Private notes, point claims, totals, ranks, learning interest, and administrative membership data never enter assistance projections.
- Availability is broad informational overlap and cannot create an assignment, booking, reservation, notification, or commitment.
- Person merge, membership ending, lodge disablement, account changes, deceased/merged state, and reference deactivation preserve history while revoking access correctly.
- All manual acceptance items pass at mobile/desktop widths and light/dark modes.
- Cross-cutting documentation matches implementation and no implementation artifact uses a phase label.

## Non-Goals

- Testing, evaluating, witnessing, approving, or certifying proficiency.
- Recording individual performances, dates, candidates, lodges, witnesses, attachments, or repeated-performance points.
- Confirming Grand Lodge passport enrollment, fees, submissions, pin issuance, or presentation.
- Assigning a brother to ritual work, constructing degree teams, scheduling, calendar booking, confirmations, substitute requests, attendance, or automated dispatch.
- Email, SMS, push, in-app messaging, contact-all, exports, or notification campaigns for matches.
- Per-lodge or per-part visibility, availability, points, proficiency, or willingness.
- Anonymous/public ritual discovery or search-engine-visible profiles.
- Regional groups, district hierarchy, lodge opt-in controls, or Phase 9 regional event/lodge discovery.
- Member-created ritual parts or lodge-specific point schedules.
- Full performance history, endorsements, ratings, reviews, rankings, leaderboards, or competitive statistics.
- Import of legacy Newburgh ritual records; Phase 8 preserves stable person/reference keys needed for later migration work.
