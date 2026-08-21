# ADR 0005: Separate Person Identity from Lodge-Owned Membership Data

- Status: Accepted
- Date: 2026-08-21

## Context

One human may belong to multiple lodges, may have family records without an account or membership, and may use one global login. Copying identity/contact data into each lodge membership would create conflicting people and make account linking unreliable. Treating all person-related information as globally visible would expose lodge-private membership, notes, and family information.

## Decision

Keep one global person identity and optional one-to-one user link. Store lodge-specific membership data on a membership with explicit lodge ownership. Shared person identity/contact changes are allowed only through an authorized active membership relationship and are globally visible to other authorized lodges.

Model family relationships as connections between people with an explicit lodge owner for provenance. Any lodge with an active member at either endpoint may view the relationship. A lodge may edit it only when a qualifying endpoint has an active membership in that lodge and names that lodge number as primary. Both lodges may edit when different endpoints independently qualify. This is controlled administrative sharing, not directory visibility.

Model current officers as lodge-owned assignments from a membership to a platform-owned position reference, with one current assignment per lodge position. Assignments are public by default, while email and phone require explicit opt-in and address remains private. Track Past Master service separately as repeatable lodge/person/year records; track Award of Gold explicitly on the lodge membership.

Account access remains user-and-role based. Membership, degree, family relationship, and officer position do not implicitly grant permissions. Officer assignment/removal prompts for an optional corresponding role change, but lodge access still requires an explicit role assignment decision.

Manual person merge is platform-admin only, transactional, conflict-aware, and audited. Normal writes enforce normalized email uniqueness and never merge on name alone.

## Consequences

- Shared contact corrections are not duplicated across memberships.
- Every person query must establish an authorized lodge relationship before exposing data.
- Lodge-specific notes, memberships, current officers, and Past Master history remain tenant-isolated. Family relationship access is the explicit endpoint-derived exception.
- Updating shared person data has cross-lodge effects and requires clear UI language and auditing.
- Membership ending and lodge-access revocation remain separate operations.
- Public officer rendering requires a narrow explicit projection rather than serializing person or membership records.
- Relationship writes require current primary-membership checks and may be authorized by a lodge other than the provenance owner.
- Merge logic must handle same-lodge membership, relationship, officer-history, and account-link conflicts atomically.
