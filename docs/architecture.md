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

## Phase 1 Runtime

Local services are Nginx, PHP-FPM/application, PostgreSQL, Redis, a queue worker, Node.js/Vite, and Mailpit. The application and queue worker use the same PHP image. PostgreSQL data and local media persist in Docker volumes.

See [Phase 1](phase-01.md) and the records in [decisions](decisions/).
