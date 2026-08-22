# ADR 0007: Person-Owned Directory Privacy with Request-Scoped Projections

- Status: Accepted
- Date: 2026-08-22

## Context

The platform stores one global `Person` for a human who may hold active memberships in several lodges. Identity and contact values therefore have one canonical source, while degree, member number, notes, roles, officer terms, and family relationships retain lodge or membership ownership.

Phase 6 adds an authenticated member directory. A directory response cannot safely serialize a `Person`, `Membership`, or relationship model because the permitted fields depend on all of the following:

- The subject's directory scope and field choices.
- Whether the requesting lodge is one of the subject's active lodges.
- Whether the request is an ordinary member-directory request or a separately authorized administrative people request.
- Which membership-owned degree may be disclosed.

Copying privacy settings onto each membership would let the same person publish conflicting canonical contact values through different lodges. Reusing `people.view` for ordinary members would also make the administrative reachable-person and family rules a directory bypass.

## Decision

Store one person-owned directory privacy record per person. It contains the overall scope (`hidden`, `own_lodge`, or `participating_lodges`) and explicit booleans for email, phone, mailing address, profile photo, and degree. Absence of a legacy/backfill record has the conservative effective default: own-lodge scope with every optional field hidden.

Name is the minimum identity shown for a listed directory result. A member who does not want their name listed uses the hidden scope. Field choices apply to ordinary own-lodge and cross-lodge directory views alike.

Treat every active platform lodge as participating in the initial release. Ordinary directory access always has an explicit requesting lodge and requires an approved, verified account linked to an active member of that lodge plus a new `directory.view` permission there. Cross-lodge results may come from people with an active membership in any active participating lodge, but do not disclose the subject's memberships or lodge affiliations.

Resolve visibility and shape output in a dedicated server-side directory access/projection service. Search predicates use the same visibility rules as result projection. A hidden email, phone number, or address must not make a person discoverable by searching that value. Direct detail routes use the same service and return 404 when the subject is not visible in that directory context.

For an own-lodge result, an opted-in degree is the degree on the subject's active membership in the requesting lodge. For a cross-lodge result, it is the highest current Masonic degree among the subject's active memberships. Past Master is not a degree and is not returned. Membership identifiers, statuses, dates, notes, roles, officer history, family relationships, birth/death data, and account data are never part of the ordinary directory projection.

Keep administrative people access separate. `people.view` continues to authorize the existing lodge-reachable administrative projection, including support access that may ignore directory presentation choices. An officer assignment alone never grants application access; the user still needs an explicit role carrying `people.view`. Platform administration continues through its separate authorization path. Administrative screens must clearly state that privacy choices govern the member directory, not authorized lodge recordkeeping.

Give the built-in Member role `directory.view`, not `people.view` or `relationships.view`. Built-in Officer and Administrator roles receive both directory and applicable administrative permissions. Existing custom roles are not silently rewritten.

Serve profile-photo derivatives through authenticated, authorization-aware routes. Never place private originals or stable public storage URLs in directory data.

Use database queries for the initial directory. Do not introduce a search index or result cache in Phase 6. Privacy changes are therefore visible on the next request. Any later index or cache must include visibility context and implement synchronous invalidation before it can replace this contract.

## Consequences

- One privacy choice follows a person across all memberships and avoids contradictory publication of canonical contact data.
- Own-lodge treatment automatically applies in every lodge where the person currently has an active membership.
- Ending a membership immediately removes that lodge's own-lodge basis; hidden and cross-lodge rules are reevaluated on every request.
- Directory and administrative people pages require distinct policies, routes, projections, and tests.
- Cross-lodge directory results deliberately do not reveal lodge affiliations in the first release.
- Search remains simple and privacy-correct at pilot scale. A future indexed implementation must preserve identical visibility semantics.
- Family information remains outside the directory regardless of scope.
