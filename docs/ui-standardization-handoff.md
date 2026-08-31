# UI Standardization Handoff

## Assignment

Use the Terra model to apply the established UI Review visual system to every existing
portal screen. Treat `/platform/ui-review` as the living reference page. Keep that page and
its platform-admin-only route, but do not add it to application navigation.

This is a presentation refactor. Do not change domain behavior, authorization, tenancy,
privacy, validation, routes, request payloads, or Phase 9 discovery contracts.

## Current State

The shared foundation has already been softened and validated:

- Semantic light/dark tokens live in `resources/css/app.css`.
- Existing legacy helpers (`primary-button`, `secondary-button`, `icon-button`, and
  `field-input`) now use those tokens.
- Shared primitives for buttons, cards, inputs, checkboxes, dialogs, menus, and tooltips
  use the same softer surfaces and borders.
- `resources/js/components/PageHeader.vue` provides standard page hierarchy and actions.
- `resources/js/components/ui/badge/Badge.vue` provides semantic status treatments.
- `resources/js/pages/platform/UiReview.vue` demonstrates intended output in light and
  dark appearance.
- `docs/admin-ui-patterns.md` records usage rules.

Initial inventory found roughly 191 direct neutral-color utility references and 278 uses of
legacy form/action helpers. Legacy helper use is acceptable because those helpers share the
new tokens. Direct `slate-*`, `gray-*`, `neutral-*`, `bg-white`, and `text-black` use should
normally be migrated.

The worktree may contain unrelated user changes. Inspect `git status --short` and relevant
diffs before editing. Preserve every unrelated change. Do not reset, restore, clean, stage,
or commit files.

## Visual Contract

Use semantic intent consistently:

| Purpose                 | Preferred implementation                                      |
| ----------------------- | ------------------------------------------------------------- |
| Page canvas             | `bg-background text-foreground`                               |
| Grouped content         | `Card` or `border-border/80 bg-card`                          |
| Page heading            | `PageHeader`                                                  |
| Supporting copy         | `text-muted-foreground`                                       |
| Quiet grouping/header   | `bg-muted` with restrained opacity                            |
| Main action             | default `Button` or `primary-button`                          |
| Neutral action          | outline `Button` or `secondary-button`                        |
| Icon action             | icon-sized `Button` or `icon-button`                          |
| Status                  | `Badge` variant based on meaning                              |
| Form control            | `Input`, `Label`, `Checkbox`, or aligned legacy field helpers |
| Overlay                 | shared `Dialog`, `DropdownMenu`, or `Tooltip`                 |
| Error/destructive state | semantic `destructive` colors                                 |

Rules:

- Keep one obvious primary action per page or modal.
- Prefer subdued borders (`border-border/80` or lower) and semantic surfaces.
- Do not add pure white/black or new hard-coded neutral colors.
- Preserve meaningful domain colors such as warning, success, or destructive states, but
  express them with accessible light/dark variants.
- Preserve phone-card and tablet-table behavior from `docs/admin-ui-patterns.md`.
- Keep keyboard controls, focus styles, labels, error messages, and ARIA attributes intact.
- Do not turn non-interactive status icons into buttons.
- Avoid broad mechanical replacement when a color communicates domain meaning.
- Do not create a large generic data-table abstraction during this pass. Standardize its
  visual vocabulary first; extract only when repeated behavior is truly identical.

## Execution Order

Work in small, reviewable batches. Run focused validation after each batch and inspect the
diff before continuing.

### Batch 1 — Shared Shell and Common Components

Review application layout, navigation, appearance controls, shared headings, tabs, links,
errors, empty states, and reusable feature components. Replace direct neutral colors with
semantic tokens. Do not alter navigation visibility or permissions.

Primary locations:

- `resources/js/components`
- `resources/js/layouts`
- `resources/css/app.css`

### Batch 2 — Phase 9 Discovery Surfaces

Standardize public lodge discovery, regional events, group landing pages, directory, and
ritual assistance first. These surfaces should visibly agree on cards, filters, badges,
empty states, pagination, and responsive layouts.

Primary pages:

