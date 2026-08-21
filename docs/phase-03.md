# Phase 3 — People, Membership, and Lodge Administration

## Outcome

Each lodge can privately maintain people, lodge memberships, family relationships, officer assignments, account links, and lodge-scoped roles without duplicating a person who belongs to more than one lodge. Shared identity/contact changes are visible through every authorized membership relationship. Lodge-owned membership data remains isolated, while family relationships use explicit derived visibility and collaboration rules.

## Domain and Schema Scope

Phase 3 expands the minimal `people` record and introduces:

- `memberships`: one lodge-owned relationship between a person and a lodge, including type, status, degree, lodge/member numbers, milestone dates, explicit Award of Gold status, and lodge-specific notes.
- `past_master_terms`: repeatable lodge/person/year records for Past Master service, including service in multiple lodges or multiple years.
- `membership_types`, `membership_statuses`, and `masonic_degrees`: platform-owned reference values with stable keys, labels, ordering, active state, and a database-controlled default where applicable.
- `person_relationships` and `relationship_types`: lodge-owned observations connecting two people through a configured relationship and inverse relationship.
- `officer_assignments` and `officer_positions`: current lodge officer slots backed by platform-owned position reference data.
- Existing `users.person_id`: the optional one-to-one link between an authenticated account and a global person.
- Existing lodge roles, permission assignments, and user-role assignments, expanded for membership administration.

The expanded person record supports `legal_first_name`, `legal_middle_name`, `legal_last_name`, `legal_suffix`, `preferred_name`, normalized email, phone, mailing address, optional birth date, deceased state/date, and private profile-photo metadata. A derived display name is used in UI. The existing `name` value remains a temporary read fallback during migration and is not independently editable once structured fields exist.

Critical constraints include:

- A normalized non-null person email is globally unique.
- A user links to at most one person and a person links to at most one user.
- A person has at most one membership row per lodge; ending a membership preserves its history.
- Every membership, relationship, officer assignment, role assignment, note, and private file carries or derives a verified lodge owner.
- Membership reference keys are stable and are not inferred from display labels.
- Active membership status is the only initial database-configured membership default. Membership type and degree have no automatic default.
- Relationship endpoints cannot be the same person, and inverse/duplicate relationship edges are rejected.
- An officer assignment's membership and lodge must agree.
- Primary lodge is stored as a lodge number string and does not require a platform lodge foreign key.
- Ordinary deletion cannot remove a person while memberships, relationships, an account link, a current officer assignment, or Past Master history still requires that identity.

See [ADR 0005](decisions/0005-person-membership-and-lodge-ownership.md).

## Ownership and Visibility Rules

- `Person` identity and contact fields are person-owned, global data. They are never copied into each membership.
- A person is visible to an authorized lodge when the person has an active membership in that lodge or is an endpoint of a relationship whose other endpoint has an active membership in that lodge.
- An actor with the applicable people-management permission in any lodge where the person has an active membership may update shared identity/contact fields. The change is global and audited with the authorizing lodge.
- Membership fields and membership notes are owned by the membership's lodge and are editable only through that lodge's authorization.
- Family relationships are between people and retain an owning lodge for provenance. Any lodge with an active member at either relationship endpoint may view the relationship. A lodge may edit it only when an endpoint has an active membership in that lodge and that membership identifies the lodge's own number as the person's primary lodge number. If both endpoints qualify through different primary lodges, both lodges may edit the same relationship.
- Officer assignments are lodge-owned current-position records. Past Master years provide the retained historical record required by this phase. Public officer output is a deliberate projection, not general person-directory access.
- Profile photos accept the Phase 2 image formats and 25 MB/60-megapixel limits. They reuse queued orientation, normalization, and metadata removal but produce private profile derivatives rather than public website media.
- Active lodge context may select the UI scope, but it never supplies authorization.

## Person Records

Authorized users can create and maintain people without creating login accounts. The editor distinguishes:

- Shared identity/contact fields.
- Lodge-owned membership data.
- Lodge-owned notes and family relationships.
- Account-link state.
- Current officer assignments and Past Master service years.

Email matching is case-insensitive and normalized consistently with registration. Exact email matches reuse the existing person. Exact full-name matches are warnings for human review and never automatic merges.

Sensitive fields such as birth date, death date, notes, account-link state, and private photos are never included in public officer output. A member may view the authorized people list for the active lodge, but Phase 3 has no cross-lodge or public directory.

## Lodge Memberships

Each membership supports:

