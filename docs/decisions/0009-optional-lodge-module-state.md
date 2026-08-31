# ADR 0009: Separate Optional-Module Availability, Lodge Preference, and Authorization

- Status: Accepted
- Date: 2026-08-30

## Context

Phase 1 introduced platform-defined features and lodge assignments, but the active planning documentation did not consistently distinguish a platform administrator making a module available from a lodge choosing to use it. Scholarship documentation consequently allowed either a platform or lodge administrator to “enable” the feature while the authorization matrix reserved feature assignments to platform administrators.

Scholarship, Store, Fundraisers, and Games need one reusable, tenant-safe gating contract. Public website/CMS, People, Events, Volunteer staffing, the member portal/directory, Newsletters, Galleries, Ritualist Program, and Lodge groups/regional discovery are baseline platform capabilities and must not become optional modules.

## Decision

Represent optional-module state as separate concepts:

1. A platform-owned module definition identifies a supported optional capability.
2. A platform administrator controls availability of that module to a lodge.
3. An authorized administrator of that lodge controls the lodge's enabled preference, but only while the module is available.
4. The effective state is `platform availability AND lodge enabled preference`.
5. Authentication, lodge ownership, permissions, privacy, publication, and other domain authorization remain independent requirements.

The lodge preference is preserved when platform availability is revoked. Revocation or lodge disablement hides and blocks the module without deleting its data. Re-enabling restores access to preserved data subject to ordinary authorization and publication rules.

All module-specific entry points use a centralized module-state application service. Server routes and APIs, public projections, jobs, search, cache reads, and feature-backed CMS sections fail closed when the module is ineffective. Navigation and client-side controls reflect the server decision but are not the enforcement boundary. Active-lodge context never substitutes for module state or authorization.

Module definitions are added when their production module is implemented. Phase 10 proves the generic mechanism with test fixtures or another non-user-facing test mechanism; it does not create placeholder workspaces for future modules.

## Consequences

- Platform availability, lodge preference, effective state, authorization, and CMS publication cannot be represented by one overloaded boolean.
- A lodge administrator cannot self-grant platform availability or change another lodge by altering an identifier.
- Module-specific permissions never bypass an unavailable or disabled module, and module state never grants a permission.
- Data, audit history, and historical relationships survive disablement and temporary availability revocation.
- Cache keys and invalidation, queued work, search indexing, and public projections must include or re-evaluate effective state.
- Each optional module phase adds its definition and regression coverage without scattering module-name conditionals through controllers or policies.
