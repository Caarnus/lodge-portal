# Phase 11 — Scholarship Management

## Outcome

Each lodge can publish and operate a private, lodge-owned scholarship application and
committee-review workflow when Scholarship is both available from the platform and enabled by
the lodge. Phase 11 consumes every Phase 10 enforcement adapter and adds no
Scholarship-specific gating shortcut.

## Module Definition and Permissions

- Add the active platform-owned `scholarship` module definition through the controlled Phase 10
  definition lifecycle. The key is release-owned and immutable.
- Define `scholarships.manage` and `scholarships.review` as platform-owned lodge permissions.
- The built-in lodge Administrator receives `scholarships.manage`. Review authority is not
  implied; an Administrator reviews only when separately granted `scholarships.review` and
  assigned to the cycle.
- Other built-in roles receive neither Scholarship permission by default.
- Platform administrators control availability but are neither Scholarship administrators nor
  reviewers by default. Access to lodge-owned Scholarship data requires an explicit lodge role
  and the same permissions as any other user.
- Scholarship permissions grant no `lodge_modules.manage` authority, and module state grants no
  Scholarship permission.
- Every administrative, applicant, API, service, download, job, search, cache, navigation, and CMS
  entry point uses the Phase 10 effective-state contract before applying its ordinary Scholarship
  authorization and ownership rules.

## Ownership and Core Records

All Scholarship domain records are lodge-owned and carry an explicit `lodge_id`, including when
the owner can also be derived through a parent. Database constraints or write-time invariant
checks enforce one matching ownership chain.

The initial aggregate contains:

- Scholarship cycle.
- Cycle application-field definitions.
- Cycle document requirements.
- Cycle rubric criteria.
- Cycle reviewer assignments.
- Applicant application.
- Application answers.
- Applicant document records.
- Email-verification and secure-resume credentials.
- Reviewer rubric scores and review-submission history.
- Committee discussion comments.
- Application workflow history.

Applications belong to exactly one lodge and cycle. Answers reference a field definition from the
same cycle. Documents reference a requirement and application from the same lodge/cycle. Reviews
reference an assigned reviewer, application, and rubric frozen for the same cycle. Direct child
identifiers never bypass those relationships.

## Scholarship Cycle

A cycle defines:

- Lodge-unique name and stable route identity/slug.
- Application open and close dates interpreted in the lodge's IANA time zone.
- Award description.
- Instructions and eligibility text.
- Custom application fields and document requirements.
- Weighted review rubric.
- Assigned reviewer committee.
- Lifecycle and audit history.

Cycle lifecycle is:

`draft → open → closed → archived`

- Draft cycles are administrative only and may be freely configured.
- Moving a cycle to `open` freezes its complete applicant form, document requirements, and rubric.
  This transition is rejected unless the field, document, rubric, date, and weight rules are valid.
- `open` makes the cycle eligible for publication, but applicant access still requires the current
  time to fall within the application window and a published Scholarship CMS section referencing
  the cycle.
- A cycle accepts applications beginning at `00:00:00` on the open date and stops accepting them
  at the next local midnight after the displayed close date. The public UI describes this as
  closing at 11:59 PM on the close date. Calculations use the lodge time zone and remain correct
  across DST changes.
- The date predicates are authoritative on every start, save, verification, upload, and submit
  request; scheduled jobs are not the sole enforcement mechanism.
- Administrators may close a cycle early. Reopening a closed cycle is allowed only before its
  configured close boundary, requires confirmation, and is audited. Archived cycles cannot be
  reopened; a new cycle should be cloned instead.
- Archiving removes the cycle from ordinary active administration and public projections while
  retaining its records and authorized historical access.
- Existing cycles may be cloned into editable drafts. Frozen structures are never edited in
  place.

## Custom Applicant Form

Email is the only mandatory fixed applicant field in the initial release because it establishes
anonymous application ownership, verification, and resume access. Lodges configure almost all
other applicant information.

Supported field types are:

- Short text.
- Long text/essay.
- Email, in addition to the required system email only when the lodge needs another labeled email.
- Phone.
- Date.
- Number/decimal.
- Single-select.
- Multi-select.
- Yes/no or acknowledgement checkbox.
- Structured mailing address.

