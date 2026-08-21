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
- Feature definition/assignment: platform-defined functionality enabled per lodge. Phase 1 establishes the mechanism without premature module flags.
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

Shared person identity/contact data is updated globally only through an authorized active-membership relationship. Memberships, notes, relationship provenance, officer history, and role assignments retain a lodge owner. Family relationship visibility/editing follows its explicit active-member and primary-lodge collaboration rules instead of unrestricted sharing. The global people table is never an unscoped directory.

One person may have memberships in multiple lodges, but at most one membership per lodge. Ending one membership preserves the person, other memberships, account link, relationships, and history. Primary lodge is a membership string value containing a lodge number, not a required foreign key to a hosted lodge.

Manual person merge selects one survivor, moves compatible dependent records transactionally, rejects unresolved membership/account conflicts, retires the source, and records a platform-admin audit event. Email is a hard uniqueness signal; a matching name is review information only.
