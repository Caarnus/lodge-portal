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