Each definition supports a stable identifier, administrator label, applicant-facing label, help
text, required state, order, and type-appropriate validation such as length, numeric bounds, date
bounds, and controlled options. Applicant-supplied labels, validation rules, or arbitrary JSON
keys are never accepted. Rich instructions/help text use the existing sanitization boundary.

Field definitions, option values, order, requirements, and validation freeze when the cycle moves
to `open`. Application answers retain their definition identity and a bounded label/type snapshot
so exports and historical review remain meaningful even if a future cloned cycle changes.

Conditional branching, arbitrary executable validation, computed eligibility, and automatic
ranking are excluded from the initial release.

## Anonymous Applicant Identity and Resume Access

Applicants do not need a WorkingTools account and applications are not automatically linked to a
`User`, `Person`, or lodge membership.

- Starting an application requires a normalized email address.
- A cycle permits one application record per normalized email address, including after withdrawal.
  Re-entering the same email starts the secure resume/recovery flow rather than revealing whether
  an application exists or creating a second record.
- Resume and email-verification links use separate, purpose-bound, random tokens. Only token hashes
  are stored. Tokens expire, rotate when replaced, are single-purpose, and are rate limited.
- Applicant responses never disclose whether a submitted email belongs to an existing application.
- Email verification is required before final submission. Verification alone does not submit the
  application.
- A valid resume credential authorizes only that application while the cycle and CMS publication
  rules permit applicant access. It grants no administrative, reviewer, lodge, or cross-cycle
  access.
- Draft saves are allowed only during the application window. Submitted applications are
  read-only to applicants.
- Applicants may withdraw a submitted application through a separate confirmed secure action.
  Withdrawal is retained and audited; it does not delete answers or documents.
- Unsubmitted drafts remain for 30 days after the lodge-local cycle close boundary and are then
  purged by a scheduled retention job.

## Application Lifecycle

Application lifecycle is:

`draft → submitted → under_review → finalist → awarded | declined`

An application may be declined directly from `under_review`; finalist is an optional human-selected
stage rather than an automatic score threshold. `submitted`, `under_review`, or `finalist` may also
transition to `withdrawn` through the applicant withdrawal flow. A withdrawn application cannot
return to review in the initial release.

- Submission is transactional and idempotent. It revalidates the application window, verified
  email, all frozen required fields, required clean documents, and lodge/cycle ownership.
- Submitted answers and applicant documents are immutable to the applicant.
- The first completed reviewer submission may move the application to `under_review`; the exact
  status transition is recorded once and is safe under concurrent reviews.
- Only `scholarships.manage` may set awarded or declined outcomes. Outcomes do not pay awards,
  create tax records, or automatically notify until the corresponding notification is committed.
- Status corrections require confirmation, a reason, and immutable workflow history. They do not
  rewrite prior review or answer history.
- Module disablement, availability revocation, lodge disablement, or cycle closure never changes an
  application's workflow status.

## Document Requirements and Private Uploads

Lodges may define ordered document requirements such as transcript, essay attachment, reference,
or other scanned evidence. Each requirement has a stable identifier, label, help text, required
state, and allowed supported file categories.

The initial supported formats are PDF, JPEG, PNG, WebP, HEIC, and HEIF, with decoded-content
validation rather than extension trust. Office documents, archives, executable formats, and files
containing active content are rejected. Each file is limited to 20 MB and the combined retained
documents for one application are limited to 100 MB.

- Originals use private storage with opaque generated paths; storage paths are never serialized.
- Uploads enter a pending state and cannot satisfy submission or be downloaded until the initial
  Phase 11 content, type, size, and ownership validation succeeds.
- Replacing a draft document retires the prior file without creating a public URL.
- Every read/download reloads the application, lodge, cycle, effective module state, reviewer or
  manager authority, assignment where applicable, and document readiness.
- Responses use safe `Content-Type` and `Content-Disposition` headers and private/no-store caching.
- Applicant filenames are treated as untrusted display metadata and never used as storage paths.
- Failed validation leaves a file unavailable and exposes a safe retry/replacement message without
  diagnostic internals.

Malware scanning is planned but is not implemented in Phase 11. Keep validation/readiness separate
from a future malware-scan status and define a narrow scanner adapter boundary so a locally operated
ClamAV daemon can be added in Docker development and the Ubuntu production environment later.
Phase 11 must not pretend a file was malware-scanned, label it clean, or require a scanner service
that is not present. When scanning is implemented, files will remain unavailable until a
lodge-aware queued scan reports clean, scanner failure will fail closed, and bounded operational
status will be recorded without logging document contents. Adding that enforcement requires a
deliberate migration/deployment plan for documents accepted before scanning existed.

