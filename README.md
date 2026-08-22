# Multi-Lodge Masonic Platform

A multi-tenant platform for Masonic lodges to manage their public websites, members, events, communications, and regional collaboration from one application while preserving strict lodge-level data isolation.

## Project Status

The platform foundation, public lodge website CMS, people/membership administration, and lodge events are implemented. Events support materialized recurring occurrences, reservations, reminder subscriptions and delivery, volunteer staffing positions and commitments, ICS calendar output, event-category configuration, and tenant-scoped event management.

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

The backend services run in Docker Compose, while Node.js and Vite run directly on the host for faster file watching and frontend builds. Install Node.js 24 and npm on the host before starting.

Copy `.env.example` to `.env`, then run:

```bash
docker compose build
docker compose run --rm app composer install
npm install
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan platform:admin admin@example.com --name="Platform Administrator"
```

Start the host Vite development server in a separate terminal:

```bash
npm run dev
```

The application is available at `http://localhost`, Vite runs at `http://localhost:5173`, and Mailpit is available at `http://localhost:8025`.

For a production-style frontend build instead of the development server, run `npm run build` on the host. Laravel will serve the generated assets from `public/build`.

### PhpStorm

The following shared configurations are available from PhpStorm's Run configuration menu:

- `WorkingTools: Docker Compose` starts the backend Compose services.
- `WorkingTools: Vite Dev` starts Vite directly on the host.
- `WorkingTools: Development` starts both configurations together.
- `WorkingTools: Vite Build` creates production frontend assets on the host.
- `WorkingTools: Listen for Xdebug` listens for PHP debug connections.

The npm configurations use the project Node.js interpreter. If PhpStorm has not selected Node.js automatically, configure it under **Settings → Languages & Frameworks → JavaScript Runtime**. The Docker configuration expects a PhpStorm Docker connection named `Docker`, which is the usual Docker Desktop connection name.

The PHP image includes Xdebug with request-triggered debugging to avoid slowing normal requests. PhpStorm's shared `WorkingTools` server maps the project root to `/var/www/html` and Xdebug connects to the host on port `9003`. After rebuilding the PHP image, start `WorkingTools: Listen for Xdebug` and either use a browser Xdebug extension or add `XDEBUG_TRIGGER=PHPSTORM` to the request query string. To debug an Artisan command, use:

```bash
docker compose exec -e XDEBUG_TRIGGER=1 app php artisan your:command
```

If port `9003` has been changed globally in PhpStorm under **Settings → PHP → Debug**, change it back to `9003` or update `docker/php/xdebug.ini` to match.

Common commands:

```bash
docker compose logs -f
npm run build
npm run test:e2e
docker compose run --rm test
docker compose run --rm browser
docker compose down
```

### Event operations

Event occurrences are materialized through an 18-month rolling horizon. Laravel scheduler runs these commands:

```bash
docker compose exec app php artisan events:extend-occurrence-horizon
docker compose exec app php artisan events:dispatch-reminders
docker compose exec app php artisan events:dispatch-volunteer-reminders
```

All commands are safe to rerun. Run the horizon command after deploying event schedule changes or when recovering scheduler downtime. Reminder dispatch claims deliveries before queueing them; failed deliveries require an explicit event-manager retry and are never retried automatically. Staffing reminders are separate from ordinary event reminders and never create a reservation or reminder subscription.

The host `npm run test:e2e` command tests the currently running application. The containerized browser command uses the assets produced by the preceding host `npm run build`, creates clearly named synthetic test accounts and records, then executes the complete two-lodge Playwright flow. These accounts are not created by normal application seeding or startup and are not production credentials.

To deliberately erase the local database, Redis data, media, and browser-test Node modules, run `docker compose down --volumes`. Host `node_modules` is not managed by Docker. This is destructive and cannot be undone.

## Documentation

- [Phased implementation plan](multi_lodge_platform_phased_implementation_plan.md)
- [Phase 1 specification](docs/phase-01.md)
- [Phase 2 specification](docs/phase-02.md)
- [Phase 3 specification](docs/phase-03.md)
- [Phase 4 specification](docs/phase-04.md)
- [Phase 5 specification](docs/phase-05.md)
- [Architecture](docs/architecture.md)
- [Domain model](docs/domain-model.md)
- [Tenancy rules](docs/tenancy-rules.md)
- [Authorization](docs/authorization.md)
- [Coding standards](docs/coding-standards.md)
- [Testing strategy](docs/testing-strategy.md)
- [Architecture decision records](docs/decisions/README.md)

## Contributing

Contribution guidelines will be established as implementation begins. Changes should follow the documented architecture, tenant-isolation rules, coding standards, and testing strategy. Architectural decisions that affect future phases should be recorded as ADRs.

## License

Copyright © 2026 Mark Lewellyn.

This project is free software licensed under the [GNU Affero General Public License, version 3](LICENSE). You may redistribute and modify it under the terms of that license. Modified versions made available over a network must also make their corresponding source available as required by the AGPLv3.
