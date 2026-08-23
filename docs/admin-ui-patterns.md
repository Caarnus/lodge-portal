# Administrative UI Patterns

This guide records the interface patterns established by the People and Lodge
Communications workspaces. Apply them to new administrative screens and use
them when modernizing existing ones.

## Responsive model

Build two intentional layouts only:

- **Phone portrait** is the layout below the `md` breakpoint. Prefer cards,
  stacked information, and actions at the bottom-right of each card.
- **Tablet landscape and wider** is the layout at `md` and above. Use the same
  table or grid-row layout at every larger size; do not introduce a separate
  desktop-only layout.

At tablet width, show the information needed to scan and act on a record.
Move secondary details to a modal instead of forcing all fields into one row.

## Management-list pages

Use this structure, in order:

1. Page header with title and the primary creation action on the right.
2. A search-and-filter area. Make large filter sets collapsible; leave simple
   lists visible when that is clearer.
3. A tablet table/grid and a separate phone-card layout.
4. A modal for view, create, and edit operations.

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
