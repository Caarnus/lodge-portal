# Phase 10 — Optional Lodge Module Gating

## Outcome

The platform has one generic, audited, tenant-safe contract for optional lodge modules before Scholarship or any other optional module is implemented. Platform administrators decide which modules are available to each lodge. An authorized lodge administrator may enable or disable only modules available to that lodge. Effective module state is enforced server-side and remains independent of authorization and CMS publication.

Phases 1–9 are implemented in development. Phase 10 is the next planned phase and is a retrofit of Phase 1's feature-definition/assignment foundation, not a claim that the earlier phases were unimplemented.

## Scope and Terminology

Only Scholarship, Store, Fundraisers, and Games are optional lodge modules. Their definitions are introduced with their production phases. Public website/CMS, People, Events, Volunteer staffing, member portal/directory, Newsletters, Galleries, Ritualist Program, and Lodge groups/regional discovery remain baseline platform capabilities.

The contract distinguishes:

- **Module definition:** platform-owned stable identity and presentation metadata for a supported optional module.
- **Platform availability:** whether a platform administrator permits one lodge to use the module.
- **Lodge enabled preference:** the lodge's persisted choice to use the available module.
- **Effective enabled state:** `platform availability AND lodge enabled preference`.
- **Authorization:** the separate user, permission, ownership, privacy, visibility, and publication checks required for an operation.

CMS publication is also independent. An effective module does not automatically add a public page, section, or navigation item.

## Domain Contract

Extend or adapt the existing platform feature records without overloading one field with several meanings. Exact table/class names are implementation decisions, but the domain must expose separate platform availability and lodge preference values for a `(lodge, module)` pair.

Requirements:

- Module keys are stable, unique, platform-owned identifiers, not user-entered authorization strings.
- State records explicitly identify the lodge and module and enforce one state per pair.
- Absence has a conservative, documented effective-disabled meaning.
- Revoking availability does not clear the lodge preference.
- State changes are transactional and audited with actor, lodge, module, before/after availability or preference, and timestamp.
- Definitions may be inactive or retired only through a lifecycle that preserves historical state and audit references.
- The application exposes a centralized resolver/service for availability, preference, and effective state; controllers, jobs, and renderers do not reproduce boolean expressions or hard-code module names.

### Module Definition Lifecycle

Module definitions are application-owned release data. They are created by migrations or
idempotent release seeders when the corresponding production module is introduced, not by
lodge administrators and not from request-supplied keys.

- A definition key is immutable after creation.
- A definition may be active or retired. Retired definitions resolve ineffective for every
  lodge and disappear from ordinary availability/preference controls, while their lodge-state
  rows and audit references remain intact.
- Normal application workflows must not hard-delete module definitions. Foreign-key behavior
  must not cascade-delete historical lodge module state merely because a definition is retired
  or removed incorrectly.
- Restoring a retired definition does not alter any preserved platform availability or lodge
  preference. Its effective state is recalculated from the restored definition and preserved
  state.
- The state table retains a unique database constraint for `(lodge, module)` and foreign keys
  for both owners.

### State Mutation Semantics

The centralized service owns both state reads and writes.

- It exposes a boolean/query operation and a fail-closed `requireEffective`-style guard usable
  outside HTTP. Module services and jobs use the guard rather than reproducing the state
  expression.
- Availability and preference writes lock or otherwise serialize the state row sufficiently to
  produce a truthful before/after audit record under concurrent requests.
- Updating a state row must not rewrite its original creation timestamp.
- A request that would not change state is an idempotent success and does not create a misleading
  transition audit event or invalidation storm.
- Successful state transitions dispatch one application event after the database transaction
  commits. The event identifies the lodge, stable module key, prior state, resulting state, actor,
  and which control changed; it contains no module-owned sensitive data.
- List/read projections return one deliberately named shape. They must not expose duplicate
  camel-case and snake-case state keys or require controllers to rebuild the result.

Do not add production definitions, public sections, navigation, or placeholder workspaces for Scholarship, Store, Fundraisers, or Games merely to exercise Phase 10. Use test-only fixtures or another non-user-facing mechanism to prove the generic contract.

## Authorization

Platform availability management is platform administration and requires platform-administrator authorization. It is not a lodge permission.

Define the platform-owned lodge permission `lodge_modules.manage` for changing the lodge enabled preference. It is assigned to the built-in lodge Administrator role, may be assigned through the existing platform-defined role system, and authorizes only the explicit lodge in which it is held. It does not grant platform availability changes or module-data access.

The preference operation requires all of:

- authenticated actor;
- explicit target lodge;
- target lodge in an administrable lifecycle state under existing rules;
- `lodge_modules.manage` in that target lodge; and
- platform availability for the target module and lodge.

For lodge preference changes, active and disabled lodges are administrable. A
disabled-and-locked lodge is read-only to lodge administrators: its preserved preference may be
viewed but not changed until a platform administrator restores an administrable lodge state.
Platform availability remains a separate platform-administration decision and may be maintained
while a lodge is disabled or disabled-and-locked.

