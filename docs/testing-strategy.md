# Testing Strategy

## Layers

- Unit tests cover isolated domain rules and value objects.
- Laravel feature tests cover requests, policies, persistence, validation, mail, jobs, and tenant isolation.
- Playwright tests cover critical browser workflows against the Docker environment.

## Phase 1 Browser Path

Playwright must exercise platform-admin bootstrap/sign-in, creation of two lodges, assignment of separate and multi-lodge administrators, lodge switching, branding updates, blocked cross-lodge access, registration, email verification, pending status, approval/rejection, and configurable 2FA behavior.

## Isolation Pattern

Every lodge-owned capability creates Lodge A and Lodge B fixtures and tests allowed access, direct URL/identifier manipulation, request payload reassignment, and active-context mismatch.

Tests must run from a clean database through documented Docker commands. Browser-test artifacts such as traces and screenshots should be retained on failure but ignored by version control.

## Public Website Browser Path

Playwright must exercise two lodges with distinct branding and content; default-template application; page and nested-navigation creation; typed section editing and reordering; authenticated draft preview; publication and unpublication; hidden navigation entries; and isolated public rendering at desktop and mobile widths.

## Public Content Test Pattern

For each public CMS behavior, tests distinguish draft, published, archived, hidden, and unpublished records. Cross-tenant tests attempt direct page, version, navigation-parent, section, and media identifiers from Lodge B while authorized only for Lodge A.

Sanitization tests include scripts, inline event handlers, unsafe URL schemes, malformed rich text, and platform-only custom HTML. Publishing tests verify transactionality: validation failure leaves the prior published page and navigation unchanged.

Media tests use representative JPEG, PNG, WebP, HEIC, and HEIF fixtures, including files near the 25 MB and 60-megapixel limits. They verify MIME spoof rejection, private originals, orientation, bounded public derivatives, EXIF/GPS removal, failed processing, retry behavior, publication blocking, soft deletion, and cross-lodge ownership.

Public route tests resolve the lodge explicitly, reject disabled statuses, allow duplicate page slugs across lodges, and prove that a missing page never falls back to another lodge's content. Cache isolation and invalidation tests are required if public caching is introduced.

## People and Membership Browser Path

Playwright must create a person without an account, add the same person to two lodges, assign different roles, add spouse/child relationships with explicit direction, verify active-member visibility and primary-lodge editing from both qualifying lodges, invite and link one account, assign a current officer and choose a role in the modal, verify the public Officers section, switch lodge context, revoke access in one lodge, and confirm the other membership remains usable.

## Person-Ownership Test Pattern

Every person test distinguishes global person-owned fields from lodge-owned membership, note, relationship provenance, officer, and role data. Fixtures include spouses with different primary lodges, Lodge A membership, Lodge B membership, a related non-member, and unrelated Lodge C. Tests exercise list/search results, direct URLs, nested resource identifiers, payload reassignment, active-context mismatch, ended memberships, and disabled lodges.

Shared-field tests prove an authorized change through Lodge A is visible through Lodge B and produces an audit event naming Lodge A. Negative tests prove Lodge C cannot discover the person by identifier, exact email, exact name, search, relationship endpoint, or account link.

Relationship tests prove both qualifying primary lodges may edit one row, a non-primary membership lodge has view-only access, an unrelated lodge has no visibility, and eligibility is rechecked after membership changes. Merge tests verify transaction rollback for same-lodge membership and competing-account conflicts, dependent-record ownership after success, relationship deduplication, source retirement, and immutable audit contents. Public officer tests assert default-public name/position output, opt-in contact behavior, hidden assignments, and exclusion of private person/membership/family data from serialized props and HTML.
