# Phase 12 — Store and Order Management

## Outcome

A lodge can manage products, inventory, carts, and offline/manual-payment orders and may present a public storefront through the existing website/CMS architecture when Store is effectively enabled.

## Module Contract

Add the platform-owned `store` module definition. Phase 10 controls availability, lodge preference, effective state, workspace filtering, direct enforcement, jobs, cache/search, auditing, and data preservation. Enabling Store does not publish a storefront or add public navigation.

Define platform-owned lodge permissions for product/catalog management and order management, following existing permission-catalog conventions. Store permissions never grant `lodge_modules.manage`, and neither permission nor an active-lodge selection bypasses ineffective Store state.

## Domain Scope

- Lodge-owned product: stable identity, name, description, active/inactive state, photos, base price where applicable, and publication/catalog state.
- Lodge-owned product variant: option values such as size/color, SKU or stable identity where useful, price override where supported, active state, and variant-specific quantity when applicable.
- Inventory: deliberate available quantity behavior and adjustments that cannot cross lodge ownership.
- Cart: bounded storefront selection state; server recalculates current availability and price before creating an order.
- Lodge-owned order: stable number/identity, customer/contact snapshot, totals, lifecycle, payment status, and fulfillment status.
- Order line: immutable purchase-time snapshot of product/variant description, unit price, quantity, and line total, retaining historical meaning if catalog data later changes.
- Fulfillment snapshot: pickup-at-lodge or shipping choice and the address/contact data required to fulfill it.
- Payment record/state: cash, check, or pay-at-pickup/manual workflow with amount/status/reference metadata appropriate to offline handling.

Exact schema belongs to implementation design, but order history must not depend on mutable product/customer fields. Product photos use the existing lodge-owned media boundary.

## Storefront and Checkout

The supported Store CMS section reads a bounded public projection for the explicitly resolved lodge. Product cards visibly show name, price, availability/fulfillment information, and purchase action without hover. Checkout supports pickup or shipping and collects only the contact/address information necessary for fulfillment. Server validation prevents ordering inactive, unavailable, foreign-lodge, or insufficient-inventory variants.

Support cash, check, and pay-at-pickup/manual payment. Do not accept card data or imply that manual status equals externally settled funds. Order management clearly presents totals, payment method/status, fulfillment method/status, customer snapshot, and history.

At widths below `md`, management tables become intentional cards with a stable bottom action row. At `md` and above, use one stable table layout with reserved action space. Create/edit work uses established dialog/form patterns.

## Disabled and Publication Behavior

If Store becomes ineffective, public product/order actions, administrative workspaces, APIs, jobs, search results, and cached output fail closed. A published Store-backed CMS section renders its defined unavailable/empty treatment without products or purchase URLs; the page and section remain published. Store enablement alone never adds navigation.

Products, variants, inventory history, carts where retention is appropriate, orders, lines, customer/fulfillment snapshots, manual payment state, and audit history are not deleted. Re-enabling restores catalog visibility according to product state and CMS publication and restores authorized order management.

## Future Payment Extension Boundary

The model may later associate provider-neutral payment attempts/transactions and statuses with a lodge-owned order. It must not assume manual payments are the only future method, store/process raw card details, mix funds between lodges, or make a provider dependency part of Phase 12. Provider choice is deferred. Future webhooks must be idempotent, lodge-aware, and map provider transaction identities to the correct lodge-owned aggregate.

## Automated Tests

Cover two-lodge product/media/variant/inventory/order isolation; price and order snapshots; cart revalidation; inventory boundaries; pickup/shipping; cash/check/pay-at-pickup; payment and fulfillment transitions; permissions; direct identifiers; public CMS publication; and responsive critical paths. Repeat representative public/admin/API/job/cache/search paths for Store enabled, lodge-disabled, and platform-unavailable. Verify disabling retains products/orders and re-enabling restores them.

## Manual Acceptance

Create products with photos and size/color variants, exercise variant inventory, publish a Store section, place pickup and shipping orders using every manual payment method, and manage payment/fulfillment status. Confirm a second lodge cannot see or mutate any record. Disable and revoke Store availability, verify all surfaces fail closed without deletion, then re-enable and confirm historical orders and eligible catalog output return.

## Non-Goals

- Online card payments or any raw-card handling.
- Carrier rate shopping or shipping-label purchase.
- Automated sales-tax engines.
- Accounting integrations.
- Discount/coupon/loyalty systems.
- Marketplace or cross-lodge cart/order aggregation.
