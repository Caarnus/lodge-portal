# Phase 13 — Fundraising Campaigns

## Outcome

A lodge can publish accessible fundraising progress boards and maintain authoritative progress manually without Store or online payments. Campaigns retain history and may optionally associate Store products when both modules are effectively enabled.

## Module Contract

Add the platform-owned `fundraisers` module definition and lodge-scoped campaign-management permission through the Phase 10 contract. Fundraiser permission does not grant module-toggle authority. Store and Fundraisers are independent: either may be available/enabled without the other.

## Domain Scope

A lodge-owned campaign supports:

- title/name and stable public slug or route identity consistent with existing lodge routing;
- description/content and optional lodge-owned image/media;
- monetary goal and current progress;
- authoritative manual progress updates by an authorized lodge administrator;
- optional start/end dates;
- draft, published, active, completed, or equivalent lifecycle consistent with project conventions;
- multiple campaigns and historical retention; and
- optional associations to lodge-owned Store products.

Record progress changes sufficiently for auditing and safe correction. The initial source of truth is the administrator-maintained progress value; automatic reconciliation is not required. Present a visible numeric amount/percentage and accessible progress semantics, never color or bar length alone.

The domain may later add direct, offline/manual, order-derived, and provider-backed contribution records without replacing campaign identity or mixing lodge ownership. Phase 13 does not treat progress as a tax-deductible contribution ledger and creates no tax receipts.

## Store Association

Product association is optional and requires matching lodge ownership. Purchasing remains a Store operation and is offered only while Store is also effectively enabled and the product is eligible. Disabling Store removes public purchase actions but does not invalidate the campaign, manual progress board, historical product relationship, order data, or campaign history. It must not decrease or recalculate authoritative manual progress automatically.

## Public CMS and Disabled State

Use a supported campaign section in the existing versioned public-site architecture. Enabling Fundraisers never creates a page or navigation entry. Published campaign sections recheck effective state and campaign lifecycle at render time.

When Fundraisers is ineffective, public campaign output/actions, administration, APIs, jobs, search, and cache fail closed while campaigns, media references, progress history, and Store associations remain stored. Re-enabling restores eligible projections under current publication/lifecycle rules.

## Future Payment Boundary

Future contributions may reference provider-neutral transaction IDs/statuses and must remain attributable to one lodge. WorkingTools will not store/process raw card data. Provider selection and online payments are deferred; future webhook handling must be idempotent and lodge-aware.

## Automated Tests

Cover campaign ownership/lifecycle/slugs, manual progress validation/audit, accessible numeric projections, CMS publication, image ownership, multiple/history behavior, and cross-lodge identifiers. Test Fundraisers with Store unavailable, disabled, and enabled; Store with Fundraisers absent; product associations with matching/mismatched lodges; Store disablement preserving the campaign and removing purchase actions. Repeat enabled/lodge-disabled/platform-unavailable tests for direct URLs/APIs, jobs, cache/search/public projections, preservation, and restoration.

## Manual Acceptance

Create and publish several campaigns, update progress manually, confirm numeric and accessible progress presentation, complete/archive one campaign, and verify history. Run a campaign with no Store. Associate a product when both modules are effective, then disable Store and confirm the campaign remains while purchase action disappears. Disable/re-enable Fundraisers and verify data preservation and restored authorized access.

## Non-Goals

- Requiring Store, ecommerce, or payment automation.
- Online payments or provider selection.
- Automatic accounting reconciliation.
- Tax-deductibility claims or tax receipts.
- A second website builder outside the existing CMS.
