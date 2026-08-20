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
