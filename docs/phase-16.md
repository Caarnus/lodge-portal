# Phase 16 — Regional Lodge Onboarding and Operational Hardening

## Outcome

Additional Southwest Indiana lodges can be onboarded through documented, audited workflows without ordinary setup requiring custom development.

## Onboarding Workflow

The platform administrator creates lodge identity, slug/domain mapping, lifecycle state, initial administrator invitation, branding/default template choices, and platform availability for optional modules. The availability step explicitly lists Scholarship, Store, Fundraisers, and Games definitions present in the release and does not include baseline capabilities.

The onboarding flow displays platform availability separately from the lodge enabled preference and effective state. It must not present one “enabled features” checkbox as though one role controls both decisions. The initial lodge administrator subsequently uses lodge administration and `lodge_modules.manage` to enable or disable available modules. An unavailable module has no usable lodge toggle.

Future payment-provider onboarding is separate from optional-module gating. A platform administrator may make a supported provider integration available and view bounded connection diagnostics. An authorized lodge administrator may then connect only that lodge's merchant account, choose connection/method state, and decide separately whether Store and/or Fundraisers use online payment. The lodge can continue using Store/Fundraisers with manual methods and no provider connection. Onboarding must never substitute another lodge's or a generic WorkingTools merchant account when a connection is absent or fails.

Onboarding never auto-publishes a module-backed CMS section or public navigation. Module-specific permissions and roles follow their phase catalogs and remain independent from module state.

## Operational Hardening

Complete repeatable provisioning, invitations, domain/branding setup, content templates, module-availability assignment, lodge-owned export/backup and restoration, monitoring, queues/schedules, cache/search invalidation, audit review, disabled-lodge handling, and support runbooks. Jobs reload ownership and module state. Tenant-isolation and identifier-manipulation checks apply throughout onboarding and support tooling.

## Automated and Manual Acceptance

Test provisioning at least two lodges with different availability sets and local preferences. Confirm the platform administrator can change availability but cannot silently set lodge preference through the lodge workflow; Lodge A administrator can toggle only available modules in A; Lodge B administrator cannot manipulate A or enable unavailable modules; and ordinary officers/members cannot toggle modules. When online payments are implemented, add separate Lodge A/B merchant connections and payment-method selections, direct-identifier/webhook misuse attempts, connection failure, and no shared-account fallback coverage. Exercise invite, template, domain, export/restore, queue, cache/search, disable/reactivate, and audit paths.

Pilot one additional regional lodge after Newburgh cutover. Capture onboarding duration, manual interventions, support issues, and any migration differences without weakening the common tenant/module contracts.

## Non-Goals

- Automatic approval of lodges or administrators.
- Billing, subscription tiers, or module metering.
- Making every platform capability optional.
- A cross-lodge super-administrator role outside established platform administration.
