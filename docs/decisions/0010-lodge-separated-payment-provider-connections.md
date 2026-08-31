# ADR 0010: Lodge-Separated Payment-Provider Connections

- Status: Accepted
- Date: 2026-08-31

## Context

Store and Fundraisers begin with cash, check, pay-at-pickup, and manually maintained progress; online payments are explicitly outside their initial phases. Future online payment support must not compromise the platform's lodge isolation or turn an external provider into the owner of WorkingTools orders, products, campaigns, or fulfillment.

One shared WorkingTools merchant account would mix lodge financial relationships and create an unacceptable cross-tenant fallback path. A payment provider may offer a connected-account or integration-partner model. Helcim is currently a leading candidate because its connected-account model appears compatible with independently connected lodge merchant accounts, but provider selection remains deferred.

## Decision

Establish a provider-neutral lodge payment-provider boundary:

- WorkingTools supports platform-approved provider integrations when a future payment phase implements them. Platform-level partner/integration credentials, when a provider requires them, remain encapsulated in the provider integration rather than lodge merchant credentials.
- Each lodge independently connects its own merchant relationship/account to a supported provider.
- Provider availability may be controlled by platform administration where appropriate; the lodge controls whether its connected provider is enabled and which supported methods are used.
- Store and Fundraisers consume the shared lodge payment configuration. They never own provider credentials and do not embed provider-specific configuration in their domain records.
- A lodge payment configuration includes a provider/account identity, connection/enabled status, encrypted credentials or tokens, supported payment methods, and provider-specific optional capabilities behind the integration boundary.
- WorkingTools remains authoritative for its products, inventory, carts, orders, fulfillment, campaigns, progress, and historical records. The provider handles the financial transaction.

WorkingTools must never process one lodge's payment through another lodge's merchant account or credentials. It must not use a generic or shared WorkingTools merchant account as a fallback. If the explicit lodge connection is unavailable, disabled, disconnected, invalid, misconfigured, or failing, online payment fails closed; only separately configured offline methods may remain available.

Sensitive payment credentials flow directly from the customer's browser to the provider through a provider-hosted or provider-controlled checkout/tokenization mechanism. WorkingTools does not collect, transmit through its Laravel backend, or store raw card, CVV, expiration, bank-account, or routing credentials. It retains only the safe provider, merchant/account, checkout/session, transaction/payment, amount, currency, method-category, status, timestamp, and later refund/reference metadata required to reconcile its lodge-owned records.

Future provider webhooks are authenticated according to the provider mechanism, idempotent, lodge-aware, safely correlated to the complete provider-connection/lodge/order-or-campaign chain, robust against duplicate and practical out-of-order delivery, and auditable. A provider transaction identifier alone never authorizes mutation of another lodge's record.

## Consequences

- Online payment remains a future implementation phase; neither Store nor Fundraisers requires a provider connection.
- Cash, check, pay-at-pickup, and manual progress remain first-class lodge-configurable workflows.
- Lodges may enable different payment methods and use online payment independently for Store and Fundraisers.
- Platform administrators manage supported integrations and diagnostic availability, but do not normally control lodge-deposited funds.
- Lodge administrators manage only their own connection/onboarding state and enabled methods under explicit lodge authorization.
- Provider adapters, credentials, cache keys, jobs, webhooks, audit records, and tests must carry and validate explicit lodge/provider-connection context.
