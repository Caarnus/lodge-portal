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
