# Phase 7 — Newsletters, Galleries, and Lodge Communications

## Outcome

Each lodge can publish member newsletters, maintain public or protected photo albums, send lodge-wide email messages, and manage electronic and postal newsletter distribution without exposing one lodge's content, contacts, or delivery history to another lodge.

Published newsletters and sent lodge communications are available through the authenticated member portal only. Current lodge members receive newsletter email by default unless their lodge-specific communication preference disables it. A member may request a mailed newsletter through profile settings, and an authorized lodge administrator may maintain the same preference through the person's lodge record. Eligible family members of a living or deceased lodge member may request electronic, postal, or both forms of newsletter distribution without receiving lodge membership, directory visibility, or portal access.

Gallery visibility is album-owned and supports `public`, `masons`, and `lodge`. New albums default to public. Every protected read, media response, recipient selection, delivery, export, job, and write is resolved from an explicit lodge and reauthorizes the complete ownership or eligibility chain.

## Repository Baseline and Scope

Implementation starts from the repository after Phase 6:

- `Person` is the canonical identity/contact record. `Membership` is the lodge-owned member relationship. Family members already exist as people connected through directional `PersonRelationship` records.
- Family relationships retain spouse, child, parent, widow/widower, guardian, and related support context after a member's death. They do not grant membership, authentication, directory visibility, or authorization.
- `membership_communication_preferences.receives_lodge_email` already exists, defaults true, and is explicitly reserved for general lodge and newsletter mail. It does not affect transactional, security, event, reservation, reminder, or volunteer mail.
- Members can maintain their own per-active-membership lodge-email preference. Administrative People remains a separate lodge-authorized recordkeeping boundary.
- Built-in lodge roles and platform-owned permissions are synchronized through `LodgeRoleCatalog`. Officer assignment remains separate from role assignment and grants no application permission by itself.
- The Phase 2 website domain already provides lodge-owned image `media_assets`, private originals, normalized JPEG derivatives, processing status, Tiptap rich text, server sanitization, and newsletter/gallery placeholder section types.
- Existing website derivatives are written to public storage. Phase 7 must add protected derivative delivery before a media asset can safely appear in a non-public album or member-only newsletter. A database `visibility` value without protected storage and an authorized response is not sufficient.
- Existing event and volunteer email jobs demonstrate the required pattern: carry stable identifiers, reload every owner and eligibility fact, use durable delivery rows, and make retries idempotent.
- PostgreSQL is the production database. SQLite behavior does not define partial uniqueness, ownership checks, locking, recipient claiming, or delivery idempotency.

Before implementation, run the existing backend and frontend gates. A failing Phase 6 baseline is a prerequisite defect; do not weaken existing privacy, tenancy, or delivery tests to accommodate Phase 7.

Ritualist taxonomy and implementation belong to Phase 8 and are outside this document.

## Locked Product Decisions

- Newsletters, gallery albums, lodge communications, distribution subscriptions, recipient requests, and delivery history belong to exactly one lodge.
- Newsletter and lodge-communication archives are authenticated member-portal features. No newsletter title, date, cover, body, PDF, communication subject, or communication body is exposed through the public lodge site.
- This member-only newsletter decision supersedes the master outline's earlier public/member-only newsletter visibility option and its public-newsletter acceptance step. Gallery visibility remains independently configurable as defined below.
- A newsletter may contain sanitized rich content, a PDF, or both. At least one is required before publication.
- A newsletter issue has a stable lodge-scoped slug plus separate draft and published versions. Editing published content creates or updates a private draft; the published version remains unchanged until explicit republication.
- `publication_date` is descriptive and interpreted in the lodge's time zone. It does not schedule publication. `published_at` records the actual publication time.
- Publishing and notification are separate domain actions. The publish confirmation defaults to emailing eligible newsletter recipients, but the actor must see and confirm that choice. Publication succeeds independently; recipient snapshot or queue failure is reported and may be retried without republishing.
- Republish does not silently send another email. An explicit confirmed send or resend action is required.
- Current eligible lodge members receive general lodge/newsletter email by default through the existing effective `receives_lodge_email = true` behavior. Every send honors the value effective when its recipient snapshot is created.
- Add `receives_print_newsletter`, default false, to the membership communication preference. A member may maintain it for their own current active membership. A user with `communications.recipients` may maintain both electronic and print preferences through the lodge's administrative Person screen based on the person's request.
- Administrative access never overrides an electronic opt-out during a send. Re-enabling email is a separate audited preference action representing a new request; resend and retry cannot change consent.
- Family of a living or deceased member may request newsletter delivery by email, postal mail, or both. An approved family subscription is separate from membership communication preferences and is valid only while tied to a qualifying Person relationship and a qualifying lodge membership record for the related member.
- A family subscription, request, or newsletter delivery never grants an account, lodge role, membership, directory visibility, event eligibility, or member-portal access.
- General lodge communications are rich-text email messages sent to all eligible current members. They do not include family newsletter subscribers. Phase 7 has no arbitrary recipient lists, segments, marketing journeys, or purchased/imported mailing lists.
- Sent lodge communications become immutable entries in the owning lodge's member-only communication archive. Draft communications are visible only to authorized managers.
- Newsletter emails deliver the complete sanitized rich body when present and attach the PDF when present. A non-member recipient is not sent a protected portal link as a substitute for the requested copy.
- Physical fulfillment uses the uploaded PDF when one exists. A rich-text-only issue provides an authorized print-safe layout of the complete sanitized body. When both exist, the PDF is the canonical physical copy unless the preparing administrator explicitly selects the print-safe rich-content rendition for that run.
- General lodge messages have no file attachments in Phase 7. Links and sanitized rich content are supported.
- Gallery visibility belongs to the album, not to individual photos. Supported values are `public`, `masons`, and `lodge`; default is public.
- `masons` means an approved, verified user linked to a live, non-merged person with at least one current active membership in any active platform lodge. It does not require membership in the album-owning lodge and reveals no membership details.
- `lodge` means the same account/link requirements plus a current active membership in the album-owning lodge.
- Platform-administrator status alone does not grant an ordinary protected-audience read. Authorized management preview is a separate route and permission check.
- Gallery photos use the existing image format, size, pixel, orientation, metadata-removal, and readiness rules. Alternative text is required for every photo; album-specific captions are optional.
- Originals remain private. Public album derivatives may be publicly cached. Masons-only, lodge-only, newsletter-cover, and management-preview derivatives are served through authorized private responses with private/no-store caching.
- Changing an album from public to a protected visibility removes or rotates its public presentation copies before the new visibility commits. A prior public URL must not remain a bypass. Public copies still required by another explicit public reference are not silently deleted; the visibility transition must reject or clone the asset rather than falsely claim that the underlying image became private.
- The shared provider's configured From address remains authoritative. A lodge may configure the display sender name and reply-to/contact addresses, but cannot supply an arbitrary envelope or From address.
- Test messages go only to the initiating user's verified account email and use the same rendering and lodge identity rules as the corresponding production message.
- Phase labels such as `PhaseSeven`, `P7`, or `phase_07` must not appear in implementation filenames, class names, route names, database objects, queue names, or code comments.

## Domain Model

### Newsletter Issues and Versions

Add `newsletter_issues` as stable lodge-owned identities with:

