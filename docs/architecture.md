# Architecture

## Member portal boundary

The member portal is a separate read/write boundary from administrative People. `SelfProfileService` resolves a valid current User-to-Person link for self-service writes. `DirectoryAccess` owns request-lodge eligibility and minimal presentation projections. Directory HTTP controllers never serialize Person models directly, and profile-photo derivatives are private-storage responses re-authorized for every request.

The dashboard uses a read service keyed by stable user and person identifiers. It composes bounded sections and does not treat active-lodge selection as proof of membership or visibility.

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

## Person and Membership Boundary

People and users are global identities; memberships are lodge-owned relationships. Person identity/contact fields have one canonical value, while membership status, degree, Award of Gold state, lodge numbers, dates, notes, family-relationship provenance, current officers, Past Master years, and roles retain explicit lodge ownership.

A lodge-scoped people query begins with its active memberships and relationships connected to those members. It does not query the global people table and filter afterward. Shared person mutation requires both a lodge permission and an active membership relationship, and the authorizing lodge is recorded in audit data. Relationship edits are the deliberate cross-owner exception: a qualifying primary lodge may edit after write-time endpoint/membership validation.

Account access is independent of membership data. A user-person link is global and unique; lodge access comes from explicit lodge role assignments. Ending a membership, removing a role, unlinking an account, and merging people are separate domain transitions. Officer assignment/removal may prompt for a corresponding role change but does not perform one implicitly.

The public Officers section is a narrow read model keyed by the explicitly resolved public lodge. It returns only current, public assignment fields and never exposes a general person or membership serialization.

## Event Boundary

Events and materialized occurrences are lodge-owned. A canonical RRULE and IANA time zone define a recurring series, while bounded occurrence rows provide stable identities for overrides, attendance reservations, reminder subscriptions and deliveries, routes, and calendar output. The original recurrence identity remains stable when an occurrence moves.

Protected-event visibility is derived from the explicitly loaded event lodge, the linked person, active membership, configured cross-lodge participation, and qualification. Active lodge selection never supplies eligibility. Public CMS queries return only published public occurrences for the public site's resolved lodge.

Reservations consume occurrence capacity; reminder subscriptions express notification consent and consume none. Volunteer positions and commitments are a third independent interaction type and are never stored in either record. Positions may be series- or occurrence-scoped; commitments bind one linked person to one position and occurrence. Staffing reminders use dedicated delivery rows and reload the complete ownership chain before mail.

See [Phase 1](phase-01.md), [Phase 2](phase-02.md), [Phase 3](phase-03.md), [Phase 4](phase-04.md), and the records in [decisions](decisions/README.md).
