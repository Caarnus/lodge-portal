# Coding Standards

- Use strict, explicit types where supported and follow Laravel conventions.
- Keep controllers thin; place domain transitions in focused application/domain services.
- Centralize authorization in policies and permission services rather than UI conditions or scattered role-name checks.
- Model lodge ownership explicitly and avoid implicit global scopes as the sole isolation defense.
- Validate input with form requests and use database constraints for critical invariants.
- Use Vue 3 Composition API, Inertia, PrimeVue unstyled components, and Tailwind CSS.
- Meet accessible keyboard, focus, labeling, contrast, and responsive-layout expectations.
- Never commit credentials. Document environment variables in an example environment file.
- Add tests with every behavior change, including a cross-tenant negative case for lodge-owned features.
- Record architecture-changing decisions as ADRs under `docs/decisions`.
- Define every public content section with a server-side validation contract and a matching editor and renderer; do not accept untyped arbitrary configuration.
- Sanitize rich text and custom HTML on the server. Never rely on client rendering or editor behavior as the security boundary.
- Treat publishing as a transaction over a complete validated draft, including navigation and media ownership.
- Generate managed public links from route identities or lodge-relative targets rather than storing absolute lodge URLs.
- Require meaningful alternative text for informative public images and preserve keyboard operation without hover-only interactions.
- Prefer no-fee open-source dependencies. Record and review licenses before adding a runtime package, and do not make ordinary lodge operation depend on a premium component or paid SaaS.
- Keep uploaded originals private; validate decoded image content and publish only normalized derivatives with unnecessary metadata removed.