- `id` and `lodge_id`, including unique `(id, lodge_id)` for composite ownership references.
- Stable `slug`, unique within the lodge across non-deleted issues.
- Nullable creator identifier.
- Timestamps and soft deletion.

Add `newsletter_issue_versions` with:

- `id`, `lodge_id`, and `newsletter_issue_id` with a composite foreign key to the owning issue.
- Status: `draft`, `published`, or `archived`.
- Title.
- Descriptive `publication_date`.
- Nullable same-lodge cover media identifier.
- Nullable sanitized `body_html`.
- Nullable same-lodge newsletter-document identifier.
- Creator, publisher, `published_at`, and timestamps.

Require at most one current draft and one current published version per issue using PostgreSQL partial uniqueness. A publication transaction validates the complete draft, archives the prior published version, promotes the draft, and creates a new editable draft copy only when editing next begins. Validation failure leaves the prior publication unchanged.

Changing a title does not change the stable issue slug automatically. Slug changes are explicit and reject collisions within the lodge. Public-route slug behavior is irrelevant because newsletter routes require authentication, but stable slugs remain useful for member links and later migration.

Unpublishing archives the current published version and removes it from member lists without deleting the issue, versions, documents, distribution history, or audit trail. Soft-deleting is allowed only after unpublishing and follows the existing recoverable-content pattern. A document or media asset referenced by a current draft or published version cannot be deleted.

### Newsletter PDF Documents

Add `newsletter_documents` as immutable lodge-owned PDF uploads with:

- `id`, `lodge_id`, uploader, original filename, private storage path, detected MIME type, byte size, SHA-256 digest, timestamps, and soft deletion.
- Unique `(id, lodge_id)` for composite ownership references.

Accept only a successfully identified PDF with a valid PDF signature, a `.pdf` presentation filename, and a maximum size of 10 MiB. Store it on private storage. Do not convert it to HTML, execute embedded content, extract scripts, or create a publicly addressable path. Member downloads reauthorize the published newsletter and return `Content-Type: application/pdf`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, and private/no-store caching.

The 10 MiB limit leaves room for email transport encoding and the surrounding message within ordinary provider limits. If provider configuration cannot safely attach the stored document, recipient snapshot creation must fail visibly before any production deliveries are claimed; it must not send members an incomplete issue silently.

### Gallery Albums, Versions, and Photos

Add `gallery_albums` as stable lodge-owned identities with:

- `id`, `lodge_id`, stable lodge-scoped slug, creator, timestamps, and soft deletion.
- Unique `(id, lodge_id)` and active lodge/slug uniqueness.

Add `gallery_album_versions` with:

- `id`, `lodge_id`, and `gallery_album_id` with composite ownership.
- Status: `draft`, `published`, or `archived`.
- Title and optional sanitized/plain-text description.
- Visibility: `public`, `masons`, or `lodge`, default public.
- Nullable cover-photo relationship, creator, publisher, `published_at`, and timestamps.

Add `gallery_album_photos` with:

- `id`, `lodge_id`, album-version identifier, and same-lodge media-asset identifier.
- Required alternative text stored canonically on the media asset and an optional album-specific caption.
- Deterministic non-negative `sort_order` unique within the album version.
- Timestamps and composite ownership constraints.

Use one current draft and one current published version per album. Editing title, description, visibility, cover, captions, or order after publication changes only the draft until explicit republication. Publishing requires every referenced asset to be ready and owned by the same lodge. The cover must identify a photo in the same version; when absent, the first ordered photo is the presentation cover.

A gallery media asset may be referenced by multiple draft versions, but a publication must resolve whether the asset has any other public presentation. A protected album must never claim that an asset is private while exposing the same derivative through an untracked public URL. Centralize reference checks across website sections, event covers, newsletter covers, and gallery versions.

### Lodge Communications

Add `lodge_communications` for general member messages with:

- `id`, `lodge_id`, status `draft`, `sending`, `sent`, or `cancelled`.
- Subject and sanitized rich `body_html`.
- Creator, last editor, sender, `send_requested_at`, `sent_at`, timestamps, and soft deletion limited to unsent drafts.
- Unique `(id, lodge_id)`.

Drafts may be edited by `communications.send`. Starting a production send freezes subject and body, changes the message to `sending`, and creates the recipient snapshot transactionally. A sent message is immutable. To change or repeat it, the actor duplicates it into a new draft; retrying failed deliveries does not create a new message or resend successful deliveries.

The authenticated member archive lists only sent messages for the explicitly requested lodge. Eligibility is reevaluated on every archive/detail request; having received an old email does not grant portal access after a membership ends.

### Lodge Communication Settings

Add one `lodge_communication_settings` row per lodge with:

- `lodge_id`, unique.
- Nullable `sender_display_name`.
- Nullable `reply_to_email`.
- Nullable `secretary_email`.
- Nullable `newsletter_contact_email`.
- Nullable updater, timestamps.

Effective values use these fallbacks:

1. Sender display name: configured value, otherwise lodge name.
2. General reply-to: configured reply-to, otherwise secretary email, otherwise existing `lodges.public_email`.
3. Newsletter reply-to/contact: newsletter contact, otherwise general reply-to.
4. Secretary/general contact display: secretary email, otherwise existing `lodges.public_email`.

All addresses are normalized and validated. The UI states that these fields affect reply/contact handling, while `config('mail.from.address')` remains the authenticated From address. Settings changes do not alter already snapshotted or sent message headers.

### Membership Newsletter Preferences

Extend `membership_communication_preferences` with:

- `receives_print_newsletter`, default false.
- Nullable timestamps or actor metadata already supplied by the preference row/audit system; do not add a second competing preference table for members.

Effective missing-row defaults become:

- `receives_lodge_email = true`.
- `receives_print_newsletter = false`.

The email preference controls general lodge messages and newsletter email. The print preference controls newsletter postal delivery only. Neither affects authentication/security email, registration decisions, event mail, event reminder subscriptions, volunteer reminders, or another lodge's preferences.

Self-service changes require the user's valid linked person and the exact current active membership. Administrative changes require `communications.recipients`, a person reachable through the route lodge, and that person's membership in the same lodge. Store no client-supplied person/lodge ownership values.

### Family Newsletter Subscriptions

Add `family_newsletter_subscriptions` with:

- `id`, `lodge_id`, recipient `person_id`, sponsoring member `person_id`, and qualifying `person_relationship_id`.
- `receives_email` and `receives_print`, requiring at least one true.
- Status `active`, `unsubscribed`, or `inactive`.
- Consent/request source, requested timestamp, approver/updater, optional bounded administrative note, unsubscribe timestamp, timestamps.
- Unique active subscription for `(lodge_id, recipient_person_id)`.

An active family subscription requires all of:

1. Recipient is a distinct, non-deceased, non-merged, non-deleted Person.
2. The relationship connects recipient and sponsoring person in either stored direction and has not been deleted.
3. Sponsoring person has a membership owned by the subscription lodge whose status key is `active` or `deceased`. A deceased sponsor may have an end date; an active sponsor must satisfy the ordinary current-membership rule.
4. Recipient is not already a current active member of that lodge. If they become one, deactivate the family subscription and use membership preferences.
5. Email channel has a valid canonical recipient email; print channel has a complete canonical mailing address.

The qualifying relationship types are spouse, child, parent, widow/widower, and guardian plus future active family relationship types explicitly approved by the platform catalog. Do not infer family from surname, address, notes, emergency contacts, or a submitted name.

