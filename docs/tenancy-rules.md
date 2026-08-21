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
