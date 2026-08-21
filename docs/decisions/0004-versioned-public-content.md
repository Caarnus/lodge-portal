# ADR 0004: Versioned Public Content with Controlled Sections

- Status: Accepted
- Date: 2026-08-20

## Context

Lodge administrators need to edit and preview public pages without exposing incomplete changes. Public visitors must receive a coherent page and navigation tree while a lodge administrator is working. The platform also needs a supported section catalog, tenant-safe media, server-side sanitization, and future lodge-scoped exportability.

A single mutable page record would either expose edits immediately or require duplicating draft and published fields throughout the schema. Storing complete pages as unvalidated arbitrary JSON or HTML would weaken validation, authorization, accessibility, and migration guarantees.

## Decision

Use stable lodge-owned page identities with separate version records for draft, published, and archived states. Ordered section records belong to a specific page version and use platform-defined section types with server-owned validation contracts.

Publishing is a transaction that validates the complete draft and its navigation/media references, archives the previous published version, and promotes the draft. Public rendering reads only published versions. Editing published content occurs in a draft, so public output does not change until publication.

Media assets carry explicit lodge ownership and visibility. A section may reference media owned by the same lodge or intentionally platform-shared media. Rich text and platform-admin custom HTML are sanitized on the server; lodge administrators cannot submit arbitrary HTML, CSS, or JavaScript.

The section editor initially presents ordered cards with move-up and move-down controls. Rich text uses [Tiptap](https://github.com/ueberdosis/tiptap) with a restricted toolbar and no-fee MIT-licensed packages. PrimeVue Editor with Quill was evaluated first, but no available Quill release passed the dependency scan because of an unresolved HTML-export vulnerability. Server-side sanitization is required regardless of editor behavior.

Pages and media assets are soft-deleted. A future scheduled job may permanently purge records and files only after they have remained deleted for at least 30 days and are no longer referenced. Uploaded phone photos accept common web and HEIC/HEIF formats within documented size and pixel limits. Originals remain private; queued processing creates oriented, metadata-stripped public derivatives.

The default website template creates normal lodge-owned pages and sections. Created content is not permanently coupled to the template.

## Consequences

- Draft preview and atomic publishing have clear semantics.
- Published content remains stable during editing.
- Page and navigation history can be audited and exported.
- Section editors and renderers require explicit schemas for every supported type.
- Publishing and navigation changes require transactional validation.
- Storage uses more rows than a single mutable page document.
- Custom section types require application work and tests, which intentionally preserves the supported design system.
- Media uploads require processing-state UI and a lodge-aware queue job before referenced pages can publish.
- Recoverable deletion consumes storage until a later retention cleanup process is implemented.