Revalidate eligibility when approving, editing, snapshotting a distribution, merging people, deleting relationships, changing the sponsor membership, or marking either person deceased. A sponsor's death does not deactivate an otherwise valid family subscription. Relationship removal, recipient death, unresolved merge, or loss of a qualifying membership state prevents future deliveries without deleting history.

### Family Distribution Requests

Add `family_newsletter_requests` so a non-member can request email, print, or both without being granted account access. Store:

- Lodge owner and requested channels.
- Requester's name, email when supplied, structured mailing address when supplied, claimed relationship, and claimed related-member name.
- Status `pending_verification`, `pending_review`, `approved`, `rejected`, or `expired`.
- Hashed email-verification token when email is requested, bounded expiry, requester IP/user-agent metadata appropriate for abuse review, reviewer, review timestamps, and optional bounded review note.
- Nullable resulting family-subscription identifier after approval.

The public request endpoint is lodge-slug scoped, throttled, uses a honeypot or equivalent low-friction abuse control, and returns a non-enumerating response. It never confirms whether the named member/person/relationship exists. Email or combined requests must verify control of the submitted email before review. Postal-only requests proceed directly to manual review because address possession cannot be safely verified automatically.

Approval does not silently create a free-floating subscription from request text. An authorized reviewer must select or create the canonical recipient Person through the existing People workflow, select the sponsoring member and qualifying relationship, correct canonical contact/address data when authorized, and then activate the subscription. Rejection/expiry creates no person, relationship, membership, role, or subscription.

Expire unverified requests after 48 hours. Retain approved requests with the subscription audit. Purge rejected, expired, and abandoned request contact data after 90 days through a scheduled cleanup command while retaining a minimal non-PII audit event.

### Distribution Runs and Deliveries

Add `communication_distribution_runs` with:

- `id`, `lodge_id`, kind `newsletter` or `general_message`.
- Exactly one same-lodge source: published newsletter version or frozen lodge communication.
- Status `preparing`, `ready`, `sending`, `completed`, `completed_with_failures`, or `cancelled`.
- Initiator, timestamps, recipient counts by channel/status, and a unique idempotency key.

Add `communication_deliveries` with:

- `id`, `lodge_id`, distribution-run identifier.
- Channel `email` or `postal`.
- Exactly one recipient source: membership or family subscription. General messages permit membership sources only.
- Recipient name plus normalized email or structured postal address snapshot. Store only the fields needed to deliver and audit that issue.
- Status `pending`, `claimed`, `sent`, `failed`, `skipped`, `prepared`, or `mailed` as valid for the channel.
- Attempt/claim timestamps, bounded failure or skip reason, sent/prepared/mailed timestamps, and provider message identifier when available.
- Hashed single-purpose unsubscribe token for email deliveries.
- Unique recipient/channel within one run and composite lodge ownership.

Recipient snapshots are immutable. They explain what was attempted but do not authorize a retry. Before every email send, reload the source, lodge, recipient source, current eligibility, and current preference/subscription state. An opt-out or eligibility change after snapshot but before send changes the delivery to skipped. Postal export similarly revalidates before marking a row prepared.

Do not serialize recipient snapshots into queue payloads. Jobs carry only the delivery identifier. Claim work atomically, recover stale claims, and guarantee that dispatcher overlap, queue retry, explicit retry, or repeated commands cannot send a successful delivery twice.

## Audience and Read Authorization

### Newsletter and Communication Portal Reads

Newsletter and sent-communication list/detail/download routes require all of:

1. Active lodge.
2. Approved and email-verified user.
3. User linked to a non-deleted, non-merged, living Person.
4. Current active membership in the explicit route lodge.

Ordinary member archive access is membership-based and does not require `newsletters.manage` or `communications.send`. Those permissions control management only. `current_lodge_id`, an email delivery, a family subscription, a family relationship, a prior membership, a guessed slug, and platform-admin status are not substitutes.

An ineligible requester receives 403 at the archive boundary. A well-formed but unpublished, deleted, foreign-lodge, or otherwise invisible issue/message returns 404 after requester authorization. PDF and protected cover reads repeat the same checks and never expose storage paths.

### Gallery Reads

Every album list, detail, and photo response resolves the explicit lodge and published version, then applies its visibility:

- `public`: anyone may view while the lodge is active.
- `masons`: requester meets the cross-platform active-Mason account rule.
- `lodge`: requester additionally has a current active membership in the album-owning lodge.

List queries filter ineligible albums in SQL before counts/pagination. Direct ineligible album/photo identifiers return 404 and reveal neither title nor visibility. Protected photo URLs are album-version/photo scoped, rerun authorization, and use private/no-store caching. A media identifier alone is never a gallery read route.

Changing lodge, membership, account approval/link, person lifecycle, album publication, or visibility takes effect on the next protected request. Do not use a client-side visibility filter, a long-lived signed public URL, or `current_lodge_id`.

## Newsletter Workflow

Authorized users can:

- Create an issue and private draft.
- Set title, stable slug, descriptive publication date, optional cover, rich content, and/or PDF.
- Preview the draft under management authorization.
- Publish, republish, unpublish, restore, and soft-delete eligible issues.
- Send a test copy to their own verified email.
- Start a confirmed electronic and postal distribution for the current published version.
- View run totals and bounded delivery status without receiving a bulk contact export.
- Retry only currently eligible failed email deliveries.
- Open the authorized physical-copy source: the uploaded PDF or a print-safe rich-content rendition.

The publish confirmation has `Email eligible recipients` selected by default and shows that member opt-outs and approved family subscriptions are honored. `Prepare postal distribution` is selected only when eligible member/family print recipients exist and remains an explicit choice because physical fulfillment has cost. The controller publishes transactionally, then invokes recipient snapshot creation after commit. A snapshot failure does not roll back or conceal a successful publication.

Publication email selection includes:

- Current active memberships with effective `receives_lodge_email = true`, a live/non-merged person, and valid canonical email.
- Active eligible family subscriptions with `receives_email = true` and valid canonical email.

Postal selection includes the corresponding member print preferences and family print subscriptions with complete canonical addresses. Deduplicate electronic delivery by normalized email within one run, retaining source attribution for audit. Do not deduplicate postal requests merely because two people share an address; each explicit requested copy remains one delivery.

If rich content and PDF are both present, the email includes the rich content and PDF attachment. If only rich content exists, it is the complete email content. If only a PDF exists, the email includes a concise lodge/issue introduction and the PDF attachment. Email layout adds lodge identity, descriptive publication date, reply/contact information, and the secure unsubscribe action without mutating stored issue content.

Unsubscribing a member delivery sets that membership's `receives_lodge_email` false. Unsubscribing a family delivery sets only that subscription's `receives_email` false and deactivates it only when print is also false. The GET route displays a confirmation; POST performs the change. Tokens are random, stored only as hashes, lodge scoped, single-purpose, non-enumerating, and idempotent.

## General Lodge Communication Workflow

A user with `communications.send` can:

- Create and edit a subject plus sanitized rich-text draft.
- Send a test to their own verified email.
- Review the exact all-current-member audience definition and estimated eligible count.
- Confirm a production send.
- View aggregate progress and authorized delivery failures.
- Retry currently eligible failed deliveries without resending successful rows.
- Duplicate a sent communication into a new draft when a corrected or repeated message is needed.

