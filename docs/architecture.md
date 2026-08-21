# Architecture

## Baseline

The platform is a Laravel 13 application on PHP 8.4 using Vue 3, Inertia, PrimeVue in unstyled mode, Tailwind CSS, PostgreSQL, and Redis. It is one application deployment with one shared relational database serving multiple lodge tenants.

The local environment is Docker Compose. Production initially uses conventional services on Ubuntu 24.04 with Nginx and PHP-FPM.

## Application Boundaries

- Platform capabilities operate independently of lodge membership.
- Lodge-owned resources always carry explicit lodge ownership.
- Active lodge context selects a working scope but never grants authorization.
- People, authenticated users, and lodge memberships are distinct concepts.
- Files, cache entries, jobs, logs, and audit entries retain lodge context when applicable.

## Runtime

Local services are Nginx, PHP-FPM/application, PostgreSQL, Redis, a queue worker, Node.js/Vite, and Mailpit. The application and queue worker use the same PHP image. PostgreSQL data and local media persist in Docker volumes.

## Public Website Boundary

Public lodge sites and the authenticated administration application share the Laravel deployment but have separate route and authorization concerns.

- Public routes resolve a lodge explicitly from its slug and read published content only.
- Disabled lodges do not expose public pages.
- Authenticated active-lodge context is never used to select public content.
- Draft preview is an authenticated, lodge-authorized route.
- Stable page identities own versioned metadata; ordered, typed sections belong to a page version.
- Publishing validates and promotes a complete draft transactionally.
- Public rendering uses the supported Vue/Tailwind design system and lodge branding tokens.
- Managed content contains route targets and media identifiers rather than absolute lodge-domain URLs.
- Custom domains can be added later without changing page ownership or stored links.
- Public URL handling is host-agnostic: the initial `/l/{lodge-slug}` prefix and future verified custom domains resolve through the same lodge-aware handlers.
- Original media is private. Lodge-aware processing produces bounded, metadata-stripped public derivatives before content can be published.

See [Phase 1](phase-01.md), [Phase 2](phase-02.md), and the records in [decisions](decisions/README.md).