## Reviewer Committee and Access

Reviewers are assigned to the cycle, not separately to each application. A reviewer may access
submitted/non-withdrawn applications in that cycle only when all of the following remain true:

- Scholarship is effective for the explicit lodge.
- The reviewer has `scholarships.review` in that lodge.
- The reviewer has an active assignment to that cycle.
- The cycle, application, document, rubric, and assignment ownership chain matches.

Review is not blinded. Assigned reviewers may see applicant identity, answers, essays, and clean
required documents because those materials are necessary to evaluate the application. Draft and
withdrawn applications are not reviewable. Applicants never see reviews, scores, private notes,
committee comments, or reviewer identities.

Removing a reviewer assignment immediately removes future access but preserves that reviewer's
historical scores, revisions, and comments. Reassignment does not rewrite authorship.

## Weighted Rubric, Score Visibility, and Discussion

The lodge defines an ordered weighted rubric while the cycle is a draft. Each criterion contains:

- Name and reviewer guidance.
- Positive integer maximum score.
- Weight expressed and stored with fixed precision.
- Required reviewer comment setting where desired.
- Display order.

Criterion weights must total exactly 100 percent before the cycle can open. A review must score
every criterion within `0..maximum_score`. Its normalized weighted total is calculated server-side
on a 0–100 scale. Display rounding never changes stored criterion scores or the fixed-precision
aggregate calculation.

- A reviewer first works privately and cannot see peer scores, aggregates, or committee discussion.
- Submitting one complete initial rubric permanently unlocks peer latest scores, aggregate results,
  completion counts, and committee discussion for that reviewer.
- A reviewer may revise their own submitted criterion scores and private reviewer note. Every
  revision is retained in immutable history; the latest submitted revision supplies that reviewer's
  contribution to the aggregate.
- Aggregates use only the latest complete submitted review from each currently assigned reviewer
  and display both reviewer count and completion count. Historical removed-reviewer scores remain
  available in audit/history but do not silently affect the current aggregate.
- Committee comments are append-only discussion entries available to cycle reviewers who have
  submitted their initial rubric and to Scholarship managers. Comments identify their author and
  timestamp, are never exposed to applicants, and cannot alter scores or application status.
- Reviewers may not edit or delete another reviewer's scores, notes, or comments.
- Aggregate scores assist human review only. They never rank, award, decline, or otherwise decide
  applications automatically.

## Scholarship Administration

Users with `scholarships.manage` may:

- Create, validate, open, close, clone, and archive cycles.
- Configure the applicant form, document requirements, and rubric while the cycle is a draft.
- Assign or remove cycle reviewers before or after opening, with immediate authorization changes
  and preserved authorship/history.
- Monitor verified/draft/submitted/under-review/withdrawn/outcome counts without accessing another
  lodge.
- View submitted applications, clean documents, review completion, latest aggregates, discussion,
  and immutable histories.
- Set and correct permitted application outcomes with reasons.
- Produce an audited CSV export for one explicit cycle.

Exports include fixed application identity/status/timestamps, normalized email, configurable
answers with stable headings, document-presence/readiness indicators, latest reviewer criterion
scores, weighted totals, aggregate values, and outcome where authorized. They exclude private
reviewer notes, committee comments, token material, storage paths, malware diagnostics, and
reusable document URLs. CSV output escapes spreadsheet formulas and is generated/downloaded
through a lodge-authorized, effective-state-checked private response.

## Public CMS and Applicant Access

Add a supported Scholarship application CMS section through the existing versioned section
catalog. Its configuration references one same-lodge cycle and contains only the bounded display
options defined by the section contract.

- Enabling Scholarship or opening a cycle never creates a page, section, or navigation entry.
- An applicant start/resume/verify/upload/submit route is available only while the cycle is
  referenced by at least one currently published section for that explicit public lodge.
- The referenced cycle must also be open, currently within its application window, and effectively
  enabled, except that a post-submission confirmation/withdrawal action may use its documented
  secure token rules.
- Unpublishing the final referencing section or making Scholarship ineffective immediately blocks
  ordinary applicant access without deleting the cycle or applications.