General message recipient selection includes current active lodge memberships with effective `receives_lodge_email = true`, a live/non-merged Person, and a valid canonical email. It excludes family newsletter subscriptions, ended/inactive/deceased memberships, guests, event subscribers, directory-only cross-lodge users, and arbitrary addresses. Normalize and deduplicate email within the run.

Starting a send freezes the message and its recipient snapshot. The member archive may display the message as soon as it reaches `sent`; `sent` means recipient preparation completed and every currently dispatchable delivery reached a terminal state, including a possible `completed_with_failures` run. The archive does not expose recipient counts, names, addresses, failures, provider identifiers, or unsubscribe tokens.

Email unsubscribe uses the same membership preference behavior as newsletter mail. A general communication cannot be marked urgent or transactional to bypass the preference.

## Gallery and Media Workflow

Authorized users can:

- Create an album whose draft defaults to public visibility.
- Set title, slug, description, visibility, and cover.
- Upload multiple supported photos with required alternative text.
- Add or edit captions.
- Reorder using accessible move controls; drag-and-drop may supplement but not replace them.
- Remove draft photos, retry failed processing, preview, publish, republish, unpublish, restore, and soft-delete eligible albums.

Reuse the Phase 2 25 MB/60-megapixel upload bounds and accepted JPEG, PNG, WebP, HEIC, and HEIF formats. Continue detected-MIME validation, decoded-pixel limits, orientation correction, normalized web derivative creation, and metadata stripping. Add private normalized derivatives for protected delivery rather than serving originals.

Refactor media presentation around a centralized service that knows every draft and published reference. It must:

- Verify lodge ownership for selection and publication.
- Block publication until processing succeeds.
- Produce private derivatives for all gallery-capable assets.
- Materialize a public copy only while at least one published public reference requires it.
- Remove or rotate an unneeded public copy during a public-to-protected transition.
- Reject asset deletion while referenced by a current website page, event, newsletter, or gallery draft/published version.
- Keep soft-deleted originals recoverable under the existing retention direction.

The public Website Gallery section renders a bounded set of recent published public albums for its explicitly resolved lodge. It never includes protected album titles, counts, covers, or empty-state hints. The Newsletter section renders a generic member sign-in call to action and does not reveal issue metadata because newsletters are member-only.

## Permissions and Built-In Roles

Add platform-owned lodge permissions:

- `newsletters.manage` — create/edit drafts, documents, covers, previews, and eligible deletion/restoration.
- `newsletters.publish` — publish, republish, or unpublish newsletter issues.
- `galleries.manage` — create/edit album drafts, photos, captions, order, previews, and eligible deletion/restoration.
- `galleries.publish` — publish, republish, or unpublish albums.
- `communications.send` — compose, test, send, duplicate, monitor, and retry general messages and send published newsletter distributions.
- `communications.settings` — maintain lodge sender/reply/contact settings.
- `communications.recipients` — administer member communication/print preferences, review family requests/subscriptions, prepare postal distributions, and mark postal copies mailed.

Built-in role synchronization is idempotent and preserves custom-role assignments:

- Administrator receives every Phase 7 permission.
- Officer receives `communications.send` so an officer with the actual Officer lodge role can email all eligible lodge members.
- Member and Non-member receive no Phase 7 management permission.
- Existing custom roles retain their configured permissions; lodge administrators may grant the new permissions through the existing role editor.

Officer assignment alone still grants nothing. The existing explicit Officer role prompt is the authorization transition. Newsletter distribution requires a published newsletter plus `communications.send`; it does not require `newsletters.publish`. Draft editing and publication remain independently grantable. Platform administrators may administer any lodge through management routes but are not ordinary protected-audience viewers unless independently eligible.

## Routes and Controllers

Use explicit lodge-scoped routes with conventional names. Exact resource nesting may make minor framework-compatible adjustments, but preserve these boundaries.

### Member and Public Reads

| Method | Route | Purpose |
|---|---|---|
| GET | `/lodges/{lodge}/newsletters` | Member newsletter archive |
| GET | `/lodges/{lodge}/newsletters/{issue:slug}` | Published member issue |
| GET | `/lodges/{lodge}/newsletters/{issue:slug}/cover` | Authorized cover derivative |
| GET | `/lodges/{lodge}/newsletters/{issue:slug}/document` | Authorized PDF download |
| GET | `/lodges/{lodge}/communications` | Sent member communication archive |
| GET | `/lodges/{lodge}/communications/{communication}` | Sent member communication detail |
| GET | `/l/{lodge:slug}/galleries` | Visibility-filtered album index |
| GET | `/l/{lodge:slug}/galleries/{album:slug}` | Visibility-filtered album detail |
| GET | `/l/{lodge:slug}/galleries/{album:slug}/photos/{photo}` | Public or authorized protected derivative |
| GET/POST | `/l/{lodge:slug}/newsletters/request` | Non-enumerating family request flow |
| GET/POST | `/l/{lodge:slug}/newsletters/request/verify/{token}` | Email-channel request verification |
| GET/POST | `/l/{lodge:slug}/communications/unsubscribe/{token}` | Single-purpose lodge-email unsubscribe |

Place the literal public feature prefixes before the Phase 2 catch-all `GET /l/{lodge:slug}/{pageSlug}` route so `galleries` and newsletter action paths cannot be consumed as CMS slugs.

### Management

Management routes live under `/lodges/{lodge}` and the existing authenticated, verified, approved, administrative-2FA boundary:

- Newsletter issue index/create/edit/update/preview/publish/unpublish/delete/restore.
- Newsletter document upload/download/remove and cover selection.
- Newsletter test, send, resend-confirmation, run detail, failed-delivery retry, postal export, and mark-mailed actions.
- Newsletter physical-copy preview/print using the run's selected PDF or rich-content rendition.
- Gallery album index/create/edit/update/preview/publish/unpublish/delete/restore.
- Gallery photo upload/update/move/remove/retry and authorized original download.
- Communication index/create/edit/update/test/send/show/duplicate and failed-delivery retry.
- Communication-settings edit/update.
- Recipient/family-request/subscription index, approve/reject/update/deactivate, and member-preference update.

Controllers load the explicit route lodge and nested resource before authorization. Form requests validate fields; domain services own publication, eligibility, snapshots, delivery state, media exposure, and preference changes. Do not put recipient queries, publication transitions, or media visibility logic in controllers or Vue.

## UI Views and Components

### Member Portal

Add bounded dashboard links/cards for each active lodge:

- Latest published newsletters.
- Recent sent lodge communications.
- Gallery link using the explicit lodge context.

Newsletter and communication archives clearly identify the lodge, descriptive publication/sent date, and content type. They do not display recipient or delivery-administration data. A user with multiple memberships switches through explicit lodge links; active UI context is convenience only.

Profile settings show per-active-membership controls for:

- Receive lodge and newsletter email, default on.
- Receive a mailed newsletter, default off and disabled with a clear prompt until the canonical mailing address is complete.

Explain that email preference affects general lodge/newsletter email but not requested event or account messages, and that postal preference applies only to newsletters.

### Administrative People and Recipients

The lodge Person editor shows the same membership-owned email/print settings only when the actor has `communications.recipients`. It labels the authorizing lodge and warns that changing canonical contact/address data may affect other authorized lodge workflows. Preference changes are separate from identity/contact updates and are audited independently.

