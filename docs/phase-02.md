# Phase 2 — Public Lodge Website and Content Management

## Outcome

Each active lodge can build, preview, publish, and independently operate a responsive, branded public website from reusable content sections. Lodge administrators do not need developer assistance or access to arbitrary code.

## Schema Scope

Phase 2 introduces the following lodge-owned records:

- `website_pages`: stable page identity and lodge ownership.
- `website_page_versions`: draft, published, and archived versions of page metadata and navigation placement.
- `website_sections`: ordered, typed content attached to one page version.
- `media_assets`: uploaded files with explicit lodge ownership, uploader, storage path, MIME type, size, alternative text, and public/private visibility.

The `lodges` record gains the remaining public-brand fields: seal image and tag line. Existing logo, primary color, secondary color, contact, meeting, status, and slug fields remain the source of lodge identity.

Critical constraints include:

- Page slugs are unique within a lodge for each active draft or published navigation state.
- The same page slug is allowed in different lodges.
- Each page has at most one current draft and one current published version.
- Each lodge has at most one published home page.
- Every version, section, and lodge media row carries a lodge identifier even when its parent already identifies the lodge.
- Database constraints and application validation prevent a page, version, section, navigation parent, or media asset from crossing lodge boundaries.
- Deletion is rejected for the published home page and for records still required by a published navigation tree.
- Pages and media assets use soft deletion. Their schema must support a future scheduled purge of records and files that have remained deleted for at least 30 days.

Publishing promotes a validated draft in one transaction, archives the prior published version, and preserves enough history to audit what changed. Editing a published page creates or updates a draft; public visitors continue to receive the published version until the draft is published. See [ADR 0004](decisions/0004-versioned-public-content.md).

## Domain Rules

- Public content is resolved from an explicit lodge slug and never from active authenticated lodge context.
- Disabled and disabled-and-locked lodges have no publicly accessible site. Authorized administrators may still preview their drafts.
- Only published versions appear in public navigation or public page responses.
- Unpublishing a page removes it from public routing and navigation without deleting its draft or history.
- Navigation may be nested to arbitrary author-created depth, but writes must reject cycles and cross-lodge parents.
- Hidden pages remain publicly addressable only when published; they are omitted from generated navigation.
- Navigation order is deterministic among siblings.
- A template is a starting point. Applying it creates ordinary lodge-owned pages and sections with no permanent coupling to the template.
- Lodge branding must preserve accessible contrast. The supported design system may adjust how a supplied color is used rather than rendering inaccessible text.
- Rich text and custom HTML are sanitized on the server. Scripts, inline event handlers, unsafe URLs, and arbitrary JavaScript are rejected.
- Lodge administrators may use only the supported section catalog. Custom HTML sections are restricted to platform administrators.
- Feature-driven placeholder sections render an intentional unavailable or empty state until their module exists; they must not expose another lodge's data.
- Page media must be owned by the same lodge or explicitly marked as platform-shared.
- Managed content stores route targets or lodge-relative paths, not absolute lodge-domain URLs.
- Page creation/deletion, publication state, navigation changes, template application, public-brand changes, and custom HTML changes are audited with actor, lodge, subject, and relevant before/after state.
- Deleting a page immediately removes it from preview selection, public routing, and navigation while retaining its versions for recovery. A deleted page may be restored before future permanent cleanup.
- Media referenced by a current draft or published version cannot be deleted. Unreferenced media is soft-deleted before any later physical purge.

## Section Catalog

The initial catalog contains:

- Hero.
- Rich text.
- Image.
- Image with text.
- Link list.
- Call to action.
- Meeting information.
- Contact information.
- Officers placeholder.
- Upcoming events placeholder.
- Newsletter placeholder.
- Gallery placeholder.
- Sanitized custom HTML for platform administrators only.

Each section type has a server-owned validation contract and a matching Vue renderer/editor. Section configuration may be stored as JSON, but arbitrary keys are not accepted. Media references are stored as asset identifiers and validated independently of client input.

