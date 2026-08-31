# Administrative UI Patterns

This guide records the interface patterns established by the People and Lodge
Communications workspaces. Apply them to new administrative screens and use
them when modernizing existing ones.

## Shared visual foundation

Use semantic theme utilities instead of direct neutral colors. This keeps light and dark
appearance aligned and lets palette changes flow through the whole portal.

- Page canvas: `bg-background text-foreground`.
- Grouped surface: shared `Card`, or `bg-card border-border/80` when a component is not
  appropriate.
- Supporting copy: `text-muted-foreground`.
- Quiet row/header background: `bg-muted` with optional opacity.
- Main action/link: `primary`; destructive actions: `destructive`.
- Do not introduce new `slate-*`, pure white, pure black, or feature-specific neutral
  colors in application screens.

Prefer shared components before writing equivalent utility strings:

- `PageHeader` for page title, description, eyebrow, and right-aligned actions.
- `Button` for actions; use default once for the main action and `outline` for neutral
  actions.
- `Card` for grouped content.
- `Input`, `Label`, and `Checkbox` for form controls.
- `Badge` for compact statuses. Use `default`, `secondary`, `muted`, `warning`, or
  `destructive` by meaning, not by arbitrary color preference.
- `Progress` for an accessible numeric range and `ProgressSummary` when the bar needs a
  visible label, formatted amount, and supporting context.
- `Dialog`, `DropdownMenu`, and `Tooltip` for overlays.

Legacy screens may use `primary-button`, `secondary-button`, `icon-button`, and
`field-input`. These helpers use the same semantic tokens, but new Vue work should prefer
the shared components. Review patterns in `/platform/ui-review` in both light and dark
appearance before shipping a new administrative screen.

## Responsive model

Build two intentional layouts only:

- **Phone portrait** is the layout below the `md` breakpoint. Prefer cards,
  stacked information, and actions at the bottom-right of each card.
- **Tablet landscape and wider** is the layout at `md` and above. Use the same
  table or grid-row layout at every larger size; do not introduce a separate
  desktop-only layout.
- Do not rely on horizontal scrolling for management records or editable matrices on
  phones. Keep the tablet table at `md` and above, and provide labeled phone cards or
  compact field groups below it using the same controls and state.

At tablet width, show the information needed to scan and act on a record.
Move secondary details to a modal instead of forcing all fields into one row.

## Tabs and collapsible sections

- Use the shared `TabBar` for peer views within one workspace. Keep tab labels short,
  preserve the active route with `aria-current`, and allow the row to scroll horizontally
  on phones.
- Use `WorkspaceTabs` when the available tabs depend on lodge permissions; it applies the
  same `TabBar` presentation after filtering inaccessible routes.
- Use shared `Collapsible`, `CollapsibleTrigger`, and `CollapsibleContent` primitives to
  shorten long pages with repeated or optional sections. Keep the first or most relevant
  section open by default.
- Put the section name, a short count or summary, and a rotating disclosure icon in the
  trigger. The entire trigger row must be keyboard accessible.
- Do not collapse validation summaries, destructive warnings, or information required to
  understand the page's primary action.

## Management-list pages

Use this structure, in order:

1. Page header with title and the primary creation action on the right.
2. A search-and-filter area. Make large filter sets collapsible; leave simple
   lists visible when that is clearer.
3. A tablet table/grid and a separate phone-card layout.
4. A modal for view, create, and edit operations.

### Drag-and-drop navigation trees

- Keep the drag handle, hierarchy marker, page identity, and status metadata together.
  Put row actions in a separate fixed-width region so nesting never shifts the actions.
- Indent only the hierarchy/content region. Also state the parent in text so hierarchy
  does not depend on indentation alone.
- Keep reorder gaps quiet at rest. Expand them into clear horizontal drop targets while
  a row is being dragged, and use a distinct whole-row target for nesting.
- Explain reorder and nesting gestures immediately above the editor. Do not rely on the
  drag handle alone to teach both behaviors.
- Batch navigation changes locally and provide one explicit save-and-publish action. That
  action publishes navigation metadata only; page content should not need republishing.

