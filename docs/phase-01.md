# Phase 1 — Platform Foundation and Lodge Provisioning

## Outcome

A platform administrator can provision lodges and administrators. Lodge administrators can securely manage identity and branding only for assigned lodges. Users can register, verify email, wait in a restricted state, and be approved or rejected. This phase is implemented in development.

## Schema Scope

Phase 1 introduced users, minimal people, lodges, minimal memberships, platform permission definitions, lodge roles and assignments, registrations/approval state, feature definitions and lodge assignments, media ownership, and immutable audit events. Full person and membership profiles arrived in Phase 3. Phase 10 explicitly retrofits the feature-assignment foundation into separate platform availability and lodge enabled preference; this preserves Phase 1 history without treating its original assignment concept as the complete optional-module contract.

Critical constraints include globally unique lodge slugs, globally unique non-null person email addresses, explicit lodge keys on lodge-owned rows, and safe foreign keys for role and media ownership.

## Domain Rules

- Home lodge selection routes registration approval and grants no access.
- A platform administrator or administrator of the selected lodge may approve or reject a registration.
- Pending users see only a restricted pending experience.
- Email verification is required.
- Administrative 2FA is configuration-controlled and may be disabled locally or by platform policy.
- Active lodge context never grants permission.
- Slugs are globally unique and changeable through an audited action.
- Audit entries cannot be edited or deleted through normal application workflows.

## Routes and UI

Phase 1 provided authentication, verification, password reset, optional 2FA, registration and pending-status screens; platform lodge list/create/edit, feature assignment, and registration review screens; and lodge identity, branding, and active-lodge switching screens. Phase 10 separates platform module availability from the lodge preference and adds the lodge-side setting authority. Existing routes remain server-authorized.

## Authorization

Use the matrix in [authorization.md](authorization.md). Policies must load the target resource and verify its lodge rather than trusting route or session context.

## Background Jobs and Email

Email verification, password reset, invitations, and registration-decision notices are queued and carry recipient and lodge context where applicable. Mailpit captures all local mail.

## Import and Export

No domain import is required. Seeders create synthetic Lodge A/Lodge B scenarios. Database and media volumes have documented backup/reset behavior, but lodge-scoped exports are deferred.

## Local Environment

Docker Compose provides Nginx at `http://localhost`, PHP 8.4 PHP-FPM, PostgreSQL, Redis, a queue worker sharing the application image, Node.js/Vite, and Mailpit. Persistent volumes retain database data and media. Documentation must include setup, start/stop, logs, tests, migrations, seeding, frontend, queues, and deliberate reset commands.

## Automated Tests

Implement the master plan's Phase 1 test list using Laravel tests plus Playwright for the critical browser flow. Include two-tenant negative tests, registration decisions, audit contents, email verification, 2FA enabled/disabled configurations, media ownership, status reactivation rules, and clean Docker startup health.

## Manual Acceptance

Execute the eight master-plan acceptance steps, then register and verify a user, confirm the pending restriction, approve and reject sample registrations, exercise 2FA both enabled and disabled, inspect Mailpit messages, restart containers to confirm persistence, and perform the documented deliberate reset.

## Non-Goals

Public lodge sites, full membership profiles, events, newsletters, scholarships, ritual tracking, games, production deployment automation, official Indiana Grand Lodge asset selection, and Newburgh data migration are excluded.
