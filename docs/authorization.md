# Authorization

## Principles

- Authentication, active lodge selection, platform roles, and lodge roles are separate concerns.
- All protected actions use server-side authorization policies.
- Permissions come from a platform-owned catalog. Lodge-defined roles may compose catalog permissions but cannot invent permission identifiers.
- Platform administration does not imply lodge membership or lodge access unless explicitly provided by platform policy for that action.

## Phase 1 Matrix

| Action | Platform administrator | Assigned lodge administrator | Other authenticated user |
|---|---:|---:|---:|
| View/create lodges | Yes | No | No |
| Edit lodge identity | Yes | Assigned lodge only | No |
| Assign lodge administrator | Yes | Assigned lodge only | No |
| Manage feature assignments | Yes | No | No |
| Approve/reject registration | Yes | Selected home lodge only | No |
| Reactivate disabled lodge | Yes | Assigned lodge only | No |
| Reactivate disabled-and-locked lodge | Yes | No | No |

Pending registrants may authenticate only into the pending-approval experience. Selecting a home or active lodge never grants access.

## Public Website Matrix

| Action | Public visitor | Platform administrator | User with `website.manage` | User with `website.publish` |
|---|---:|---:|---:|---:|
| View active lodge's published site | Yes | Yes | Yes | Yes |
| View draft preview | No | Any lodge | Assigned lodge | Assigned lodge |
| Manage branding, pages, navigation, sections, and lodge media | No | Any lodge | Assigned lodge | No, unless separately granted `website.manage` |
| Publish or unpublish pages | No | Any lodge | No, unless separately granted `website.publish` | Assigned lodge |
| Add a custom HTML section | No | Yes | No | No |
| View disabled lodge's public site | No | No | No | No |

`website.manage` and `website.publish` are independent platform-owned permissions. The built-in lodge Administrator role receives both. Every management action authorizes the loaded lodge-owned resource; ownership identifiers supplied in a request are never trusted.

## People and Membership Matrix

| Action | Platform administrator | Required lodge permission and relationship |
|---|---:|---|
| Search/view a person | Any person | `people.view`; person must have an active membership in assigned lodge or a relationship to one of its active members |
| Create a person | Yes | `people.manage` in assigned lodge; creation must also create/reach a lodge-owned relationship |
| Edit shared person identity/contact | Yes | `people.manage` in a lodge where person has an active membership |
| Manage a membership or lodge-private notes | Yes | `memberships.manage` for membership's lodge |
| View family relationships | Yes | `relationships.view`; assigned lodge must have an active member at either endpoint |
| Manage family relationships | Yes | `relationships.manage`; an endpoint's active membership in assigned lodge must name that lodge number as primary |
| Invite/link an exact-email account | Yes | `people.manage` through person's active membership lodge |
| Revoke lodge access | Yes | `roles.manage` for that lodge |
| Manage officer assignments/history | Yes | `officers.manage` for assignment's lodge |
| Create custom roles or assign ordinary roles | Yes | `roles.manage`; actor cannot grant unavailable permissions |
| Assign the built-in Administrator role | Yes | Existing Administrator for the same lodge |
| Merge people or resolve conflicting links | Yes | Never lodge-scoped; platform administrator only |
| View current public officers | Public projection only | No authentication; active lodge and published site only |

The built-in Administrator receives all Phase 3 lodge permissions. The built-in Officer receives `people.view`, `people.manage`, `memberships.manage`, and `relationships.view`, but not `relationships.manage`, role management, or merge access. Member receives `people.view` and `relationships.view`; Non-member receives no administrative Phase 3 permissions by default. Officer position, degree, membership type, and membership status never grant authorization implicitly.

Person authorization has two checks: the actor has a permission in a specific lodge, and that lodge has the required relationship to the person. A permission in Lodge A cannot authorize reading or changing a person who is reachable only through Lodge B. Shared person edits record which lodge relationship authorized the action.

Family relationship ownership records provenance rather than exclusive visibility. Any lodge with an active member at either endpoint may view the relationship with `relationships.view`. Editing additionally requires `relationships.manage` and an endpoint whose active membership in that lodge identifies the lodge's own number as primary. Two lodges may edit one relationship when different endpoints independently qualify; every edit records the authorizing lodge.

Officer terms and lodge roles remain separate transitions. Creating an officer assignment opens a role-assignment prompt but grants nothing automatically. Ending/removing an assignment opens a role-removal prompt and retains the role by default when another current officer assignment exists.