- Membership type: Initiation, Affiliation, Dual, or Honorary.
- Membership status: Active, Demitted, Suspended, Expelled, or Deceased.
- Degree: Entered Apprentice, Fellow Craft, or Master Mason.
- Primary lodge number and optional local member number.
- Explicit Award of Gold (50-year member) state. This is maintained directly because initiation dates do not account for suspended time.
- Zero or more Past Master service years for the person and lodge. These are separate records because service may span multiple years and lodges.
- Entered Apprentice, Fellow Craft, Master Mason, affiliation/joined, demit/withdrawal, and end dates.
- Lodge-specific notes.

Reference values are seeded into tables and may be activated, ordered, or relabeled without changing stable keys. Historical records continue to resolve inactive values. Active is the database-configured default membership status. Membership type and degree require an explicit value when known and are never guessed by application code. Validation rejects chronologically impossible milestone sequences when all relevant dates are known, while allowing unknown dates to remain null.

Ending or revoking a lodge membership does not delete the person, another lodge's membership, the global user account, family records owned by another lodge, or Past Master history. Lodge access is revoked independently by removing that lodge's user-role assignments.

## Family Relationships

Initial relationship types are spouse, child, parent, widow/widower, and guardian. Relationship types define their inverse behavior so the UI can present both sides consistently without creating contradictory records.

Relationship entry always identifies which person the selected type describes and previews the directional statement. Displays use complete statements such as “Taylor Example is Child of Jordan Example,” rather than an ambiguous type/name pair. Related non-members identify the active lodge member through whom they are visible.

Relationships:

- Connect person records, not membership rows.
- Carry an owning lodge for provenance, audit, and lifecycle purposes.
- Are visible to any lodge with an active member at either endpoint.
- Are editable by a lodge only when an endpoint's active membership in that lodge also names that lodge number as the person's primary lodge number.
- May therefore be edited by two lodges when both endpoints independently meet the primary-lodge rule.
- Retain spouse, child, widow/widower, and orphan-support context after a member's death.
- Do not grant account access, lodge membership, authorization, or directory visibility.
- Are audited when created, changed, or removed.

## Account Linking and Invitations

An authorized lodge administrator may invite a person whose normalized email is present and unambiguous. The workflow:

1. Reuses an unlinked user with the same normalized email or creates a pending/approved user according to the existing approval policy.
2. Refuses to relink a user or person already linked elsewhere.
3. Links the user and person transactionally.
4. Sends the existing password-setup/reset notification when account setup is needed.
5. Records the actor, authorizing lodge, user, and person in the audit trail.

Lodge administrators cannot manually link mismatched email identities. Ambiguous or conflicting links require platform-admin review and, where appropriate, a person merge. Revoking one lodge's access removes that lodge's role assignments without deleting the global account or person link.

## Person Merge

Manual merge is platform-admin only and requires an explicit source and survivor. Before confirmation, the UI shows field differences and relationship conflicts. The transaction moves compatible memberships, family relationships, Past Master history, notes, and the user link to the survivor; rejects unresolved same-lodge membership or account-link conflicts; records a detailed audit event; and retires the source identity without silently discarding history.

Imports are outside Phase 3, but future imports must use the same duplicate indicators: normalized email is a hard conflict, while an exact normalized full-name match is a review warning.

## Officers and Public Website Integration

Officer positions are platform-owned database reference values with stable keys, labels, public ordering, and active state. Each lodge position has at most one current assignment, and administrators can quickly select or replace the member occupying each position. Officer assignment history is not retained. Past Master service is maintained separately as repeatable lodge/person/year records.

The Phase 2 Officers placeholder becomes a feature-backed section. It resolves assignments for the public site's lodge only and renders assignments with `is_public` enabled, which is the default. Position and preferred/display name are public. Email and phone are hidden by default and require separate explicit opt-in fields; address is never public. It never queries authenticated active-lodge context or exposes notes, birth data, account state, family information, membership numbers, private dates, or private profile files.

If there are no current public assignments, the section retains an intentional empty state.

## Lodge Roles and Permissions

Initial built-in roles remain Administrator, Officer, Member, and Non-member. Masonic degree, membership type/status, and officer position are domain data and never authorization roles.

Lodges may create custom roles by selecting platform-defined permissions. They cannot create permission keys. Officer assignment does not silently grant application permissions. After an assignment is saved, a modal prompts an administrator to assign the lodge Officer role or another permitted role to the linked account. When an assignment is ended or removed, a similar modal offers to remove the role; it defaults to retaining access when the person has another current officer assignment. A member without a linked user cannot receive a role until invited and linked.

Add platform-owned permissions for:

- `people.view` — view people reachable through the assigned lodge.
- `people.manage` — create people and edit shared identity/contact fields when the lodge has an active membership relationship.
- `memberships.manage` — create and update memberships and lodge-owned notes for the assigned lodge.
- `relationships.view` — view family relationships made reachable through an active lodge member.
- `relationships.manage` — manage family relationships when the assigned lodge satisfies the primary-lodge edit rule.
- `officers.manage` — manage current officer assignments for the assigned lodge.
- `roles.manage` — create lodge roles and assign available roles for the assigned lodge, subject to administrator-escalation rules.