Module-specific permissions remain separate. A Scholarship reviewer, Store order manager, Fundraiser manager, or Games manager cannot toggle the module unless separately granted `lodge_modules.manage`. Conversely, enabling a module grants no module-specific permission. Module-data operations require both effective module state and their normal authorization.

The module-preference permission must remain usable for an available-but-disabled module so an authorized lodge administrator can re-enable it. Module-specific data permissions are ineffective while the module is disabled. Active/current lodge context supplies neither module state nor permission.

## Application Enforcement Boundary

Create reusable middleware, request guard, or equivalent application-layer integration backed by the centralized state resolver. Every module phase must apply it consistently to:

- lodge administration routes and APIs;
- public module routes and projections;
- module services invoked outside HTTP;
- direct identifiers and downloads;
- queued jobs and scheduled work;
- search indexing and result projection;
- cached public/private output; and
- feature-backed CMS section resolution.

Module state is checked before module-specific data is exposed, then ordinary authorization is evaluated. A permission never bypasses ineffective state, and state never replaces resource ownership checks.

Jobs reload the lodge and module state when executing rather than trusting state captured when queued. Work that would operate a disabled module exits safely and records appropriate operational status without deleting data. State changes invalidate module-aware caches and remove or suppress indexed/public projections. Cache reads also fail closed so stale cached output cannot bypass a later disablement.

### Required Enforcement Adapters

Phase 10 is not complete with HTTP middleware alone. Implement the following reusable adapters,
all backed by the same resolver and transition event:

- HTTP middleware for lodge administration, API, public, and download routes.
- An application-service guard for controller-independent domain services.
- A queue/scheduled-work guard that reloads the lodge and module at execution time and returns an
  explicit skipped/ineffective result before module work begins.
- A module-aware cache key/tag contract and an after-commit invalidation listener. Cache reads
  recheck effective state before returning a cached payload.
- A search indexing/projection predicate plus a transition listener that removes or suppresses
  the affected lodge/module projection when ineffective. Search result projection rechecks state
  even if an index is stale.
- A public-projection guard that accepts the explicitly resolved lodge rather than authenticated
  active-lodge context.
- A module-backed CMS section resolver contract that rechecks effective state at render time and
  returns the section-defined unavailable/omitted treatment without deleting page/version/section
  records.
- A workspace/navigation predicate requiring both effective module state and the workspace's
  ordinary permission. The Optional modules settings tab itself depends on
  `lodge_modules.manage`, not on effective state, because it must remain reachable to re-enable an
  available-but-disabled module.

No production Scholarship, Store, Fundraisers, or Games definition or workspace is created to
exercise these adapters. Use test-only doubles/fixtures that are loaded only by automated tests or
an explicitly documented local acceptance-test environment.

## Administration UI

### Platform Administration

Provide a platform-only lodge module-availability view that clearly shows, per lodge and defined module:

- module name;
- platform availability control;
- read-only lodge enabled preference; and
- derived effective status.

Copy and status treatment must make clear that revoking availability overrides but does not erase the lodge preference. Availability changes require the established confirmation and audit patterns.

### Lodge Administration

Provide a lodge settings view for authorized users that:

- lists optional modules defined for the release;
- allows toggling only modules made available by the platform;
- labels unavailable modules as unavailable rather than presenting a control that will fail;
- distinguishes enabled preference from effective status when relevant; and
- does not display module-data actions in place of settings navigation.

Permission-aware `WorkspaceTabs` and all other navigation omit optional-module workspaces unless the module is effectively enabled and the user can access that workspace. Hiding navigation is presentation behavior only; direct URLs remain server-protected.

## Public CMS Contract

An optional module can expose a supported CMS section only when its module phase defines the section contract. Publishing such a section must validate lodge ownership and an appropriate module definition. At render time, the public projection rechecks effective module state.

If a published page contains a module-backed section while the module becomes ineffective:

- the section fails closed and emits no module-owned records or purchase/action URLs;
- the page remains renderable with a deliberate empty/unavailable treatment or omission defined by the section contract;
- stale cached section output is invalidated and cannot be served; and
- the page/version/section record remains intact so content can return after re-enablement.

Enabling a module never automatically publishes a section or adds public navigation.

## Data Preservation and Lifecycle

Disabling a lodge preference or revoking platform availability must never delete, reassign, anonymize, or unpublish module-owned records as a side effect. Existing ownership and lifecycle state remain intact. Re-enabling restores eligible administration and public projections subject to permissions, publication, dates, inventory, and other domain rules.

Deleting a lodge or module definition remains governed by separate retention rules and is not part of the toggle workflow.

## Automated Tests

Use Lodges A and B and a test-only module definition when no production module exists. Cover:

