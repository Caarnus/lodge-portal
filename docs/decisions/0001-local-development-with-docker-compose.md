# ADR 0001: Local Development with Docker Compose

- Status: Accepted
- Date: 2026-08-20

## Decision

Docker Compose is the required local-development interface. It runs separate Nginx, PHP-FPM/application, PostgreSQL, Redis, queue-worker, Node.js/Vite, and Mailpit services. PHP-FPM and workers reuse one application image. Database and media data persist in volumes, and a deliberate reset command is documented.

Developers do not need host installations of the application runtimes or services. The application is available at `http://localhost`.

## Consequences

Local setup is reproducible and reasonably similar to production. Docker is required for development. Production remains a traditional Ubuntu service installation unless another ADR changes it.