### Progress and commerce

- Progress bars always have an accessible name and a visible value nearby. Use a numeric
  value and maximum even when the displayed value is formatted as currency or a count.
- Storefront cards show name, price, availability or fulfillment, and the purchasing
  action without hover. Disabled products retain their price and explain why purchasing
  is unavailable.
- Keep order totals in a semantic summary surface and explain pickup, shipping, digital
  delivery, or other fulfillment before checkout.

### Filters

- Apply text search with a short debounce (about 300–450 ms).
- Apply select, checkbox, and sort changes immediately.
- Preserve the current filters in the URL so a refreshed or shared URL retains
  the same list state.
- Use the shared `field-input` style for inputs and selects.
- Provide a clear-filters action for multi-filter screens.

### Tablet tables

- Use sortable headings only for data that has a meaningful server-side sort.
- Every sortable heading includes an ascending, descending, or neutral sort
  indicator and must not wrap into the next column.
- Give compact columns explicit widths; allow the primary name/title column to
  use remaining space.
- Long primary titles may truncate. If truncation hides important text, make
  the title clickable to expand/collapse it within the row.
- Reserve the full action area for every row, even when a conditional action is
  unavailable. This keeps controls vertically aligned.
- Use fixed-size `icon-button` controls for row actions and put related actions
  in an `inline-flex`/`flex` group with a small gap.
- Status indicators that are not clickable, such as the linked-account or
  deceased indicators, are icons without button background or border.

### Phone cards

- Let the primary name/title use the full first row.
- Present key facts in labeled two-column sections where the values are short;
  otherwise stack them.
- Place action icons in a distinct row at the card’s bottom-right.
- Keep the same actions and permissions as the tablet row.

## Modals

Create, edit, and view workflows stay in a modal. Do not add a full-page edit
route merely because the record has many fields.

- A view modal may offer an Edit action when allowed.
- Save/create actions that complete the primary workflow close the modal and
  return the user to the list. Auxiliary actions within a modal, such as photo
  upload or membership preference saving, keep it open.
- Modal content must scroll within the viewport (`DialogScrollContent`) rather
  than extending beyond it.
- Keep all permissions enforced by the server; hiding a button is only a UX
  improvement, not authorization.
- When selection lists would be unwieldy, use a nested modal with a searchable,
  filterable, sortable table and checkboxes. Keep different recipient types
  distinct in submitted data even when they appear in one list.

## Form grouping

Group fields by the user’s mental model, not by database column order.

- Use short section headings and `field-label` / `field-input` consistently.
- On tablet, related short inputs may share a row. On phone, they stack.
- Give address lines their own rows; group city, state, and postal code.
- Put related flags inline with the relevant section heading (for example,
  Deceased with Personal status and Award of Gold with Lodge records), rather
  than giving a checkbox an unexplained grid cell.
- Keep dependent fields adjacent to their trigger. Show the death-date field
  only when Deceased is selected; do not imply a death date is required when it
  is not.
- For complex domains, use clearly titled subsections. The People membership
  form uses Membership standing, Masonic dates, Lodge records, and notes.
- Prefer a controlled select when values come from a known catalog, such as
  relationship type, membership status, degree, or name suffix.

### Relationship entry

Use one consistent relationship direction. The People modal records the
statement as:

`{current person} is {relationship type} of {related person}`

Show that statement in the form: place the relationship type directly after
the current person’s name, then select the related person on the next row. If
creating a new related non-member, show that person’s fields and place the
submit action below the final field, aligned to the right.

## Buttons and dark mode

- Use `primary-button` for the main action on a screen or modal.
- Use a bordered `bg-card` secondary button for neutral actions.
- Use `icon-button` for clickable icon controls. It intentionally has a card
  background and border so it remains distinguishable from dark page surfaces.
- Use destructive color and border treatment for destructive actions.
- Do not style non-interactive status icons as buttons.

## Example references

- `resources/js/pages/communications/Index.vue`
- `resources/js/pages/people/Index.vue`
- `resources/js/components/people/PersonModal.vue`
