# ADR 0003: Traditional Ubuntu Production Baseline

- Status: Accepted
- Date: 2026-08-20

## Decision

The initial production baseline is Ubuntu 24.04 with conventionally installed Nginx, PHP-FPM, PostgreSQL, Redis, mail services, and persistent queue workers. Local Docker Compose files are not assumed to be production deployment definitions.

## Consequences

Production operations follow familiar Ubuntu service-management practices. Deployment documentation must pin packages and service topology. Moving production to containers remains possible but requires a later ADR and operational design.