- `resources/js/pages/public/Lodges.vue`
- `resources/js/pages/public/RegionalEvents.vue`
- `resources/js/pages/public/LodgeGroup.vue`
- `resources/js/pages/directory/Index.vue`
- `resources/js/pages/directory/Show.vue`
- `resources/js/pages/ritual/Assistance.vue`
- `resources/js/pages/ritual/AssistanceDetail.vue`

Do not change Phase 9 filtering, visibility, eligibility, consent, safe projection, or
member-affiliation behavior.

### Batch 3 — Platform and Lodge Administration

Apply `PageHeader`, shared controls, badges, cards, table headers/rows, phone cards,
validation, and action menus consistently.

Primary areas:

- `resources/js/pages/platform`
- `resources/js/pages/people`
- `resources/js/pages/officers`
- `resources/js/pages/roles`
- `resources/js/pages/lodge`

Keep `/platform/ui-review` out of navigation. Keep its direct route available to platform
administrators as a visual reference.

### Batch 4 — Events, Communications, and Published Content Management

Standardize management lists, editors, forms, modals, warnings, tables, and responsive
cards.

Primary areas:

- `resources/js/pages/events`
- `resources/js/pages/communications`
- `resources/js/pages/newsletters`
- `resources/js/pages/galleries`
- `resources/js/pages/website`
- Related components under `resources/js/components`

Preserve existing admin UI modal and responsive conventions.

### Batch 5 — Authentication, Settings, and Remaining Public Screens

Finish authentication, settings, dashboard, welcome, lodge public website, public event,
newsletter, and token-action screens. Public lodge branding may intentionally differ from
the administrative shell; standardize controls and accessibility without erasing tenant
branding.

Primary areas:

- `resources/js/pages/auth`
- `resources/js/pages/settings`
- `resources/js/pages/public`
- `resources/js/pages/Dashboard.vue`
- `resources/js/pages/Welcome.vue`

### Batch 6 — Consistency Audit

Search for remaining drift:

```powershell
rg -n 'slate-|gray-|neutral-|bg-white|text-black' resources/js -g '*.vue'
rg -n 'rounded-(md|lg).*border|border.*rounded-(md|lg)' resources/js -g '*.vue'
rg -n 'primary-button|secondary-button|icon-button|field-input' resources/js -g '*.vue'
```

Review every result. Some direct colors may be intentional, especially tenant-branded
public content or semantic success/warning states. Document intentional exceptions instead
of forcing a replacement.

## Validation

For each batch:

```powershell
npx prettier --check <changed Vue/TS/CSS files>
npm run typecheck
npm run lint
git diff --check
```

Before final handoff:

```powershell
npm run build
npm run typecheck:e2e
```

Run focused Playwright coverage for touched critical paths when the environment is
available. At minimum, manually inspect representative phone and tablet/desktop widths in
both light and dark modes. Verify dialogs, dropdowns, forms, errors, empty states, tables,
hover/focus states, and disabled controls.

Do not run backend tests unless PHP/backend files change. If a template or Inertia contract
changes unexpectedly, stop and reassess scope.

## Completion Criteria

- Existing screens use semantic surfaces, borders, text, and actions consistently.
- Light and dark modes feel related and avoid harsh black/white inversion.
- Common page headers and statuses use `PageHeader` and `Badge` where appropriate.
- Direct neutral utilities are removed or documented as intentional exceptions.
- Phone-card/tablet-table behavior remains intact.
- No authorization, tenancy, privacy, filtering, payload, or route behavior changes.
- UI Review remains admin-only by direct URL and absent from navigation.
- Typecheck, lint, build, browser typecheck, formatting, and whitespace checks pass.

## Terra Prompt

Use this prompt when handing off:

> Read `AGENTS.md`, `docs/admin-ui-patterns.md`, `docs/phase-09.md`, and
> `docs/ui-standardization-handoff.md` completely. Apply the UI Review visual system across
> all existing portal screens in the documented batches. Preserve unrelated worktree
> changes and all domain, authorization, tenancy, privacy, route, validation, and payload
> behavior. Keep `/platform/ui-review` available only by direct platform-admin URL and out
> of navigation. Use semantic tokens and existing shared primitives, add small reusable
> components only when behavior is genuinely shared, validate each batch, and report files,
> checks, intentional exceptions, and remaining risks.
