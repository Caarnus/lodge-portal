# Phase 11 — Scholarship Management

## Outcome

Each lodge can run private, lodge-owned scholarship cycles and application review when Scholarship is both available from the platform and enabled by the lodge. Phase 11 consumes the Phase 10 module-state contract; it does not introduce a Scholarship-specific gating shortcut.

## Module and Authorization Contract

- Add the platform-owned `scholarship` module definition during this phase.
- Scholarship is ineffective unless platform availability and lodge enabled preference are both true.
- A platform administrator controls availability. An administrator with `lodge_modules.manage` in the target lodge controls the lodge preference only while available.
- Scholarship administrators and reviewers manage data only through explicit lodge-scoped permissions. Those permissions do not confer module-toggle authority.
- Platform administrators are not reviewers by default.
- Every route, API, service, document download, job, search projection, navigation item, and public CMS projection enforces effective state and ordinary authorization server-side.

## Domain Scope

Lodge-owned scholarship cycles define name, application window, award description, instructions, eligibility text, required fields/uploads, lodge-defined application questions, reviewer assignments, and lifecycle. Applications belong to exactly one lodge and cycle. Reviews belong to an authorized reviewer and application and contain scores, private notes, submission state, and bounded aggregate behavior.

Applicants can start/save as allowed, answer configured questions, upload required private documents, verify email where configured, submit before deadline, and receive confirmation. Authorized administrators can monitor review progress, change workflow status, export permitted data, and close/archive cycles.

All transcripts and applicant documents use private storage and reauthorized downloads. Public URLs never reveal storage paths. Applicant identity and review contents are sensitive lodge-owned data and are not regional/shared discovery data.

## Disabled-State Behavior

When Scholarship is unavailable or lodge-disabled:

- applicant and administrative routes/APIs fail closed;
- scholarship navigation and workspaces are omitted;
- public application output and module-backed CMS sections expose no records;
- notifications and processing jobs do not advance applications for that lodge;
- cache/search results do not expose applicants, cycles, reviews, or documents; and
- cycles, applications, answers, reviews, assignments, documents, and audit history remain stored with their original ownership.

Re-enabling restores access according to current dates, lifecycle, permissions, reviewer assignments, and publication rules. Disablement does not reopen deadlines or otherwise mutate scholarship state.

## Automated Tests

In addition to ordinary deadlines, questions, scoring, workflow, exports, email, private download, and two-lodge isolation tests, cover:

- unavailable and lodge-disabled list/detail/write/download attempts;
- Scholarship permission without effective module state;
- effective module state without Scholarship permission;
- cross-lodge identifier manipulation for cycles, applications, reviews, and documents;
- jobs, cache, search, public projection, and CMS sections while ineffective;
- disabling with existing applications/reviews/documents preserves every record; and
- re-enabling restores authorized access without changing prior workflow state.

## Manual Acceptance

Configure independent cycles for Lodges A and B, submit applications, assign separate reviewer committees, verify private downloads and aggregate scoring, and archive a cycle. Repeat the critical list/detail/write/download paths with Scholarship platform-unavailable, lodge-disabled, and enabled. Confirm disabling preserves data and re-enabling restores only normally authorized access.

## Non-Goals

- Shared regional scholarships.
- Payment of awards.
- Transcript or school API integrations.
- Automated applicant-ranking decisions.
- Online payments.
