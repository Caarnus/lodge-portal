# Tenancy Rules

1. Every lodge-owned row has an explicit lodge identifier.
2. Route parameters and active lodge context are untrusted input, not authorization.
3. Server-side policy checks verify both permission and resource ownership.
4. Platform administrators are not automatically lodge members.
5. Multi-lodge users receive independent assignments for each lodge.
6. Queries, validation rules, uploads, jobs, cache keys, search, exports, and logs retain the applicable lodge scope.
7. A user cannot associate an uploaded file or other child resource with a lodge they cannot administer.
8. Tests use at least two lodges and attempt direct identifiers and route manipulation.
9. Shared data is shared only through an explicit rule; database co-location never implies visibility.
10. Public routes resolve a lodge from an explicit slug and never from authenticated active-lodge context.
11. Public page queries require both the resolved lodge and a published version; a matching slug in another lodge is never a fallback.
12. Website pages, versions, sections, navigation parents, and lodge media carry and validate consistent lodge ownership.
13. Public navigation is generated only from the resolved lodge's published versions.
14. Platform-shared media is an explicit ownership state. A null or missing lodge identifier does not implicitly make a file shared.
15. Disabled and disabled-and-locked lodges expose no public content, including cached content.
16. A person identifier never grants visibility. A lodge-scoped query must prove reachability through an active membership in that lodge or a relationship to one of its active members.
17. Shared person identity/contact fields are person-owned; membership fields, notes, family relationship records, officer assignments, and role assignments remain lodge-owned.
18. Global people search is platform-admin only. Lodge people search begins from authorized lodge relationships and cannot fall back to exact email or name matches in another lodge.
19. Shared person updates require an active membership in the authorizing lodge and the applicable permission; the audit event records that lodge.
20. Person relationships connect global people and carry an owning lodge for provenance. A lodge may view one when either endpoint is its active member. Editing additionally requires a qualifying endpoint membership whose primary lodge number matches that lodge's own number; two primary lodges may therefore edit one relationship.
21. Officer assignments carry a lodge and must reference a membership from the same lodge. Public officer queries resolve only the public site's explicit lodge.
22. Ending a membership or revoking a lodge role affects only that lodge and does not delete the global person/user or another lodge's records.
23. Person merges are platform-admin transactions that revalidate every dependent lodge owner and roll back on unresolved conflicts.
24. Every cross-owner relationship edit records the authorizing lodge and revalidates visibility/edit eligibility at write time; cached or previously selected lodge context is insufficient.
25. Events, occurrences, reservation fields, reservations, reminder rules, reminder subscriptions, and reminder deliveries carry or derive one matching lodge owner enforced at write time.
26. Event routes load the lodge, event, and occurrence together; a valid occurrence identifier never bypasses event/lodge ownership checks.
27. Protected-event eligibility derives from the event's owning lodge and current membership data. Active lodge context, a submitted lodge identifier, a prior reservation, or a reminder subscription never grants access.
28. Public CMS and calendar queries return only published public occurrences for the explicitly resolved public lodge. A missing occurrence never falls back to another lodge.
29. Event jobs carry stable identifiers and reload the complete lodge/event/occurrence/subscription/delivery ownership chain before mutation or delivery.
30. Reservations, reminder subscriptions, and future volunteer commitments are independent intents. Creating one never authorizes, implies, or silently creates another.
31. Volunteer positions, commitments, and staffing reminder deliveries carry matching lodge, event, and occurrence ownership. Nested volunteer routes validate every link before authorization.
32. Optional-module availability never grants authorization, and lodge module enablement never grants authorization.
33. A module-specific permission cannot bypass platform-unavailable or lodge-disabled state; effective module state cannot bypass permission or ownership checks.
34. Module state is resolved for the explicit target lodge. State for one lodge cannot enable, disable, expose, or suppress another lodge's records.
35. Scholarship, Store, Fundraiser, and lodge-private Games records carry explicit lodge ownership. Platform/shared Games content retains its explicit platform/shared ownership.
36. Direct module/resource identifiers never bypass effective state or lodge ownership. Active lodge context is not evidence of either.
37. Module jobs reload lodge ownership and effective state; search, cache, public projections, downloads, and feature-backed CMS sections fail closed when ineffective.
38. Disabling a module or revoking availability does not delete, reassign, anonymize, or unpublish its stored records as a side effect. Re-enablement restores only normally authorized/published access.
39. Platform availability and lodge enabled preference are separate configuration values. Revoking availability does not erase the lodge preference.
40. A lodge payment-provider connection has explicit lodge and provider ownership. Provider account identifiers, encrypted credentials/tokens, supported methods, checkout sessions, transactions, and webhook records are always resolved through that complete ownership chain.
41. WorkingTools must never process a lodge's payment through another lodge's merchant account or credentials and has no implicit shared platform merchant-account fallback.
42. A disconnected, disabled, unavailable, invalid, misconfigured, or failing lodge payment connection fails closed for online payment. Only payment methods explicitly configured by that lodge may remain available.
43. A provider transaction/payment identifier, checkout/session identifier, or webhook payload never by itself authorizes a cross-lodge order, campaign, contribution, or payment-state mutation.
44. Raw card, CVV, avoidable expiration, bank-account, and routing credentials never enter WorkingTools application storage or Laravel request processing. Provider-controlled checkout/tokenization handles sensitive credential capture.
45. Payment-provider webhooks authenticate with the provider mechanism, are idempotent and lodge-aware, revalidate connection/lodge/business-record ownership, and retain bounded audit/diagnostic context.
# Directory and profile ownership

Directory requester authorization is evaluated from the route lodge, never `current_lodge_id`. A person-wide privacy setting belongs to the canonical person, while lodge-email preference belongs to a specific membership and its lodge. Self-service profile writes resolve the current user-to-person link server-side; submitted person or lodge identifiers never establish ownership.

Directory projections may cross lodges only when the subject chose `participating_lodges`. They contain presentation fields only and are delivered with private/no-store cache policy. Originals and processed profile photos stay on private storage; derivative reads re-check requester and subject eligibility.

## Ritual assistance exception

Ritual progress is person-owned, not lodge-owned. An active member may opt into discovery from every own active lodge or across WorkingTools lodges. This narrow discovery exception is independent from directory scope: cross-lodge results disclose only active hosted-lodge affiliations and separately opted-in email/phone. Disabled and historical affiliations never authorize or project. Every search starts from its explicit requesting lodge; current-lodge selection never supplies authority.

## Lodge groups and discovery

Lodge groups are platform-owned filter metadata, not tenant containers. Adding lodge to group never grants access to lodge-owned data. Every group-filtered query must first apply event eligibility, directory privacy, ritual consent, or public-resource rules, then narrow result through active group membership. Inactive/archived groups and disabled lodges cannot supply active discovery results; historical pivots may remain.
