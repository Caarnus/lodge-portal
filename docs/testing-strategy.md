# Testing Strategy

## Layers

- Unit tests cover isolated domain rules and value objects.
- Laravel feature tests cover requests, policies, persistence, validation, mail, jobs, and tenant isolation.
- Playwright tests cover critical browser workflows against the Docker environment.

## Phase 1 Browser Path

Playwright must exercise platform-admin bootstrap/sign-in, creation of two lodges, assignment of separate and multi-lodge administrators, lodge switching, branding updates, blocked cross-lodge access, registration, email verification, pending status, approval/rejection, and configurable 2FA behavior.

## Isolation Pattern

Every lodge-owned capability creates Lodge A and Lodge B fixtures and tests allowed access, direct URL/identifier manipulation, request payload reassignment, and active-context mismatch.

Tests must run from a clean database through documented Docker commands. Browser-test artifacts such as traces and screenshots should be retained on failure but ignored by version control.