The recipient workspace separates:

- Member electronic and print preferences.
- Pending family requests.
- Approved family subscriptions and their qualifying relationship/sponsor.
- Per-issue postal preparation/mailed status.

Do not provide a general bulk export of emails, phone numbers, family relationships, or the member directory. Postal export contains only the revalidated names and mailing fields needed for the selected issue and is audited.

### Management Editors

Newsletter, gallery, and communication editors follow existing responsive Inertia/Vue conventions, server-owned validation, visible focus, useful labels, keyboard operation, light/dark themes, and clear save/publish/send state. Destructive or costly actions require confirmation. Show recipient estimates as estimates until the immutable run snapshot is created.

Send confirmation must distinguish:

- Publishing content.
- Emailing members/family newsletter subscribers.
- Preparing physical copies.
- Sending a general member message.
- Retrying only failed deliveries.

Never use color alone for draft, published, sending, failed, or protected states. Gallery controls expose all three visibility meanings in plain language.

## Email Rendering, Dispatch, and Operations

Use the configured Laravel mail transport and authenticated `config('mail.from.address')`. Apply the effective lodge sender display name and reply-to address at send time. Queue notifications/mailables only after the recipient snapshot commits.

Add a dispatcher command and scheduler entry for pending electronic deliveries. The dispatcher:

1. Selects ready/sending runs in bounded batches.
2. Claims pending or stale-claimed rows atomically.
3. Dispatches one job carrying only the delivery identifier.
4. Reconciles run counts/status idempotently.

The job reloads and verifies the delivery, run, exact source version/message, lodge, current lodge status, recipient source, eligibility, consent, current contact address, and channel. If the canonical email no longer equals the snapshotted normalized address, skip the stale delivery; do not silently send to either address. A newly created run is required to use changed contact information.

On success, record `sent_at` and the provider identifier when available before treating the delivery as terminal. On transport failure, record a bounded safe error and failed status. Logs and audit metadata contain record identifiers and aggregate counts, not message bodies, PDF bytes, unsubscribe tokens, or full recipient lists.

Test sends do not create production deliveries, change communication status, publish content, or consume unsubscribe state. They are throttled and audited with actor, lodge, source type, and destination user identifier rather than a copied email address.

Provider bounce/complaint webhooks, reputation dashboards, inbound email, and provider-specific template systems are not required. Preserve provider message identifiers and a clear extension point for later suppression integration.

## Postal Distribution

Phase 7 maintains preparation and fulfillment state; it does not purchase postage or integrate with a postal vendor.

For a published issue, an actor with `communications.recipients` can create/reuse the issue's postal run, revalidate eligible recipients, and download a CSV containing only:

- Recipient display name.
- Mailing address lines.
- City, state, and postal code.
- Opaque delivery identifier for reconciliation.

CSV output must prevent formula injection, use a documented UTF-8 format, be lodge/issue scoped, set private/no-store headers, and be audited. Export changes eligible `pending` rows to `prepared`. The UI allows selected/all prepared rows to be marked mailed with confirmation and actor/time audit. It may mark invalid/stale recipients skipped with a safe reason.

The same postal run records its immutable physical-copy source. If the issue has a PDF, select it by default; otherwise require the sanitized print rendition. The print route is management-authorized, uses print-specific accessible styling, excludes navigation and management controls from print output, and uses private/no-store caching. Republishing an issue never changes the physical source or addresses of an existing run.

Regenerating an export never creates duplicate delivery rows or resets mailed rows. A changed address requires an explicit new distribution run or an authorized correction workflow that preserves the prior snapshot; never rewrite historical mailed-address evidence silently.

## Audit, Caching, Privacy, and Security

Audit at minimum:

- Newsletter create/edit/publish/republish/unpublish/delete/restore and document/cover changes.
- Album create/edit/publish/republish/unpublish/delete/restore, visibility transitions, and photo changes/order.
- Communication create/edit/test/send/duplicate/cancel/retry.
- Communication-setting changes.
- Member preference changes from self-service and administration.
- Family request review and subscription activation/update/deactivation.
- Distribution creation, email retry, postal export, and mark-mailed actions.
- Public-media copy creation/removal caused by visibility changes.

Audit message/content changes using bounded hashes or summarized field names rather than copying entire rich bodies, PDFs, recipient lists, addresses, tokens, or provider payloads. Never log token plaintext.

Public album caching keys include lodge, album published-version identity, and media identity. Publishing, unpublishing, visibility changes, lodge status changes, or public-copy rotation invalidates only the affected lodge/album entries. Protected albums, member archives, protected images/PDFs, settings, recipient management, delivery status, and exports use private/no-store behavior and are excluded from shared response caches.

Rich text is untrusted input and uses the existing server sanitizer. Reject scripts, inline handlers, unsafe URL schemes, external tracking pixels, forms, iframes, arbitrary style injection, and lodge-authored JavaScript. Email rendering sanitizes again or consumes only the canonical sanitized value and escapes lodge/contact metadata.

Apply route throttles to public request/verification/unsubscribe flows, test sends, production send actions, and protected media reads as appropriate. Use CSRF protection on state changes. Token GET requests never mutate state.

## Lifecycle and Cross-Domain Rules

- Disabling a lodge removes public albums, denies protected audience routes, blocks new sends/exports, and causes unclaimed deliveries to skip. Reactivation does not automatically resume a cancelled run or resend anything.
- Ending/inactivating a membership stops future member portal access and future member deliveries. Historical delivery rows remain.
- Marking a member deceased stops their member deliveries. It does not invalidate eligible family subscriptions sponsored through that deceased membership.
- Marking a family recipient deceased stops future family deliveries.
- Account unlink/deletion affects portal eligibility but does not by itself stop email/print delivery derived from a valid membership Person and preference. Distribution does not require a login account.
- Person merge moves compatible member preferences through existing membership movement and rewrites compatible family subscription/request references. Abort on conflicting active family subscriptions or unresolved sponsor/recipient self-reference; never silently choose consent.
- Relationship deletion or sponsor membership changes revalidate affected family subscriptions and prevent future snapshots. Do not delete prior deliveries.
- Newsletter/album soft deletion never deletes shared media or immutable delivery history. Media cleanup must consult all current and historical references required for recovery/audit.
- General communications, newsletter distributions, event reminders, reservation mail, and volunteer reminders remain independent records and consent purposes. One action never creates another.
- `current_lodge_id`, role in another lodge, an officer assignment, a family relationship, receipt of email, or knowledge of an identifier never establishes ownership or audience eligibility.

## Automated Test Requirements

Use fixed clocks for publication dates, publishing, request expiry, delivery claims, retries, and retention. Every lodge-owned test group creates at least Lodge A and Lodge B and substitutes individually valid foreign identifiers.

### Unit and Domain Tests