The built-in Administrator receives all Phase 3 lodge permissions. The built-in Officer receives `people.view`, `people.manage`, `memberships.manage`, and `relationships.view`, but not `relationships.manage`. Member receives `people.view` and `relationships.view`; Non-member receives no administrative permissions in this phase.

## Routes and UI

Authenticated lodge-scoped management includes:

- Searchable people/member list with clear membership status and account-link indicators.
- Create/edit person flow with shared-field warnings.
- Membership create/edit/end flow.
- Family relationship management.
- Account invitation/link status and lodge-access revocation.
- Current officer slot management and separate Past Master year maintenance.
- Officer role-assignment/removal prompts that remain separate from the officer assignment transaction.
- Separate lodge role-definition and searchable/paginated role-assignment screens.
- A deduplicated active-lodge selector and active-lodge-only navigation grouped separately from platform navigation.
- Platform-only duplicate review and person merge.

All management routes identify the target lodge and load the target person/membership/relationship/officer record before authorization. Person identifiers alone never establish lodge visibility. Search results are produced from an authorized lodge relationship, not from the global people table.

UI supports desktop and mobile layouts, keyboard operation, visible focus, useful labels, non-color-only status cues, and confirmation for merge, membership ending, relationship removal, role escalation, and access revocation.

## Audit, Notifications, and Jobs

Audit shared person changes, membership lifecycle changes, notes, relationships, officer assignments, role/permission assignments, invitations/account links, lodge-access revocation, and merges. Shared person changes record the lodge relationship used to authorize the action.

Account invitations reuse queued password-setup email. No recurring background job is required. Person merge is synchronous and transactional for Phase 3; a future large import may use a separately designed job.

## Automated Tests

Laravel tests cover:

- One person with memberships in two lodges and different roles in each.
- Person creation without an account and safe account invitation/linking.
- Case-insensitive email uniqueness and duplicate-name review warnings.
- Shared person updates from an authorized active-membership lodge and global/audit effects.
- Denial through unrelated, ended, or forged lodge relationships.
- Membership field ownership, reference defaults, inactive references, primary lodge numbers without tenant rows, chronological validation, and ending without cross-lodge deletion.
- Family relationship persistence, inverse display, duplicate/self-link rejection, owner provenance, active-member visibility, one-primary-lodge edit access, two-primary-lodge collaboration, and unrelated-lodge denial.
- Officer position references, one current assignment per lodge position, quick replacement, membership/lodge consistency, default-public assignment behavior, private-by-default contact fields, empty state, and isolation.
- Award of Gold state and multiple Past Master years across lodges.
- Officer role prompt behavior for linked/unlinked accounts, multiple current assignments, and explicit assignment/removal choices.
- Built-in/custom role behavior, permission escalation protection, multi-lodge assignments, and access revocation.
- Platform-only merge success, same-lodge/account conflict rollback, relationship deduplication, and audit contents.
- Direct URL, payload identifier, active-context, search, and public-section tenant-boundary attacks.

Playwright covers the critical browser path: create a person without an account; add memberships in two lodges; assign different roles; add spouse/child relationships; invite and link one account; assign a current officer; verify the public Officers section; switch lodges; revoke one lodge's access; and confirm unrelated-lodge denial.

## Manual Acceptance

Execute the master plan's twelve Phase 3 acceptance steps, then also:

1. Confirm every list/search and direct record URL enforces lodge reachability.
2. Confirm shared person edits warn that other authorized lodges will see the change.
3. Confirm membership notes never appear to another lodge; confirm family relationships are visible only to lodges with a related active member.
4. Confirm a primary lodge number can reference a lodge not hosted by the platform.
5. Confirm ending one membership leaves the person, account, other membership, and Past Master history intact.
6. Confirm officers are public by default, contact information is private by default, hidden assignments stay private, and output follows configured position order.
7. Confirm role assignment cannot grant a permission unavailable to the acting administrator.
8. Confirm merge conflict rollback leaves both people unchanged.
9. Exercise the complete flow at mobile and desktop widths using keyboard-only controls.

## Non-Goals

- Cross-lodge or member-facing directory.
- Member-controlled privacy settings or self-service profile editing.
- Grand Lodge or legacy-data imports.
- Ritual proficiency tracking.
- Events, recurrence, reservations, reminder subscriptions, or reminder delivery.
- Dues, payments, financial records, or Grand Lodge synchronization.
- Public family, contact, membership, or profile directory data beyond the explicit officer projection.
