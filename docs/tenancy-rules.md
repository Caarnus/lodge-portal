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