- Newsletter and album draft/published/archive transitions, transactional promotion, private edits, explicit slug behavior, and descriptive publication date.
- At-least-one newsletter body/PDF rule, rich-text sanitization, PDF signature/MIME/size checks, and same-lodge cover/document ownership.
- Gallery visibility requester matrix for anonymous, approved/unapproved, verified/unverified, linked/unlinked, active/ended memberships, own/other lodge, disabled lodge, merged/deceased people, and platform admin.
- Gallery ordering, cover membership, readiness, reference resolution, and public-copy rotation/rejection.
- Effective communication-setting fallbacks and immutable header snapshots.
- Member email/print effective defaults, self/admin update authorization, address completeness, and preference-purpose independence.
- Family relationship direction, allowed types, living/deceased sponsor, recipient lifecycle, member conversion, removed relationship, invalid sponsor status, and channel/contact requirements.
- Recipient selection and normalized-email deduplication for newsletter versus general-message audiences.
- Distribution/message state machines, immutable snapshots, stale claim recovery, retry, resend, and completion aggregation.
- Member versus family unsubscribe behavior and token hashing/idempotency.
- Person merge and relationship/membership lifecycle conflict handling.

### Database and Concurrency Tests

- Composite lodge ownership for issues, versions, documents, albums, photos, settings, subscriptions, runs, and deliveries.
- Partial uniqueness for current draft/published versions and active family subscriptions on PostgreSQL.
- Valid enum/status/channel checks, at-least-one family channel, exactly-one run source, and exactly-one delivery recipient source.
- Same-lodge cover/photo/document constraints and cover-photo-in-version invariant enforced by transaction/service where a database constraint cannot express it.
- Two publication requests produce one promoted version and preserve the prior publication on failure.
- Two send requests with one idempotency key produce one run and one recipient snapshot.
- Concurrent dispatchers claim one delivery once; stale claims recover without duplicating sent mail.
- Concurrent unsubscribe and send revalidation never sends after the opt-out commits.
- Public-to-protected publication cannot commit while an unsafe public derivative remains.

Do not skip concurrency or partial-index coverage merely because SQLite lacks the required behavior. Run it against the configured PostgreSQL test service.

### Laravel Feature Tests

- Newsletter create/edit/preview/publish/republish/unpublish/delete/restore with permission separation.
- Published newsletter remains unchanged while its draft is edited.
- Member archive and cover/PDF routes deny anonymous, foreign-lodge, ended, merged, deceased, and platform-admin-only requesters.
- No newsletter metadata appears in public website props, HTML, cache, or the newsletter placeholder.
- Gallery CRUD, upload, processing failure/retry, captions, ordering, cover, all visibility modes, and publication.
- Public gallery placeholder includes only public albums and cannot leak protected counts/titles/covers.
- Prior public photo URL fails after a valid public-to-protected transition unless the asset remains deliberately public elsewhere, in which case unsafe reuse is rejected or cloned.
- General communication compose/test/send/archive/duplicate and immutable sent content.
- Built-in Administrator/Officer/Member/Non-member permission synchronization; officer assignment without Officer role remains denied.
- Member profile and administrative Person preference updates, per-lodge isolation, and no effect on event/security mail.
- Public family request non-enumeration, throttling, token verification, expiry, review, canonical-person/relationship approval, rejection, and cleanup.
- Living/deceased sponsor family electronic/physical distribution and invalid/unrelated request denial.
- Newsletter audience includes opted-in active members and approved family subscriptions; general message audience includes members only.
- Email rendering for body-only, PDF-only, both, lodge identity/fallbacks, attachment, and unsubscribe.
- Test sends only to the actor's verified email and create no production delivery.
- Repeated dispatch, overlapping workers, current-consent recheck, changed email, lodge disable, transport failure, and explicit failed-only retry.
- Postal CSV scope, escaping/formula-injection protection, headers, audit, prepared/mailed transitions, and idempotent regeneration.
- Postal physical-copy selection and print output for body-only, PDF-only, and combined issues; existing runs remain immutable after republish.
- Audit/log/Inertia/HTML checks contain no tokens, full recipient lists, private addresses outside authorized views, storage paths, or cross-lodge identifiers.

For every mutation and read, substitute a Lodge B issue, version, document, album, photo, communication, request, subscription, run, delivery, membership, person, relationship, and media identifier while authenticated only for Lodge A. Also test mismatched but individually valid Lodge A identifiers.

### Playwright Critical Path

Add a Phase 7 browser suite whose implementation filename has no Phase label. It must:

1. Sign in as a Lodge A administrator and configure sender/reply/newsletter contact settings.
2. Create a rich-content-plus-PDF newsletter, edit its private draft, preview, and publish with default electronic distribution.
3. Confirm an opted-in member receives the issue in Mailpit and sees it in the Lodge A member archive.
4. Opt that member out through profile settings and confirm a later general message does not send to them.
5. Re-enable email through an explicit audited preference action and request printed newsletters.
6. Submit and verify a family electronic/print request, approve it against a qualifying relationship to a living or deceased member, and confirm the family recipient has no portal access.
7. Export the issue postal list, verify only expected Lodge A recipients, and mark prepared copies mailed.
8. Assign the actual Lodge A Officer role to an officer account, compose and send a general message, and confirm it appears in the member archive.
9. Remove the Officer role while retaining the officer assignment and confirm send access is denied.
10. Create public, masons-only, and lodge-only albums with ordered/captioned photos.
11. Verify anonymous, Lodge B member, and Lodge A member visibility; ensure protected direct photo URLs fail for ineligible requesters.
12. Change a public album to lodge-only and confirm the prior public photo URL no longer bypasses authorization.
13. Render the public Gallery section and member-only Newsletter call to action without protected metadata.
14. Switch active context and attempt Lodge B/Lodge A identifier substitution.
15. Exercise mobile and desktop layouts, keyboard focus, light mode, and dark mode.

Email transport may be asserted through Mailpit and Laravel tests. The browser suite must not depend on nondeterministic background timing; provide documented test dispatch helpers or bounded polling.

### Required Gates

At each work-package gate run focused tests plus relevant static checks. At final integration run:

```text
php artisan test
vendor/bin/pint --test
npm run typecheck
npm run typecheck:e2e
npm run lint
npm run build
npm run test:e2e
composer validate --strict
composer audit
npm audit --audit-level=low
git diff --check
```

Also run `php artisan route:list`, `php artisan schedule:list`, and migration refresh/rollback against the PostgreSQL test environment. Resolve every warning or request explicit approval before suppressing it.

## Manual Acceptance Checklist

A tester using Lodge A, Lodge B, an unrelated Lodge C, Mailpit, and representative mobile/desktop browsers can:

1. Configure Lodge A's sender display, reply-to, secretary, and newsletter contact values and observe correct fallbacks.
2. Create body-only, PDF-only, and combined newsletter drafts with cover images.
3. Publish an issue and verify its descriptive publication date differs safely from actual `published_at`.
4. Edit after publication and confirm members still see the prior published version until republish.
5. Publish with default member email selected, then confirm eligible members receive one complete copy and opted-out members receive none.
6. Confirm newsletter body, PDF, cover, title, and date are absent from all anonymous public-site responses.
7. View the issue as an active Lodge A member; fail as anonymous, Lodge B-only member, ended member, and platform-admin-only account.
8. Send a test newsletter and confirm only the initiating verified user receives it and no production run changes.
9. Opt out through the secure email link and confirm later newsletter and general lodge emails are skipped while event/security messages remain unaffected.
10. Enable mailed newsletters through member profile settings and through an authorized Lodge A People record.
11. Submit a family request for email, postal, and both; verify email ownership where applicable and approve only after selecting a canonical family Person, sponsor, and relationship.
12. Approve a family recipient related to a living member and another related to a deceased member; confirm neither gains portal or directory access.
13. Reject an unrelated or unverifiable request without revealing whether the claimed member exists.
14. Export a safe Lodge A postal CSV, mark copies mailed, and confirm regeneration creates no duplicates.
15. Print the run's selected physical copy, using the PDF by default when present and the complete print-safe rich body otherwise.
16. Send a rich-text general communication as a user with the Lodge A Officer role and verify all opted-in active Lodge A members receive it once.
17. Confirm a current officer assignment without the Officer role cannot send.
18. Confirm family newsletter recipients do not receive the general lodge message.
19. Create public, masons-only, and lodge-only galleries and verify the full anonymous/Lodge B/Lodge A audience matrix.
20. Reorder photos, update captions/alternative text, select a cover, and confirm published content remains stable until republish.
21. Change a public album to protected and confirm old public derivative URLs cannot bypass the new state.
22. Attempt cross-lodge issue, document, album, photo, communication, preference, relationship, subscription, run, and delivery identifiers.
23. Disable Lodge A and confirm public albums, protected archives, sends, exports, and pending deliveries stop safely without affecting Lodge B.
24. Inspect Inertia props, HTML, logs, cache headers, CSV, and network responses for recipient/contact leakage, storage paths, tokens, protected metadata, and cross-lodge identifiers.
25. Complete newsletter, communication, gallery, request, preference, and postal workflows at mobile/desktop widths in light/dark modes using keyboard navigation.

## Detailed Implementation Work Plan

Complete and review one package before beginning a dependent package. Each package is bounded for a Terra handoff.

### Locked Implementation Map

Use these names unless an existing repository convention requires a small namespace adjustment:

- Enums: `ContentVersionStatus`, `GalleryVisibility`, `LodgeCommunicationStatus`, `DistributionRequestStatus`, `DistributionRunStatus`, `DeliveryChannel`, and `CommunicationDeliveryStatus`.
- Models: `NewsletterIssue`, `NewsletterIssueVersion`, `NewsletterDocument`, `GalleryAlbum`, `GalleryAlbumVersion`, `GalleryAlbumPhoto`, `LodgeCommunication`, `LodgeCommunicationSetting`, `FamilyNewsletterRequest`, `FamilyNewsletterSubscription`, `CommunicationDistributionRun`, and `CommunicationDelivery`.
- Domain namespaces: `app/Domain/Publications`, `app/Domain/Galleries`, and `app/Domain/Communications`.
- Core services: `NewsletterPublisher`, `GalleryPublisher`, `GalleryAudience`, `MediaExposureService`, `FamilyNewsletterEligibility`, `CommunicationAudience`, `DistributionService`, and `CommunicationDispatcher`.
- Queue/console: `DispatchLodgeCommunications`, `SendCommunicationDelivery`, and `ExpireFamilyNewsletterRequests`.
- Focused backend tests: `tests/Feature/NewsletterTest.php`, `GalleryTest.php`, `LodgeCommunicationTest.php`, and `NewsletterDistributionTest.php`, plus bounded unit tests.
- Browser coverage: `tests/Browser/lodge-publications.spec.ts` or another domain name without a phase label.

Use one new timestamped domain migration after the Phase 6 migration, named for the domain rather than the phase, such as `create_lodge_publication_communication_domain.php`. It may alter media and membership-preference columns before creating dependent tables. Migration rollback proceeds in reverse dependency order and preserves preexisting Phase 2 media safely.

All writes go through form requests and domain services. Any departure from publication versioning, audience definitions, family eligibility, consent defaults, role separation, media revocation, recipient snapshots, or delivery idempotency requires a documentation amendment before implementation.

### P7-01 Schema, Enums, Models, Permissions, and Factories

Prerequisite: clean Phase 6 baseline.

Deliver:

- Domain migration, enums, models, relationships, casts, ownership constraints, partial indexes, and factories.
- Add print preference and private-media derivative metadata with safe backfill/default behavior.
- Add permissions and idempotently synchronize built-in roles exactly as defined while preserving custom roles.
- Extend creation and merge/lifecycle foundations for the new rows without HTTP or delivery behavior.

Tests and gate:

- Fresh PostgreSQL migration/rollback, constraints/indexes/defaults, factory ownership, preexisting media compatibility, role synchronization/custom-role preservation, merge conflict foundations, focused tests, full Laravel suite, and Pint.

### P7-02 Protected Media and Reference Exposure

Prerequisite: P7-01.

Deliver:

- Private normalized derivative generation for gallery/newsletter use.
- Central reference resolver across website, event, newsletter, and gallery content.
- Public-copy materialization/revocation and cache invalidation contract.
- Authorized protected response helper with no-store headers and original-path exclusion.

Tests and gate:

- Image format/MIME/pixel/orientation/metadata regression, readiness/retry, cross-lodge references, public-to-protected transitions, shared public reference conflicts, prior-URL denial, cache behavior, and Phase 2/4 media regressions.

### P7-03 Newsletter Versioning, Documents, and Member Reads

Prerequisites: P7-01 and P7-02.

Deliver:

- Newsletter publisher, issue/version/document management, sanitization, PDF validation/storage, cover rules, preview, publish/republish/unpublish/delete/restore.
- Member archive/detail/cover/document routes and dashboard integration.
- Generic public Newsletter-section sign-in state without metadata.

Tests and gate:

- Transactional lifecycle, private edits, content combinations, document security, member eligibility, 403/404 behavior, public leakage, tenant attacks, typecheck, lint, and focused browser coverage.

### P7-04 Gallery Versioning, Audience, and UI

Prerequisites: P7-01 and P7-02.

Deliver:

- Album/version/photo management, upload, captions, ordering, cover, visibility, preview, publication lifecycle, and deletion/restoration.
- Public/protected index/detail/photo routes through `GalleryAudience`.
- Public Website Gallery-section integration and responsive accessible album UI.

Tests and gate:

- Full audience matrix, tenant identifiers, draft/published stability, processing/readiness, ordering/cover, protected media/cache behavior, placeholder isolation, typecheck, lint, build, and browser matrix.

### P7-05 Communication Settings and Preference Surfaces

Prerequisite: P7-01.

Deliver:

- Effective lodge communication settings and authorized editor.
- Member self-service email/print controls and address-completeness UX.
- Administrative Person preference controls behind `communications.recipients`.
- Audits and explicit separation from event/security/volunteer preferences.

Tests and gate:

- Fallbacks, normalization, self/admin ownership, active membership, per-lodge isolation, address validation, audit, existing profile regression, typecheck, lint, and focused browser flow.

### P7-06 Family Requests and Subscriptions

Prerequisites: P7-01 and P7-05.

Deliver:

- Public non-enumerating request/verification flow, throttling, expiry, cleanup, and review UI.
- Canonical Person/sponsor/relationship approval workflow and family eligibility service.
- Subscription channel/preferences, unsubscribe-ready state, lifecycle hooks, and audits.

Tests and gate:

- Relationship direction/type, living/deceased sponsor, recipient lifecycle, email verification, postal-only review, non-enumeration, abuse bounds, approval/rejection/expiry/purge, tenant attacks, no account/access grants, typecheck, lint, and browser request/review flow.

### P7-07 Distribution Snapshot, Email Dispatcher, and Unsubscribe

Prerequisites: P7-01, P7-03, P7-05, and P7-06.

Deliver:

