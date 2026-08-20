# Multi-Lodge Masonic Platform

A multi-tenant platform for Masonic lodges to manage their public websites, members, events, communications, and regional collaboration from one application while preserving strict lodge-level data isolation.

## Project Status

The project is currently in planning and initial foundation work. The phased implementation plan and Phase 1 technical specification are complete; application scaffolding has not yet begun.

## Planned Stack

- Laravel 13 and PHP 8.4
- PostgreSQL
- Vue 3 and Inertia
- PrimeVue in unstyled mode with Tailwind CSS
- Redis for queues, cache, and sessions
- Playwright for critical browser workflows
- Docker Compose for local development
- Nginx and PHP-FPM on Ubuntu 24.04 for the initial production deployment

## Local Development

Phase 1 will provide a Docker Compose environment with the application available at `http://localhost`. It will include Nginx, PHP-FPM, PostgreSQL, Redis, a queue worker, Node.js/Vite, and Mailpit.

Setup and development commands will be added when the Phase 1 application scaffold is implemented. Until then, there is not yet a runnable application.

## Documentation

- [Phased implementation plan](multi_lodge_platform_phased_implementation_plan.md)
- [Phase 1 specification](docs/phase-01.md)
- [Architecture](docs/architecture.md)
- [Domain model](docs/domain-model.md)
- [Tenancy rules](docs/tenancy-rules.md)
- [Authorization](docs/authorization.md)
- [Coding standards](docs/coding-standards.md)
- [Testing strategy](docs/testing-strategy.md)
- [Architecture decision records](docs/decisions/)

## Contributing

Contribution guidelines will be established as implementation begins. Changes should follow the documented architecture, tenant-isolation rules, coding standards, and testing strategy. Architectural decisions that affect future phases should be recorded as ADRs.

## License

Copyright © 2026 Mark Lewellyn.

This project is free software licensed under the [GNU Affero General Public License, version 3](LICENSE). You may redistribute and modify it under the terms of that license. Modified versions made available over a network must also make their corresponding source available as required by the AGPLv3.
