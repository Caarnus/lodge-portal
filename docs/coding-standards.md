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