- platform admin makes module available to A but not B;
- authorized A administrator enables and disables it;
- B administrator cannot enable an unavailable module;
- A administrator cannot change B by altering lodge/module identifiers;
- user without `lodge_modules.manage` cannot toggle it;
- module permission alone cannot toggle it;
- enabled preference without availability is ineffective;
- availability without enabled preference is ineffective;
- both values produce effective enabled state;
- revoking availability preserves enabled preference while making state ineffective;
- direct route/API and service attempts fail when ineffective;
- navigation/workspace projection follows effective state and permission;
- jobs recheck state and do not perform disabled operations;
- search, cache, public projection, and CMS-section fixtures fail closed;
- state changes invalidate relevant cached/indexed output;
- disablement preserves representative module data;
- re-enablement restores access to preserved data subject to authorization; and
- audit records contain correct actor, lodge, module, and bounded before/after state.

Include database uniqueness/foreign-key tests, cross-lodge negative tests, policy/service unit tests, Laravel feature tests, and focused browser coverage for both administration surfaces.

### Required Generic Test Fixture Suite

The non-production test module must include representative lodge-owned data and test-only
adapters for each boundary below. These fixtures must not be registered as production routes,
navigation, CMS section types, search indexes, scheduled tasks, or seed data.

- An administrative route/API and a public route using the HTTP middleware.
- A directly invoked service using the non-HTTP guard.
- A private download projection with a foreign-lodge identifier attack.
- A queued job that is dispatched while effective, then executed after disablement and records a
  safe skipped result without touching representative data.
- A scheduled-work equivalent or unit-level adapter test proving it uses the same execution-time
  guard.
- A module-aware cache entry proving both transition invalidation and fail-closed reads when a
  stale entry is deliberately retained.
- A search/index fixture proving disablement removes/suppresses indexed data and a deliberately
  stale result is filtered at projection time.
- A public projection and module-backed CMS-section fixture proving an existing published page
  remains renderable while module records and action URLs are omitted.
- A permission-filtered workspace entry proving state and permission are both required and that a
  direct URL remains protected.

The suite must additionally assert:

- the database rejects duplicate `(lodge, module)` state and invalid lodge/module foreign keys;
- an inactive/retired definition is ineffective without losing historical state;
- normal definition lifecycle cannot hard-delete preserved state;
- active and disabled lodges permit an authorized preference change, while
  disabled-and-locked lodges reject it;
- no-op writes are idempotent and do not create duplicate transition audits;
- audit before/after JSON is bounded to the stable module key and three state booleans and names
  the correct actor and lodge;
- changing Lodge A never invalidates, suppresses, or changes Lodge B state or projections; and
- representative module data survives lodge disablement, platform availability revocation,
  module definition retirement, and subsequent restoration.

Focused Playwright coverage runs with an environment-only test definition. It covers both
platform and lodge controls at phone portrait and tablet-landscape-or-wider layouts, confirmation
dialogs, unavailable disabled controls, preserved preference copy, effective-status changes, and
direct-URL denial. Browser setup and cleanup must not seed a user-facing production module.

## Manual Acceptance

1. As platform administrator, make the test module available to Lodge A but not Lodge B.
2. Confirm Lodge A administrator sees an available disabled control and Lodge B administrator sees an unavailable state without a usable toggle.
3. Enable it for Lodge A and confirm the effective status changes.
4. Confirm an ordinary officer/member and a module-data manager cannot change the preference.
5. Attempt Lodge B and cross-lodge identifier substitutions; confirm server denial.
6. Disable it and confirm workspace, public projection, direct URL/API, test job, search, and cached output are unavailable.
7. Confirm representative test data still exists.
8. Re-enable it and confirm access returns only for normally authorized users.
9. Revoke platform availability, confirm lodge preference is preserved but ineffective, then restore availability and confirm prior preference becomes effective again.
10. Inspect audit history for every platform and lodge state transition.

## Definition of Done

- One centralized module-state contract is used by all enforcement adapters.
- Platform and lodge controls are separate and correctly authorized.
- Effective state is computed consistently and never treated as authorization.
- Disabled-state behavior covers HTTP, services, jobs, cache, search, public projections, CMS sections, and navigation.
- Data and lodge preference survive the required lifecycle transitions.
- Phase 10 uses no fake production module UI.
- Cross-lodge, identifier-manipulation, and state-matrix tests pass.
- Architecture, domain, tenancy, authorization, UI, coding, testing, ADR, and master-plan documentation agree.
- Module-definition keys and historical state survive the supported retirement lifecycle.
- State transitions publish one after-commit event used by cache/search/projection invalidation.
- HTTP, service, job/schedule, cache, search, download, public projection, CMS, and navigation
  adapters are implemented and proven with non-production fixtures.
- Active/disabled versus disabled-and-locked preference behavior is enforced and tested.
- Both administration surfaces have focused responsive browser coverage.

## Non-Goals

- Implementing Scholarship, Store, Fundraisers, or Games.
- Creating placeholder module workspaces or public pages.
- Making baseline platform capabilities optional.
- Granting module permissions through enablement.
- General-purpose experiments/rollout flags, entitlements, billing, plans, or metering.
- Deleting data when a module is disabled.
