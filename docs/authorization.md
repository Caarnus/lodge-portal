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