- At render time the section rechecks lodge ownership, publication, cycle lifecycle/window, and
  effective state. An ineffective/closed section emits no application records, resume form, or
  action URL and uses a deliberate unavailable/closed treatment while preserving the page section.
- Applicant and reviewer responses use private/no-store caching. Public page cache entries use the
  Phase 10 module-aware invalidation contract.

## Notifications and Time-Based Work

Initial transactional messages are:

- Email verification/resume link after a start or recovery request, using non-enumerating copy.
- Successful application-submission confirmation.
- Applicant withdrawal confirmation.
- Reviewer-assignment notification.
- Outcome notification only when a Scholarship manager explicitly confirms the corresponding
  awarded/declined transition and message.

Messages contain the lodge and cycle identity and only the minimum applicant information needed.
They do not attach documents, include private answers/reviews/comments, or expose reusable manager
links. Deliveries use dedicated idempotency/history records and recheck lodge, module, cycle,
application, and recipient state at execution. A job queued before disablement exits safely when
Scholarship is ineffective.

Automatic applicant deadline reminders are not included in the initial release. Draft purge is
scheduled for 30 days after the authoritative lodge-local close boundary, rechecks that the
application is still an unsubmitted draft, deletes its private documents through the storage
abstraction, records bounded purge history, and never purges submitted or withdrawn applications
under the draft rule.

## Disabled-State and Preservation Behavior

When Scholarship is unavailable, lodge-disabled, definition-retired, or otherwise ineffective:

- Applicant, administration, review, API, download, service, and job operations fail closed.
- Scholarship workspaces and public action links are omitted.
- Search indexing/results, cache reads, public projections, and CMS sections expose no Scholarship
  records.
- Queued notifications, export, and processing work reload state and stop safely. A future malware
  scanning job must follow the same rule.
- Cycles, form/rubric definitions, applications, answers, assignments, scores/revisions, comments,
  documents, histories, and audit records remain stored with their original ownership.

Re-enabling restores only access currently permitted by cycle dates/lifecycle, CMS publication,
application status, document readiness, reviewer assignment, and ordinary permissions.
Disablement never reopens a deadline, rolls back a status, changes an aggregate, or republishes CMS
content.

## Audit and Sensitive-Data Rules

Audit cycle lifecycle and frozen-structure publication, reviewer assignment/removal, application
submission/withdrawal/outcome correction, review initial submission/revision, export generation,
document readiness/removal, and retention purge. Audit before/after JSON is deliberately bounded:
it records identifiers, state, score totals where appropriate, actor, lodge, and reason, but never
application answers, document contents/paths, token hashes, private reviewer notes, or committee
comment bodies.

Application identity, answers, documents, reviews, and discussion are never regional discovery
data. Logs, exception context, queue payloads, Inertia props, search documents, analytics, and
emails must not receive the full model serialization or sensitive bodies.

## Automated Tests

Use Lodges A and B and repeat the complete Phase 10 availability/preference matrix with the real
`scholarship` definition and representative Scholarship data. Include:

- Definition registration, permission catalog/default-role assignment, and module-state
  independence.
- Composite ownership/uniqueness/foreign-key invariants for every nested record.
- Cycle validation, freeze behavior, cloning, time-zone boundaries, DST, early close, controlled
  reopen, archive, and immutable frozen definitions.
- Custom field types/options/validation, required email, answer snapshots, untrusted JSON rejection,
  and cross-cycle field identifiers.
- Non-enumerating start/recovery, normalized-email uniqueness, token hashing/expiry/rotation/rate
  limits, verification-before-submit, draft save, idempotent submission, and withdrawal.
- Required-document readiness, MIME spoofing, supported formats, 20 MB file and 100 MB application
  limits, pending/validated/rejected processing, retry, replacement, private/no-store download,
  future scanner-adapter boundaries, and cross-lodge document identifiers.
- Reviewer permission plus cycle assignment, immediate revocation, visible applicant materials,
  hidden drafts/withdrawals, frozen rubric, criterion bounds/weights, private initial review,
  post-submission peer visibility, revisions/history, latest-score aggregates, removed-reviewer
  handling, append-only discussion, and applicant non-disclosure.
- Manager lifecycle/outcome rules, progress counts, CSV authorization/content/formula escaping,
  exclusion of notes/comments/tokens/paths, and audited exports.
