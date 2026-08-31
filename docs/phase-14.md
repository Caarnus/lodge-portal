# Phase 14 — Games and Shared Content

## Outcome

Lodges can use a shared Jeopardy-style game engine with platform/shared or lodge-private question content when Games is effectively enabled for the hosting lodge.

## Module Contract

Add the platform-owned `games` module definition and appropriate lodge-scoped Games permissions through Phase 10. Platform availability and lodge enablement are both required to use Games. Module state does not hide or destroy platform-owned/shared reference content itself; it controls whether a lodge may browse/select it through Games or run lodge-owned sessions.

Lodge-private banks and sessions remain inaccessible when ineffective and retain their lodge ownership. Shared-content editing requires its separate platform/shared-content authorization and is not granted by lodge module state.

## Domain Scope

Support platform-shared, regional/shared, and lodge-private question banks with categories, questions, answers, point values, and optional source/reference notes. A lodge-owned game session records hosting lodge, creator, date/time, selected content snapshot/reference, teams, scores, completed questions, reveal state, and Final Round state.

The Jeopardy-style flow supports session creation, question-bank/category selection, multiple teams, score changes, question/answer reveal, completed-question state, and Final Round.

## Disabled-State Behavior

When Games is unavailable or lodge-disabled, lodge Games workspaces, direct routes/APIs, jobs, search, cache, and session projections fail closed. Lodge-private banks and session history remain stored. Re-enabling restores authorized access. Platform-owned reference/shared records remain administrable under their own platform permissions and are not deleted or globally hidden because one lodge loses Games.

## Automated Tests and Acceptance

Test the full availability/preference matrix, module permission independence, cross-lodge private-bank/session denial, shared-bank use, score and Final Round behavior, direct identifiers, search/cache/jobs where applicable, preservation after disablement, and restoration after re-enablement. Manually play a complete session using shared and private content, confirm another lodge cannot see private content, then exercise disable/re-enable and availability revocation.

## Non-Goals

- Real-time internet multiplayer.
- Public game-hosting service.
- Monetized game packs.
- Advanced tournament brackets.