Rich-text sections use [Tiptap](https://github.com/ueberdosis/tiptap) with a deliberately limited toolbar. The selected packages use the no-fee MIT license. PrimeVue Editor with Quill was evaluated first, but the available Quill releases did not pass the project's dependency scan because of an unresolved HTML-export vulnerability. Editor output is always untrusted input: the server sanitizer remains the canonical security boundary for stored and rendered HTML.

## Templates

The release includes a versioned, platform-owned default template that creates:

- Home.
- About.
- Events placeholder.
- Officers placeholder.
- Contact.

The generated copy is Masonic-oriented but intentionally generic. It may describe fellowship, service, personal growth, and a welcoming place to learn about the lodge, but it must not invent the lodge's history, charities, officers, meeting schedule, or affiliations. Lodge-specific facts come from structured lodge data or remain clearly marked for administrator completion. Feature-backed sections display an intentional placeholder until their later module exists.

Phase 3 replaces the Officers placeholder's fallback with a lodge-isolated projection of current public officer assignments. The placeholder remains the Phase 2 behavior when no such data exists.

Template definitions live in application-controlled configuration or code and are covered by tests. Applying a template is idempotent only when explicitly requested for an empty site; it must not overwrite existing lodge content silently.

## Routes and UI

Initial public URLs use the platform host and lodge slug:

- `GET /l/{lodge:slug}` — published home page.
- `GET /l/{lodge:slug}/{page:slug}` — published non-home page.

Public routes use route-model binding by slug, reject disabled lodges, and do not fall back to another lodge or a global page.

These routes are the canonical public handlers rather than permanent visible URL requirements. Future verified custom domains will resolve directly to the same lodge/site handlers without storing `/l/...` URLs in managed content.

Authenticated management is lodge-scoped:

- Website overview and navigation editor.
- Page create, edit, preview, publish, unpublish, and eligible-delete actions.
- Section add, edit, reorder, and remove actions.
- Media upload and selection.
- Default-template application.
- Public branding editor for seal, logo, tag line, and colors.

Management URLs identify the target lodge by database identifier and load the target resource before authorization. Preview is an authenticated, server-authorized route and never relies on a guessable public query parameter.

The public shell and all editors must support desktop and mobile layouts, keyboard operation, visible focus, useful labels, image alternative text, and interactions that do not require hover.

The initial section editor uses ordered cards with explicit move-up and move-down controls. Drag-and-drop may be added later, but it cannot replace keyboard-accessible ordering controls.

## Authorization

Add platform-owned permission definitions for:

- `website.manage` — manage branding, drafts, navigation, sections, and lodge-owned media.
- `website.publish` — publish or unpublish lodge pages.

The built-in Administrator role receives both permissions. Custom lodge roles may receive either permission from the platform catalog. Platform administrators may manage any lodge website and are the only users allowed to create custom HTML sections.

Every write verifies the user's permission for the resource's lodge. Request payloads cannot reassign page, version, section, parent, or media ownership.

See the Phase 2 matrix in [authorization.md](authorization.md).

## Background Jobs and Email

Phone-photo normalization runs as a lodge-aware queued job because decoding and resizing modern images may be expensive. The job carries the lodge and media identifiers explicitly and never depends on an HTTP current-lodge value. A page cannot publish while it references media that has not completed processing successfully.

Publishing is synchronous and transactional so the public site never observes a partially promoted page or navigation tree.

No other background job or email is required for the initial CMS workflow.

## Media Upload Policy

- Accept JPEG, PNG, WebP, HEIC, and HEIF images up to 25 MB and 60 megapixels.
- Reject RAW/DNG, SVG, GIF, video, and non-image uploads in this phase.
- Treat the detected MIME type and successfully decoded image as authoritative; do not trust a filename extension.
- Store the original in private lodge-owned storage for recovery and future reprocessing.
- Correct orientation and generate web-safe display derivatives with bounded dimensions.
- Strip EXIF, GPS, and other unnecessary metadata from every public derivative.
- Keep originals inaccessible from public URLs.
- Record processing state and failure details so an administrator can retry or replace an image.

The 25 MB limit is intended for ordinary high-resolution phone photos, including common iPhone HEIF/JPEG output. [Apple documents](https://support.apple.com/en-us/119916) that ProRAW files can be approximately 25 MB at 12 megapixels and 75 MB at 48 megapixels, so RAW formats are intentionally excluded. The pixel limit independently prevents small, highly compressed files from exhausting memory when decoded.

## Caching

Public rendering may begin without application caching. If caching is introduced, every key includes the lodge identifier and published version identity. Publishing, unpublishing, lodge branding changes, lodge status changes, and navigation changes invalidate only the affected lodge's public entries.

## Import and Export

No legacy content import is required. The schema and media ownership metadata must remain compatible with the lodge-scoped export and Newburgh migration work planned for later phases.

The default template is the supported initial content bootstrap; arbitrary HTML-site import is excluded.

## Automated Tests

Laravel feature tests cover:

- Public resolution for two lodges with different content and branding.
- Disabled and disabled-and-locked public behavior.
- Published, draft, archived, hidden, and unpublished states.
- Draft edits remaining invisible until publish.
- Slug uniqueness within one lodge and reuse across lodges.
- Home-page uniqueness.
- Nested navigation ordering, hidden pages, and cycle rejection.
- Direct URL and identifier manipulation across lodges.
- Payload attempts to change lodge, parent, version, or media ownership.
- `website.manage` and `website.publish` independently.
- Platform-only custom HTML.
- Rich-text and custom-HTML sanitization, including scripts, event handlers, and unsafe URLs.
- Same-lodge, cross-lodge, platform-shared, private, and missing media references.
- Phone-photo validation, MIME spoofing, pixel limits, orientation, derivative generation, metadata removal, processing failures, and retry behavior.
- Placeholder fallback behavior before related modules exist.
- Transactional publishing and audit records.
- Template application and protection of existing content.
- Public cache isolation and invalidation if caching is enabled.

Playwright covers the critical browser path: configure two differently branded lodges, apply the template, edit and reorder sections, preview a draft, publish both sites, create a nested custom page, hide and unpublish pages, and verify the public desktop/mobile experiences remain isolated.

## Manual Acceptance

Execute the master plan's nine Phase 2 acceptance steps, then also:

1. Confirm a published page remains unchanged while its draft is edited.
2. Confirm preview requires an authorized account.
3. Confirm a manager without `website.publish` cannot publish.
4. Attempt cross-lodge page, navigation-parent, and media identifiers.
5. Insert unsafe rich text and confirm it is removed or rejected.
6. Render every initial section type, including feature placeholders.
7. Navigate the public site and editor using keyboard-only controls.
8. Check readable contrast and layout with two distinct branding palettes at mobile and desktop widths.
9. Disable each lodge status and verify public and preview behavior.
10. Upload representative JPEG, PNG, WebP, HEIC, and HEIF phone photos near the size and pixel limits; confirm safe derivatives and inaccessible originals.

## Non-Goals

- Custom domains or TLS automation.
- Member portal functionality.
- Real event, officer, newsletter, or gallery management. Officer management arrives in Phase 3; the other modules remain later work.
- Lodge-authored JavaScript, CSS, templates, or arbitrary HTML.
- General-purpose site builders or third-party page-builder plugins.
- Legacy website import.
- Interactive image editing or a full digital-asset-management system. Automatic orientation, normalization, metadata removal, and display-derivative generation are included.
- Permanent deletion and the scheduled 30-day cleanup job; this phase provides soft deletion and the data needed for that future job.
- Public comments, forms, analytics, or search.
