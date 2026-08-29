# Person-owned ritual discovery with lodge-scoped authorization

## Status

Accepted.

## Decision

Ritual proficiency, availability, credit claims, achievement history, and discovery scope belong to canonical people, not lodges. Ritual Assistance authorizes every request in an explicit active lodge using a local `ritual.search` permission and active membership. It does not reuse directory visibility.

Cross-lodge ritual discovery is an explicit person consent. It may disclose active hosted-lodge affiliations, while email and phone remain governed by existing independent directory field flags. Results are narrow, private/no-store projections and never create assignment, booking, messaging, or certification workflows.

## Consequences

Changing active lodge selection, platform-admin status, or a role in another lodge cannot authorize a request. Person merge must preserve the survivor's visibility or default it to hidden, and conflicts involving private ritual notes or proficiency dates require explicit resolution.
