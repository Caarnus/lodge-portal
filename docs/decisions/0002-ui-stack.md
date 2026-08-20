# ADR 0002: PrimeVue Unstyled Mode with Tailwind CSS

- Status: Accepted
- Date: 2026-08-20

## Decision

Use Vue 3 and Inertia with PrimeVue in unstyled mode. Tailwind CSS supplies component styling, layout, responsive behavior, and lodge branding.

## Consequences

The application can use PrimeVue behavior and accessibility foundations without adopting a conflicting visual theme. The project owns its Tailwind design tokens and must test accessible contrast when tenant colors are applied.