- CMS publication as the only applicant entry point, same-lodge cycle validation, last-section
  unpublication, closed/unavailable treatment, no auto-publication, and stale-cache denial.
- Jobs reloading state for email, purge, and exports; exact idempotency and safe skipped status
  after disablement. Future scan-job integration is structurally supported but not implemented.
- Platform-unavailable, lodge-disabled, retired-definition, permission-without-state,
  state-without-permission, direct URL/API/service/download, active-context mismatch, payload
  reassignment, and Lodge A/B identifier attacks.
- Disablement preserving every representative record and re-enablement restoring only currently
  authorized, published, in-window access without changing workflow or aggregate state.
- Bounded audit contents and proof that sensitive answers, files, notes, comments, and tokens are
  absent from logs, queue payloads, search, public HTML, and serialized Inertia props.

Focused Playwright coverage exercises platform availability, lodge preference, cycle/form/rubric
configuration, CMS publication, anonymous start/verify/resume/submit, private document upload,
reviewer initial scoring and post-submit discussion, manager outcome/export, cross-lodge denial,
and disable/re-enable preservation. Cover phone portrait and tablet-landscape-or-wider layouts for
the critical applicant, reviewer, and management paths.

## Manual Acceptance

1. Add the Scholarship definition and make it available to Lodge A but not Lodge B.
2. Enable it for Lodge A and create a draft cycle with custom fields, document requirements, a
   weighted rubric totaling 100 percent, and a reviewer committee.
3. Open the cycle and prove the form/rubric can no longer be edited.
4. Publish its Scholarship section and complete the anonymous email verification, resume, upload,
   and submission flow.
5. Confirm no applicant route is available for Lodge B or after the last Lodge A section is
   unpublished.
6. Submit one review, confirm peer scores/discussion unlock only for that reviewer, revise the
   score, and inspect immutable history and the latest aggregate.
7. Remove a reviewer and confirm immediate access loss without historical deletion.
8. Export the cycle and confirm the permitted fields, spreadsheet safety, exclusions, and audit.
9. Exercise platform-unavailable and lodge-disabled states across public, admin, reviewer,
   download, job, cache, search, and CMS surfaces and confirm every record remains.
10. Re-enable Scholarship and confirm current publication, dates, workflow, assignment, document
    readiness, and permission rules still govern restored access.
11. Close/archive the cycle and verify historical administration remains private and intact.

## Definition of Done

- Phase 10 is complete and every Scholarship boundary consumes its centralized adapters.
- The lodge controls nearly all applicant fields while fixed verified email supplies anonymous
  ownership and secure resume access.
- Cycle form, document requirements, and weighted rubric freeze when the cycle opens.
- Reviewer access is cycle-assigned, non-blinded, permission-scoped, and immediately revocable.
- Initial-score privacy, later peer visibility, revisions, latest-score aggregates, and append-only
  discussion behave consistently and remain hidden from applicants.
- Applicant documents remain pending until validated, private, reauthorized, and tenant-isolated;
  future ClamAV integration has an explicit adapter/status boundary without being implemented now.
- CMS publication is required for applicant entry and is independent from module enablement.
- Disabled state preserves all required data and fails closed across every specified surface.
- Feature, browser, tenant-isolation, sensitive-data, accessibility, type, lint, build, and
  formatting checks pass.
- Unsubmitted-draft retention, upload limits, notification scope, and deferred malware-scanning
  boundaries match the approved decisions in this specification.

## Resolved Implementation Decisions

1. Purge unsubmitted drafts 30 days after the lodge-local cycle close boundary.
2. Limit uploads to 20 MB per file and 100 MB retained total per application.
3. Plan for a locally operated ClamAV adapter and scan-status lifecycle, but do not implement or
   require malware scanning in Phase 11.
4. Send verification/resume, submission, withdrawal, reviewer-assignment, and explicitly confirmed
   outcome messages. Do not send automatic deadline reminders in the initial release.

## Non-Goals

- Shared regional scholarships.
- WorkingTools-account registration for applicants.
- Automated eligibility decisions, applicant ranking, or award decisions.
- Conditional/branching application forms or executable lodge-authored validation.
- Blinded review.
- Payment of awards, tax receipts, or online payments.
- Transcript, school, identity-verification, or reference-provider API integrations.
- Office documents, executable uploads, or public applicant-document URLs.