- Newsletter/general-message audience contracts and immutable run/delivery snapshots.
- Dispatcher command, job, scheduler, rendering, PDF attachment, lodge identity, status reconciliation, stale claim recovery, failure/retry, and provider ID capture.
- Secure member/family unsubscribe routes and current-consent revalidation.

Tests and gate:

- Audience/deduplication, body/PDF combinations, settings fallback, idempotency/concurrency, current eligibility/consent/contact changes, lodge disable, transport failure, retry, token behavior, log hygiene, schedule listing, PostgreSQL tests, and Mailpit spot check.

### P7-08 General Lodge Communications and Member Archive

Prerequisites: P7-05 and P7-07.

Deliver:

- Communication draft editor, sanitizer, test, frozen production send, progress/failures, failed-only retry, duplicate-to-new-draft, and immutable sent state.
- Member-only sent-message archive/detail and dashboard integration.
- Officer navigation and authorization through the actual built-in/custom lodge role.

Tests and gate:

- Compose/test/send/archive/duplicate, members-only audience, family exclusion, opt-out, Officer role versus assignment, sent immutability, tenant attacks, protected payloads, typecheck, lint, build, and browser officer journey.

### P7-09 Postal Preparation and Fulfillment

Prerequisites: P7-05, P7-06, and P7-07.

Deliver:

- Postal snapshot/revalidation, safe CSV export, prepared/mailed/skipped transitions, aggregate UI, and audit.
- Immutable physical-copy selection plus authorized PDF/print-safe rich-content output.
- Idempotent regeneration and changed-address handling without historical rewrite.

Tests and gate:

- Member/family selection, incomplete/stale addresses, CSV escaping/formula injection, authorization, no broad contact export, repeated export, mailed preservation, cross-lodge attacks, cache headers, audit privacy, and browser fulfillment path.

### P7-10 Lifecycle, Browser Integration, and Documentation

Prerequisite: all prior packages.

Deliver:

- Complete lodge/person/account/membership/relationship/merge/media lifecycle integration.
- Dashboard/navigation, CMS placeholder, mobile/desktop/light/dark, keyboard, Mailpit, and adversarial browser coverage.
- Reconcile architecture, domain model, authorization, tenancy rules, testing strategy, README, ADR index, and operations/scheduler notes with implemented behavior.
- Resolve every test, type, lint, build, IDE, and dependency-audit finding without silent suppression.

Tests and gate:

- Full required gates, PostgreSQL refresh/rollback, route/schedule inspection, all manual acceptance items, privacy/log/network review, and completed two-lodge plus cross-lodge attack matrix.

## Dependency and Parallelization Map

- P7-01 blocks all schema-dependent work.
- P7-02 may proceed after P7-01 and blocks newsletter/gallery protected media.
- P7-03 and P7-04 may proceed independently after P7-02 if they do not concurrently edit the media reference service or shared routes/navigation.
- P7-05 may proceed after P7-01 independently of publication UI.
- P7-06 waits for preferences/settings from P7-05.
- P7-07 waits for published newsletter, preferences, and family subscription contracts.
- P7-08 waits for settings and the delivery engine from P7-07.
- P7-09 waits for recipient sources plus run/delivery state.
- P7-10 is the final integration gate.

For one agent, use numbered order. With multiple agents, do not parallel-edit the domain migration, role catalog, Person merge, profile/People settings, central media exposure service, communication run/delivery state machine, shared routes, dashboard, or navigation without explicit file ownership and one integration owner.

## Agent Handoff Contract

Give Terra one package at a time. Every handoff must:

- Name the package and copy its exact deliverables/gate from this document.
- Require reading this full specification, Phase 2 media/CMS rules, Phase 3 person/relationship rules, Phase 6 privacy/preferences, ADRs 0004/0005/0007, architecture, domain model, authorization, tenancy, and testing documents.
- Identify verified prerequisite commits/contracts and PostgreSQL baseline results.
- State allowed files/directories and preserve unrelated dirty-worktree changes.
- Prohibit Phase labels in implementation artifacts.
- Prohibit client-side audience filtering, raw model serialization, public protected-media URLs, arbitrary sender addresses, submitted ownership, bulk contact exposure, unverified family enrollment, send-time consent bypass, and queue payload contact snapshots.
- Require Lodge A/B/C plus disabled-lodge adversarial tests and exact 403/404 behavior.
- Require reporting any conflict with canonical Person ownership, family relationship direction, member-versus-family consent, role/assignment separation, media revocation, publication immutability, or delivery idempotency rather than silently changing it.
- Require focused tests plus package gates and warning resolution without suppression.
- Stop after the package gate and report changed files, migrations, tests/results, risks, and prerequisites for the next package.

## Definition of Done

Phase 7 is complete only when:

- Every package and final gate passes against PostgreSQL.
- Newsletter and communication portal content is accessible only to current active members of the explicit owning lodge and absent from public props/HTML/cache.
- Newsletter body/PDF combinations, descriptive dates, draft/published versions, private edits, publication, notification, resend, and unpublish behave exactly as specified.
- Members receive lodge/newsletter email by default, may opt out, may opt into print, and neither setting alters unrelated transactional communication.
- Eligible family of living/deceased members can request and receive electronic/physical newsletters without gaining membership or portal/directory access.
- General communications go only to opted-in current members and may be sent by an account with `communications.send`, including the actual built-in Officer role.
- Electronic sends are durable, current-consent-aware, idempotent, retryable, tenant-safe, and never duplicate a successful delivery.
- Postal preparation is scoped, auditable, safe to export, idempotent, and does not pretend to integrate with a postage provider.
- Public, masons-only, and lodge-only albums enforce the same visibility for index, detail, photos, counts, cache, props, and prior URLs.
- Public-to-protected album transitions cannot leave a public derivative bypass.
- Cross-lodge identifiers, active context, role in another lodge, family relationships, and platform-admin status cannot broaden audience or management access.
- All manual acceptance items pass at mobile/desktop widths and light/dark modes.
- Cross-cutting docs match implementation and no implementation artifact uses a Phase label.

## Non-Goals

- Public newsletter browsing, public communication archives, RSS, platform-wide newsletter aggregation, or search-engine indexing of publications.
- General public email subscriptions or non-family external newsletter lists.
- Recipient segmentation, arbitrary recipient selection, mailing-list imports, marketing automation, drip campaigns, analytics pixels, open/click tracking, A/B testing, or CRM behavior.
- SMS, push notifications, member-to-member messaging, inbound email, discussion threads, comments, or social-network features.
- Scheduled newsletter publication, scheduled general-message sending, or recurring campaigns.
- Arbitrary email attachments; only the validated newsletter PDF is attachable.
- Custom email-template designer, lodge-authored HTML/CSS/JavaScript, arbitrary From/envelope identities, or provider-specific configuration UI.
- Provider bounce/complaint webhook integration or automatic global suppression management.
- Postal-vendor integration, postage purchase, label-printer drivers, returned-mail processing, household deduplication, or address verification service.
- Photo comments, reactions, tagging people, face recognition, downloads of originals by ordinary viewers, video, GIF, RAW/DNG, SVG, or interactive image editing.
- Cross-lodge gallery contribution or platform-curated regional gallery aggregation.
- Legacy Newburgh import. Phase 7 preserves stable lodge ownership, content/version, recipient, and media metadata needed for Phase 12 migration.
- Ritualist taxonomy or implementation; that belongs to Phase 8.
