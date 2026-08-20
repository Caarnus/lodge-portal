# Multi-Lodge Masonic Platform
## Phased Implementation Plan

**Status:** Planning Draft  
**Initial target region:** Southwest Indiana  
**Initial migration target:** Newburgh Lodge No. 174  
**Implementation approach:** Clean-slate application rewrite  
**Primary implementation tool:** Codex  
**Migration strategy:** Build and validate the new platform first; migrate Newburgh Lodge data after the required platform features are complete.

---

# 1. Purpose

This document defines a phased implementation plan for a new multi-lodge Masonic platform.

The platform is intended to:

- Host public-facing websites for multiple lodges.
- Give each lodge control over its own branding, content, officers, events, and enabled features.
- Support a single person/user identity across multiple lodges.
- Strictly isolate lodge-owned private data.
- Allow selected information and features to be shared across participating lodges.
- Support member-controlled cross-lodge directory visibility.
- Preserve or improve the useful features currently available on the Newburgh Lodge website.
- Provide a clean foundation for future regional expansion beyond Southwest Indiana.
- Be implemented in complete, independently testable vertical slices.

---

# 2. Core Architectural Principles

These principles apply to every phase.

## 2.1 Single Application, Shared Database, Tenant-Aware Data

The initial design assumes:

- One application deployment.
- One primary relational database.
- Multiple lodges represented as tenants within that database.
- Lodge-owned records explicitly reference their owning lodge.
- Shared records are intentionally modeled as shared rather than made globally visible by accident.

Separate databases per lodge are not required for the initial regional deployment.

## 2.2 Person, User, and Membership Are Distinct Concepts

The platform must distinguish:

- **Person**: a human being represented in lodge/member records.
- **User**: an authenticated account that may optionally be linked to a person.
- **Lodge Membership**: the relationship between a person and a lodge.

A person may:

- Belong to zero, one, or multiple lodges.
- Have different roles in different lodges.
- Exist without a login account.
- Have family relationships to other people.
- Have per-field or per-scope privacy settings.

## 2.3 Explicit Data Ownership Classification

Every persistent domain model must be categorized as one of:

- **Platform-owned**
- **Lodge-owned**
- **Person-owned**
- **Membership-owned**
- **Shared/reference data**

The implementation should not introduce a new model without determining its ownership category.

## 2.4 Active Lodge Context Is Not Authorization

Selecting or resolving a lodge does not imply the current user belongs to that lodge.

Authorization must independently verify:

- User identity.
- Person linkage where needed.
- Lodge membership.
- Lodge-scoped role or permission.
- Resource ownership.
- Visibility rules.

## 2.5 Cross-Lodge Sharing Must Be Explicit

Information must not become cross-lodge simply because it exists in a shared database.

Cross-lodge access must require an explicit visibility or sharing rule.

## 2.6 Testability Is a Phase Requirement

Every phase must end with:

- A usable vertical slice.
- Automated tests for the functionality introduced.
- Tenant-isolation tests where lodge-owned data is involved.
- Manual acceptance criteria.
- A deployable state.

---

# 3. Proposed High-Level Domain Model

The exact schema may evolve, but the implementation should preserve these conceptual boundaries.

```text
Platform
├── Users
├── People
│   ├── Family / Person Relationships
│   ├── Privacy Preferences
│   └── Lodge Memberships
├── Lodges
│   ├── Branding / Settings
│   ├── Public Website
│   ├── Officers
│   ├── Events
│   ├── Signups
│   ├── Newsletters
│   ├── Galleries
│   ├── Scholarships
│   ├── Ritualist Program
│   └── Lodge-Specific Administration
├── Regional / Shared Features
│   ├── Lodge Directory
│   ├── Shared Event Discovery
│   ├── Cross-Lodge Member Directory
│   ├── Shared Ritual Proficiency Discovery
│   └── Games / Trivia
└── Platform Administration
```

---

# 4. Phase Summary

| Phase | Complete Slice | Primary Outcome |
|---|---|---|
| 1 | Platform Foundation and Lodge Provisioning | A platform administrator can create lodges and lodge admins can sign in and manage basic lodge identity. |
| 2 | Public Lodge Website and Content Management | Each lodge can independently publish and manage a branded public website. |
| 3 | People, Membership, and Lodge Administration | Lodges can maintain member records, accounts, roles, officers, and family relationships. |
| 4 | Events, Recurrence, Signups, and Reminders | Lodges can manage calendars, recurring events, signups, exceptions, and email reminders. |
| 5 | Member Portal and Directory Privacy | Members can manage their profile and control own-lodge versus cross-lodge directory visibility. |
| 6 | Newsletters, Galleries, and Lodge Communications | Lodges can publish newsletters, photo galleries, and member/public communications. |
| 7 | Ritualist Program | Brothers can self-report ritual proficiency, track pin-program points and learning progress, maintain broad availability, and opt into regional ritual-assistance discovery. |
| 8 | Regional Discovery and Cross-Lodge Participation | Participating lodges can intentionally share events, lodge listings, member profiles, and eligible regional resources. |
| 9 | Scholarship Management | Each lodge can securely run independent scholarship cycles and reviews. |
| 10 | Games and Shared Content | Lodges can run shared or private Masonic trivia/game content and sessions. |
| 11 | Newburgh Migration and Production Cutover | Existing Newburgh Lodge data is migrated, validated, and the new platform replaces the existing application. |
| 12 | Regional Lodge Onboarding and Operational Hardening | Additional Southwest Indiana lodges can be onboarded without developer intervention for normal setup tasks. |

---

# 5. Phase 1 — Platform Foundation and Lodge Provisioning

## 5.1 Goal

Create a fully deployable application in which a platform administrator can create lodges, invite or assign lodge administrators, and each lodge administrator can manage basic lodge identity and settings.

This phase establishes tenancy and authorization through real user-facing functionality rather than infrastructure alone.

## 5.2 Assumptions

- A single shared application and database are acceptable.
- Authentication is centralized across the platform.
- A user may eventually belong to multiple lodges.
- Custom public domains are desirable but are not required in this phase.
- Platform administrators are distinct from lodge administrators.
- No existing Newburgh data will be migrated yet.
- Docker Compose is the required and supported local development environment. Developers are not required to install PHP, Composer, Node.js, PostgreSQL, Redis, or Nginx directly on the host.

## 5.3 Functional Requirements

### Platform Administration

A platform administrator must be able to:

- Sign in securely.
- View all lodges.
- Create a lodge.
- Edit basic lodge metadata.
- Set a lodge to active, disabled, or disabled-and-locked status.
- Reactivate a disabled lodge as permitted by the lodge-status rules.
- Assign at least one lodge administrator.
- View enabled features for a lodge.
- Enable or disable feature flags that exist in the current release.

### Lodge Identity

Each lodge must support:

- Lodge name.
- Lodge number.
- URL slug.
- City.
- State.
- Jurisdiction.
- Physical address.
- Optional mailing address.
- Meeting location text.
- Time zone.
- Public contact email.
- Public phone number, optional.
- Lodge status with initial states of active, disabled, and disabled-and-locked. A disabled lodge has its public site disabled and may be reactivated by an authorized lodge administrator; a disabled-and-locked lodge requires a platform administrator to reactivate it.
- Basic branding fields:
  - Logo.
  - Primary color.
  - Secondary color.
  - Defaults should match Indiana Grand Lodge. Placeholder assets and colors may be used until the official assets available from `indianafreemasons.com` are selected and incorporated.

### Authentication and Authorization

The system must support:

- User registration by invitation or administrator creation.
- User self-registration that requires the registrant to identify their home lodge.
- User registration that awaits administrator approval.
- Pending users may authenticate only into a restricted pending-approval experience and receive no normal platform or lodge access.
- A platform administrator or an administrator of the identified home lodge may approve or reject a registration; the decision must be audited.
- Secure authentication.
- Email verification for all users.
- Configurable two-factor authentication for platform and lodge administrators. Two-factor authentication may be disabled by configuration, including for local development and if platform policy does not require it.
- Password reset.
- Platform administrator role.
- Lodge-scoped administrator role.
- One user holding different roles in different lodges.
- An explicit active/current lodge context.
- The home lodge selected during registration is approval-routing information and does not itself grant access. It is not a general user-editable authorization property after registration.

## 5.4 Technical Requirements

- Establish the canonical `users`, `people`, `lodges`, and lodge-role/membership authorization structure. Phase 1 creates only the person and membership fields needed for account matching and authorization; full membership-domain data remains in Phase 3.
- Do not require every user to be linked to a `Person` record yet, but the schema must support it.
- Email addresses associated directly with `Person` records must be unique across people and enforced so two `Person` records cannot share the same email address.
- Registration matching by email must only auto-link when the match is unambiguous.
- Self-registration must record the registrant's identified home lodge so the pending registration can be routed to the applicable approval workflow.
- Custom lodge roles must be composed from platform-defined permissions stored as reference/configuration data rather than arbitrary lodge-defined permissions or hard-coded authorization checks.
- Lodge-scoped permissions must include a lodge identifier.
- A lodge admin must never gain access to another lodge's settings by manipulating IDs or routes.
- Platform administrators must not automatically become lodge members.
- Lodge URL slugs must be globally unique. Authorized changes remain permitted and must be audited; redirect/history behavior may be introduced when public sites are implemented.
- Audit records must be immutable through normal application workflows. Lodge setting and status changes, role assignments, and registration decisions must record the actor, target, lodge where applicable, and relevant before/after JSON.
- Uploaded lodge logos must be associated with the owning lodge.
- Provide an idempotent CLI command that creates or updates the initial platform administrator from explicitly supplied credentials; do not ship permanent default credentials.
- Implement the feature-flag mechanism and platform administration UI without seeding user-facing module flags before those modules exist.

### Local Development Environment

Phase 1 must provide a Docker Compose environment with:

- Nginx at `http://localhost`.
- A reusable PHP 8.4 application image used by PHP-FPM and queue-worker services.
- PostgreSQL with persistent local data.
- Redis for cache, sessions, and queues.
- A Node.js/Vite development service.
- Mailpit for safe local email capture and inspection.
- Persistent application-media storage.
- Service health checks and dependency ordering where appropriate.
- Documented commands for first-time setup, startup, shutdown, logs, tests, migrations, seed data, queue processing, frontend development, and a deliberate full environment reset.

Local development must not require host-installed PHP, Composer, Node.js, PostgreSQL, Redis, or Nginx. Production remains a traditional Ubuntu 24.04 installation unless a later ADR changes that decision.

## 5.5 Acceptance Criteria

A tester can:

1. Create Lodge A and Lodge B as a platform admin.
2. Create separate lodge administrators.
3. Sign in as Lodge A's administrator.
4. Change Lodge A's identity and branding.
5. Confirm Lodge B is inaccessible.
6. Give one user administrative access to both lodges.
7. Switch that user's active lodge context.
8. Confirm permissions remain scoped correctly.

## 5.6 Automated Test Requirements

Tests must include:

- Platform admin can create lodges.
- User can self-register and remains pending until approved.
- Pending users can access only the restricted pending-approval experience.
- Platform and applicable lodge administrators can approve or reject registrations, and the decision is audited.
- Email verification is enforced.
- Administrative two-factor authentication follows configuration and can be disabled for automated/local testing.
- Matching email automatically links a registration to the correct existing person.
- A registration without a safe match does not create an incorrect person linkage.
- Lodge admin can update own lodge.
- Lodge admin cannot update another lodge.
- Multi-lodge administrator can access only explicitly assigned lodges.
- Current lodge context alone does not grant access.
- Disabled lodges have their public site disabled but may be reactivated by an authorized lodge administrator.
- Disabled-and-locked lodges have their public site disabled and may only be reactivated by a platform administrator.
- File uploads cannot be associated with a lodge the user cannot administer.
- Playwright browser tests cover the critical Phase 1 acceptance flow.
- The Docker Compose environment starts from a clean checkout and all service health checks pass.

## 5.7 Non-Goals

This phase does not include:

- Public lodge websites.
- Member directory.
- Events.
- Newsletters.
- Scholarships.
- Ritual tracking.
- Games.
- Newburgh data migration.

---

# 6. Phase 2 — Public Lodge Website and Content Management

## 6.1 Goal

Allow each lodge to create and publish an independently branded public website using controlled, reusable content components.

A lodge should be able to operate its basic public site without developer involvement.

## 6.2 Assumptions

- Lodges need customization but should remain within a supported design system.
- Arbitrary code or CSS is not desirable for lodge administrators.
- Public pages should support both standard templates and lodge-created pages.
- The same application may later serve custom lodge domains.

## 6.3 Functional Requirements

### Public Site

Each lodge receives a public site with:

- Home page.
- Lodge information.
- Navigation.
- Contact page.
- Responsive layout.
- Lodge branding.
- Public-facing URL based initially on the platform host and lodge slug.

Supported branding fields initially include:

- Seal image.
- Logo image.
- Tag line.
- Primary color.
- Secondary color.

### Page Management

Lodge administrators can:

- Create pages.
- Rename pages.
- Change page slugs.
- Reorder navigation.
- Create arbitrary nested menu/navigation hierarchies.
- Hide pages from navigation.
- Publish or unpublish pages.
- Delete eligible pages.
- Preview unpublished changes.

### Content Sections

Supported section types should include at minimum:

- Hero.
- Rich text.
- Image.
- Image with text.
- Link list.
- Call to action.
- Meeting information.
- Contact information.
- Officers placeholder/module slot.
- Upcoming events placeholder/module slot.
- Newsletter placeholder/module slot.
- Gallery placeholder/module slot.

Unavailable feature-driven sections should degrade gracefully until their associated modules are implemented.

Platform administrators may create or manage custom HTML content sections. Lodge administrators may not enter arbitrary custom HTML unless explicitly granted a future permission.

### Templates

Provide at least one default lodge website template capable of generating:

- Home.
- About.
- Events placeholder.
- Officers placeholder.
- Contact.

## 6.4 Technical Requirements

- Public routes must resolve a lodge explicitly.
- No absolute lodge-domain URLs should be stored in managed content when route generation can be used instead.
- Page and section records are lodge-owned.
- Media used by a page must be lodge-owned or intentionally platform-shared.
- Public caching, if used, must include lodge identity in cache keys.
- Rich text must be sanitized.
- Arbitrary JavaScript must not be accepted from lodge administrators.

## 6.5 Acceptance Criteria

A tester can:

1. Create two lodges.
2. Give each different branding.
3. Build a different homepage for each lodge.
4. Publish both sites.
5. Verify content never leaks between lodges.
6. Reorder page content without code changes.
7. Create and publish a custom page.
8. Disable a page and verify it is no longer publicly accessible.
9. Confirm the site works on desktop and mobile layouts.

## 6.6 Automated Test Requirements

Tests must cover:

- Lodge-specific page resolution.
- Cross-tenant page access.
- Publishing state.
- Slug uniqueness within a lodge.
- Same page slug allowed across different lodges.
- Content sanitization.
- Media ownership.
- Navigation generation.
- Feature section fallback behavior.

## 6.7 Non-Goals

This phase does not include:

- Custom domains.
- Member login portal.
- Real event management.
- Real officer management.
- Newsletters.
- Gallery management beyond generic content images.

---

# 7. Phase 3 — People, Membership, and Lodge Administration

## 7.1 Goal

Provide each lodge with a complete private membership-management slice supporting member records, multiple lodge memberships, family relationships, officer assignments, and lodge-scoped roles.

## 7.2 Assumptions

- A `Person` is not the same as a `User`.
- People may exist without login accounts.
- One person may have multiple lodge memberships.
- Spouses and children may be represented as people without lodge memberships.
- A person's core identity should not be duplicated for each lodge membership.
- The exact Grand Lodge membership synchronization process is outside this phase.

## 7.3 Functional Requirements

### Person Records

Authorized lodge users can maintain:

- Legal/preferred name fields.
- Email.
- Phone.
- Mailing address.
- Date of birth if the lodge chooses to track it.
- Profile photo.
- Deceased status and date, where appropriate.
- Notes with appropriate permissions.

### Lodge Memberships

Each membership can include:

- Lodge.
- Membership type, selected from database-configured reference values.
- Membership status, selected from database-configured reference values.
- Degree, selected from database-configured reference values.
- Primary lodge number.
- Member number, optional.
- Entered Apprentice date.
- Fellow Craft date.
- Master Mason date.
- Affiliation/joined date.
- Demit/withdrawal date.
- End date.
- Lodge-specific notes.

Initial membership-type reference values are:

- Initiation.
- Affiliation.
- Dual.
- Honorary.

Initial membership-status reference values are:

- Active.
- Demitted.
- Suspended.
- Expelled.
- Deceased.

Initial degree reference values are:

- Entered Apprentice.
- Fellow Craft.
- Master Mason.

Reference tables must support a configured default value in the database rather than relying on application hard-coding.

Primary lodge is identified by lodge number rather than requiring a foreign-key relationship to a lodge tenant. This allows a member's primary lodge to be represented even when that lodge does not exist on the platform.

### Family Relationships

Support relationships such as:

- Spouse.
- Child.
- Parent.
- Widow/widower relationship where useful.
- Guardian/other configurable relationship if later required.

People records must support deceased status and an optional date of death. Spouse and child records must be retained as needed so lodges can continue supporting widows and orphans after a member's death.

Relationships belong to people rather than being embedded directly on membership rows.

### Account Linking

Authorized administrators can:

- Invite a person to create an account.
- Link an account to the correct person.
- See whether a person has an account.
- Revoke lodge access without deleting the person's global account.

### Officers

Lodges can:

- Define officer positions from a database-configured reference list.
- Assign members to officer positions.
- Store term/year.
- Display current officers through the public website section from Phase 2.
- Preserve historical officer terms.

### Lodge Roles and Permissions

Support the initial lodge-scoped roles:

- Administrator.
- Officer.
- Member.
- Non-member.

Lodges may create custom roles and permission groups.

At minimum:

- Lodge administrators may create additional lodge administrators.
- Platform administrators may create lodge administrators.
- Lodge administrators and officers may update member/non-member status.
- Lodge administrators and officers may edit core membership data.

Masonic degree is membership/profile data and must not be conflated with authorization roles.

## 7.4 Technical Requirements

- A person must not be duplicated merely because they join another lodge.
- Core `Person` identity and contact information is shared person-owned data. An authorized officer of any lodge in which the person has an active membership may update those fields. Changes apply globally to the `Person` and must be audit logged.
- Lodge-specific information must reside on the `Membership` and may only be edited by an authorized user of that lodge.
- The platform must support manual person merging by a platform administrator.
- Import processes must identify attempts to create duplicate people using matching email addresses and should also flag exact full-name matches for review.
- Normal application writes must prevent two `Person` records from sharing the same email address.
- A platform administrator must also be able to manually merge records that do not meet automatic duplicate-detection criteria.
- An email address must only be associated directly with one person.
- Lodge administrators may view person data only through authorized lodge relationships unless broader visibility is explicitly granted later.
- Role assignment must be lodge-scoped.
- Officer records must reference both person/membership and lodge.
- Family relationships must not imply directory visibility.

## 7.5 Acceptance Criteria

A tester can:

1. Create a person with no login.
2. Add that person as a member of Lodge A.
3. Add the same person as a plural/dual member of Lodge B.
4. Assign different lodge roles in each lodge.
5. Invite the person to create one account.
6. Sign in and access both authorized lodges.
7. Add spouse and child records.
8. Assign the member as an officer.
9. Confirm the public officers section updates.
10. Confirm an unauthorized lodge cannot browse the person record.
11. Set a primary lodge number that does not correspond to a lodge tenant on the platform.
12. Update a shared person contact field as an authorized officer and confirm the change is global and audit logged.

## 7.6 Automated Test Requirements

Tests must cover:

- One person with multiple memberships.
- Different roles in different lodges.
- Person without account.
- Account linking.
- Family relationship persistence.
- Officer term history.
- Cross-lodge person access denial.
- Removal of one lodge membership without destroying another.
- Authorized officer updates to shared `Person` identity/contact fields applying globally and being audit logged.
- Membership reference values and configured database defaults.
- Primary lodge number not requiring a platform lodge record.
- Database-configured officer-position reference values.

## 7.7 Non-Goals

This phase does not include:

- Cross-lodge member directory.
- Member-controlled privacy settings.
- Grand Lodge imports.
- Ritualist tracking.
- Event functionality.
- Dues/payment processing.

---

# 8. Phase 4 — Events, Recurrence, Signups, and Reminders

## 8.1 Goal

Provide a complete lodge event-management system with recurring events, occurrence exceptions, individual cancellations, signups, and email reminders.

This phase should reproduce and improve the strongest event-management functionality of the current Newburgh application.

## 8.2 Assumptions

- Recurring events should use an established recurrence representation such as RRULE.
- Individual occurrences must be addressable independently.
- A recurring series belongs to exactly one lodge.
- Signups may be tied to a specific occurrence rather than the whole series.
- Some events are informational only and do not require signups.

## 8.3 Functional Requirements

### Event Management

Authorized lodge users can create:

- One-time events.
- Recurring events.
- Events with visibility of:
  - Public.
  - Masons only.
  - Lodge only.
- Events with or without signup sheets.

Masons-only and lodge-only events must have a required Masonic qualification level. Supported initial required levels are:

- Entered Apprentice (EA), default.
- Fellow Craft (FC).
- Master Mason (MM).
- Past Master (PM).

Eligibility is hierarchical: EA < FC < MM < PM. A member meeting a higher qualification level satisfies a lower requirement. Past Master is an event-eligibility qualification above Master Mason and must not be stored as the member's Masonic degree.

Event data includes:

- Title.
- Description.
- Location.
- Start/end date and time.
- Time zone.
- Category selected from lodge-enabled event categories derived from a platform-wide reference list.
- Cover image.
- Signup settings.
- Visibility.
- Contact information.

### Recurrence

Support:

- RRULE-style recurrence.
- Bounded recurrence where configured.
- Editing the series.
- Editing a single occurrence.
- Cancelling one occurrence.
- Restoring a cancelled occurrence.
- Changing date, time, location, or description for an individual occurrence.
- Preserving series identity.

### Signups

Support:

- Name.
- Email.
- Optional phone.
- Party size or configurable attendee fields.
- Signup limit.
- Public signup without account where permitted.
- Authenticated/member-only signup where configured.
- Per-occurrence signup for recurring events.
- Self-service cancellation/unsubscribe through secure tokens.

### Email Reminders

Support configurable reminders such as:

- One week before.
- One day before.
- One hour before.

The architecture should allow additional reminder schedules later.

### Signup Eligibility

- Lodge-only events may only accept eligible members of the owning lodge.
- Masons-only events may accept eligible authenticated Masons from any lodge represented on the platform when cross-lodge participation is enabled for the event.
- Public events may accept eligible authenticated members and, where configured, public unauthenticated signups.
- Masons-only and lodge-only eligibility must enforce the configured required degree level.
- Members with a higher degree level are eligible for events requiring a lower degree level.
- Only active memberships are eligible for Masons-only or lodge-only event attendance/signup. Demitted, suspended, expelled, and deceased memberships are not eligible.
- The event data model should preserve room for broader cross-lodge event participation later without requiring regional-group infrastructure in this phase.

### Calendar Integration

The platform should provide calendar integration suitable for:

- Google Calendar.
- Apple/iOS Calendar.
- Android calendar applications that accept standard calendar files/links.
- Microsoft Outlook.

The preferred baseline is standards-based ICS/iCalendar generation or subscription where practical, with provider-specific links only where they materially improve usability.

### Public Integration

Published public events must be usable in the public site sections introduced in Phase 2.

## 8.4 Technical Requirements

- Recurrence expansion must be deterministic and unit tested.
- Occurrence identifiers must remain stable enough to associate signups and exceptions.
- Parent event lodge ownership must flow to every occurrence.
- Platform-wide event-category reference values must be stored as reference data, while each lodge may configure which available categories it uses.
- Background jobs must carry lodge and event identity explicitly.
- Reminder jobs must be idempotent.
- Reminder delivery must not duplicate due to queue retries.
- Event URLs must support recurring occurrence context.
- Event cancellation must not delete signup history unless explicitly required.

## 8.5 Acceptance Criteria

A tester can:

1. Create a weekly recurring lodge event.
2. Cancel one occurrence.
3. Move another occurrence to a different night.
4. Change the location of a third occurrence.
5. Enable signup for one or all occurrences as configured.
6. Register for a specific occurrence.
7. Receive a test reminder.
8. Cancel the registration through the secure management link.
9. View correct upcoming occurrences on the public website.
10. Confirm no occurrence can resolve into another lodge's context.

## 8.6 Automated Test Requirements

Tests must cover:

- Recurrence generation.
- DST transitions.
- Single-occurrence cancellation.
- Single-occurrence overrides.
- Signup association with occurrence.
- Reminder deduplication.
- Event visibility.
- Cross-lodge access.
- Signup limits.
- Signup eligibility for public, Masons-only, and lodge-only events.
- Required-qualification hierarchy and default EA requirement.
- Ineligible membership statuses being denied attendance/signup for protected events.
- ICS/iCalendar generation.
- Secure token management.

## 8.7 Non-Goals

This phase does not include:

- Regional event discovery beyond the visibility/eligibility rules needed for this phase.
- Paid ticketing.
- Waitlists.
- Two-way synchronization with external calendars.

---

# 9. Phase 5 — Member Portal and Directory Privacy

## 9.1 Goal

Give authenticated members a personal portal and allow them to control how their profile is visible to their own lodge and to other participating lodges.

## 9.2 Assumptions

- Member privacy requires explicit controls.
- Cross-lodge visibility is opt-in.
- Family information should be more restricted than general member identity.
- Public internet directory exposure is not required.
- One account may represent a person with multiple memberships.

## 9.3 Functional Requirements

### Member Dashboard

A member can view:

- Their lodge memberships.
- Their lodge roles.
- Upcoming events from their lodges.
- Their event signups.
- Their profile.
- Available lodge-specific tools.

### Profile Management

Members can update permitted personal fields such as:

- Preferred name.
- Email.
- Phone.
- Mailing address.
- Profile photo.
- Communication preferences.

Administrative fields may remain locked.

### Directory Visibility

At minimum, support these scopes:

- Hidden.
- Own lodge only.
- Participating lodges.

The default directory visibility for a new member is **Own lodge only**.

The platform should also support field-level visibility for:

- Email.
- Phone.
- Address.
- Profile photo.
- Degree.

The fields eligible for cross-lodge sharing are:

- Name.
- Address.
- Phone number.
- Email address.
- Degree.

Family information must never be shared outside the owning lodge through the directory.

Lodge officers must be able to view all maintained member fields for members of lodges where they currently hold an officer role, regardless of the member's normal directory presentation settings. This broader access is administrative/member-support access and does not change what other members can see.

### Own-Lodge Directory

Members with permission can search their lodge directory according to visibility rules.

## 9.4 Technical Requirements

- Privacy rules must be enforced server-side, not only hidden in the UI.
- Search results must not expose hidden fields.
- Search indexes or caches must respect visibility changes.
- Family relationships must not automatically become visible because the primary member is visible.
- Family details must never be included in cross-lodge directory responses.
- Officer access to complete own-lodge member records must be authorized separately from ordinary directory visibility.
- A person belonging to multiple lodges must receive own-lodge visibility treatment for every lodge in which they are a member.

## 9.5 Acceptance Criteria

A tester can:

1. Log in as a member.
2. See multiple lodge memberships.
3. Update permitted profile information.
4. Set directory scope to own lodge.
5. Confirm another lodge cannot discover the member.
6. Set directory scope to participating lodges.
7. Allow phone but hide address.
8. Confirm another participating lodge sees only permitted fields.
9. Hide the profile entirely.
10. Confirm all directory searches honor the change.

## 9.6 Automated Test Requirements

Tests must cover:

- Visibility scopes.
- Field-level privacy.
- Multi-lodge membership behavior.
- Family privacy.
- Directory searches.
- Cache/search-index invalidation where applicable.
- Unauthorized direct profile requests.

## 9.7 Non-Goals

This phase does not include:

- Public directory exposure.
- Regional lodge discovery.
- Regional event discovery.
- Messaging between members.
- Social-network features.

---

# 10. Phase 6 — Newsletters, Galleries, and Lodge Communications

## 10.1 Goal

Provide lodges with complete publication and media-management tools that integrate into their public and private sites.

## 10.2 Assumptions

- Newsletters and galleries belong to a lodge.
- Some content may be public while other content is member-only.
- Existing Newburgh newsletter concepts can inform the user experience without dictating the data model.
- Email distribution may use a shared platform mail provider with lodge-aware sender/reply-to settings.

## 10.3 Functional Requirements

### Newsletters

Authorized lodge users can:

- Create newsletter issues.
- Assign publication dates.
- Add title and cover image.
- Add rich content or attach a PDF.
- Publish/unpublish issues.
- Set public or member-only visibility.
- Display newsletters through the public site module.

### Galleries

Authorized lodge users can:

- Create albums.
- Upload photos.
- Add captions.
- Reorder photos.
- Set public or member-only visibility.
- Publish/unpublish albums.

### Lodge Email Settings

Each lodge can configure:

- Display sender name.
- Reply-to address.
- Secretary/general contact address.
- Feature-specific contacts where applicable.

### Communications

At minimum:

- Send a test message.
- Deliver newsletter/publication notifications to eligible subscribed members if enabled.
- Honor opt-out preferences.

## 10.4 Technical Requirements

- Media must be lodge-owned.
- Private media URLs must enforce authorization where practical.
- Mail jobs must carry lodge context.
- Unsubscribe/communication preferences must be durable.
- Lodge display names must not alter the authenticated mail-provider identity in an unsafe way.
- Public and member-only caching must remain separate.

## 10.5 Acceptance Criteria

A tester can:

1. Create a public newsletter.
2. Create a member-only newsletter.
3. View each under the correct authorization state.
4. Create a public gallery.
5. Create a private gallery.
6. Configure lodge email identity.
7. Send a test communication.
8. Confirm unsubscribe preferences are respected.
9. Confirm Lodge A cannot manage Lodge B media.

## 10.6 Automated Test Requirements

Tests must cover:

- Publication state.
- Visibility.
- Media ownership.
- Private media authorization.
- Email tenant context.
- Unsubscribe behavior.
- Cross-lodge access.

## 10.7 Non-Goals

This phase does not include:

- Mass marketing automation.
- SMS.
- Advanced email template designer.
- Platform-wide newsletter aggregation.

---

# 11. Phase 7 — Ritualist Program

## 11.1 Goal

Provide a self-service ritual proficiency and discovery system that allows brothers to track the ritual parts they know, the parts they are learning, and their progress toward the ritualist pin program.

The system should also help lodges find brothers who may be able to assist with degree work or other ritual by searching self-reported proficiency together with general availability.

This is not a testing, certification, appointment, or evaluation system.

## 11.2 Assumptions

- Ritual proficiency is primarily self-reported by the individual brother.
- The platform does not certify that a brother is proficient in a ritual part.
- A brother's ritual proficiency belongs to the person and can follow that person across lodge memberships.
- New members are not listed in ritual-assistance discovery by default.
- Cross-lodge ritual visibility is independent of general directory visibility.
- Some ritual parts count toward the ritualist pin program and have defined point values.
- Other useful ritual parts do not count toward the pin program but should still be tracked and searchable.
- A brother may track a part before becoming proficient in it.
- General availability is intended only as a planning/search aid and is not a scheduling commitment.
- Availability should be broad enough to remain easy to maintain, such as day-of-week and time-of-day windows.
- Exact ritual taxonomy and pin-program point values should be configurable as reference data so Indiana requirements can be represented accurately and changed without rewriting application logic.

## 11.3 Functional Requirements

### Ritual Reference Data

The platform must support centrally managed ritual reference data including:

- Ritual category or ceremony type.
- Degree, where applicable.
- Position, role, lecture, charge, prayer, or other ritual part.
- Human-readable part name.
- Optional description.
- Display/order information.
- Whether the part counts toward the ritualist pin program.
- Point value when the part counts toward the pin program.
- Whether the part is active/searchable.
- Optional grouping of related parts.

Reference data must allow useful ritual work that is not part of the pin program to exist without assigning artificial point values.

### Self-Reported Ritual Progress

A brother can maintain their own status for each ritual part.

Supported self-reported proficiency statuses are:

- Not known.
- Learning.
- Proficient.

A separate willingness-to-perform indicator must exist independently of proficiency so a brother may be proficient in a part without currently wishing to perform it.

For a tracked part, the brother may record:

- Current self-reported status.
- Date first marked proficient, optional.
- Personal notes, private by default.
- Visibility scope.
- Whether they are currently willing to be contacted to assist with this part.

No verifier, evaluator, approval, score, or test is required to mark a part proficient.

### Ritualist Pin Progress

The member portal must show:

- Total points earned from ritual parts currently marked proficient that are eligible for the pin program.
- The point value of each qualifying part.
- Which proficient parts do not contribute points.
- Remaining or next-target information where pin thresholds are configured.
- A clear distinction between ritual proficiency and pin-program points.

Point totals must be derived from configurable ritual reference data rather than manually entered totals.

If the pin program has multiple achievement levels or thresholds, the data model should allow those thresholds to be configured without hard-coding them into the member record.

### General Availability

A brother can optionally maintain broad recurring availability preferences.

The system should support availability by:

- Day of week.
- Daypart:
  - Morning.
  - Afternoon.
  - Evening.
- Optional notes.
- Enabled/disabled state.

Examples:

- Monday evening.
- Tuesday evening.
- Saturday morning.
- Generally unavailable on Wednesdays.

Availability is informational only.

The UI must make clear that:

- Availability is not a commitment.
- Availability does not create an appointment.
- Availability does not reserve the brother for an event.
- The requesting lodge must contact the brother separately.

### Ritual Assistance Search

Authorized members can search for brothers who may be able to assist with ritual work.

Search/filter criteria should include:

- Ritual category or ceremony.
- Degree.
- Specific ritual part.
- Self-reported proficiency status.
- Lodge affiliation.
- Participating-lodge/regional scope.
- General day-of-week availability.
- General time-of-day availability.
- Willingness to be contacted for the selected part.

Search results should include only information permitted by the brother's directory and ritual visibility settings.

Useful result information may include:

- Brother's name.
- Lodge affiliation(s) that may be shown.
- Ritual part(s) marked proficient.
- General availability that overlaps the search.
- Contact information permitted by directory privacy settings.

Search results should clearly label proficiency as self-reported.

### Personal Ritual Dashboard

A brother can view:

- Parts marked proficient.
- Parts currently being learned.
- Parts they want to learn.
- Pin-program point total.
- Breakdown of point-eligible parts.
- Proficient parts that do not count toward pin points.
- General availability.
- Ritual visibility settings.

The dashboard should make it easy to update proficiency without requiring lodge administrator involvement.

### Lodge Planning View

Authorized lodge officers or other permitted members can:

- Search their own lodge for needed ritual parts.
- Search participating lodges when regional visibility permits.
- Identify potential brothers who can assist with a particular degree or ritual.
- Filter candidates using broad availability.

The planning view must not assign, schedule, confirm, or book a person.

## 11.4 Technical Requirements

- Self-reported ritual proficiency is person-owned data.
- Ritual reference data and pin-program point rules are platform-owned or jurisdiction/reference data.
- A lodge must not own or overwrite a person's self-reported proficiency merely because that person is a member of the lodge.
- A person's ritual proficiency must not be duplicated for each lodge membership.
- Pin totals must be calculated from current proficiency records and current applicable point definitions.
- If point values change, displayed point totals must reflect the current point values.
- A member must never lose an already achieved ritualist-program rank solely because reference point values later change.
- Achieved ranks therefore require durable historical achievement records separate from the recalculated current point total.
- Non-point ritual parts must use the same proficiency/search model as point-bearing parts.
- Private personal notes must never be included in lodge or regional search results.
- Ritual visibility must be enforced server-side.
- Cross-lodge ritual discovery must be independently enabled by the person and must respect the platform's participating-lodge rules.
- A person may appear in ritual-assistance search even when they have chosen not to appear in the general cross-lodge member directory.
- Search must not expose contact details that the person has hidden through directory privacy settings.
- Any member of a participating regional lodge may perform regional ritual-assistance searches.
- Availability is person-owned preference data and must not be represented as calendar events or reservations.
- Availability matching should tolerate broad overlap rather than imply exact scheduling.
- The system must clearly distinguish self-reported proficiency from any official certification, appointment, or Grand Lodge credential.
- Changes to proficiency and availability should record update timestamps so searchers can judge how current the information may be.
- The system does not need to prompt members periodically to reconfirm proficiency or availability.

## 11.5 Acceptance Criteria

A tester can:

1. Configure ritual reference parts including both point-bearing and non-point parts.
2. Configure point values for eligible ritualist pin parts.
3. Sign in as a member and mark a ritual part as "Learning."
4. Change that part to "Proficient" without administrator approval.
5. Confirm the appropriate point value is automatically added when the part is pin-eligible.
6. Mark a non-point ritual part proficient and confirm it is tracked without changing the pin total.
7. View a clear breakdown of proficiency and ritualist pin points.
8. Enter broad availability for selected days and times.
9. Enable cross-lodge visibility for ritual proficiency.
10. Search from another participating lodge for a brother proficient in a specific degree part.
11. Filter the search by a day/time that overlaps the brother's stated availability.
12. See the brother in results with a clear self-reported indication.
13. Confirm only contact fields allowed by the brother's directory settings are displayed.
14. Disable cross-lodge ritual visibility and confirm the brother disappears from regional ritual search.
15. Confirm the search workflow provides contact/discovery information but does not create an assignment, reservation, or appointment.

## 11.6 Automated Test Requirements

Tests must cover:

- Self-service creation and update of ritual proficiency records.
- No approval requirement for marking a part proficient.
- Pin-point calculations.
- Exclusion of non-point parts from pin totals.
- Multiple point-bearing parts and aggregate totals.
- Configurable point values.
- Ritual reference-data activation/deactivation.
- Person ownership of proficiency across multiple lodge memberships.
- Ritual visibility scopes.
- Directory privacy interaction with ritual search.
- Cross-lodge regional search authorization.
- Willingness-to-assist filtering.
- Availability storage.
- Day/time overlap matching.
- Hidden/private availability behavior where applicable.
- Private-note exclusion from search results.
- Direct URL/API attempts to access non-visible ritual data.
- Search results identifying proficiency as self-reported.

## 11.7 Non-Goals

This phase does not include:

- Testing or evaluating ritual proficiency.
- Lodge or Grand Lodge certification of proficiency.
- Required verifier or approver workflows.
- Assigning brothers to degree teams.
- Appointment scheduling.
- Calendar booking.
- Availability confirmations.
- Automated substitute requests.
- Automated messaging or dispatch to matching brothers.
- Attendance tracking for degree work.
- Grand Lodge certification synchronization.

---

# 12. Phase 8 — Regional Discovery and Cross-Lodge Participation

## 12.1 Goal

Turn multiple isolated lodge sites into an intentionally connected Southwest Indiana network without weakening tenant isolation.

## 12.2 Assumptions

- Participating lodges opt into regional visibility.
- Public lodge metadata can be shared regionally.
- Member profile visibility remains member-controlled.
- Event visibility remains controlled by the event-owning lodge.
- Regional grouping should not depend on one hard-coded Masonic hierarchy.

## 12.3 Functional Requirements

### Lodge Directory

Provide a regional directory containing participating lodges with:

- Lodge name/number.
- City.
- Meeting location.
- Meeting schedule.
- Website link.
- Contact information.
- Public branding/logo.

### Regional Events

Allow events to have visibility such as:

- Lodge-private.
- Own-lodge members.
- Participating-lodge members.
- Public.

Members can browse:

- Their lodge events.
- Participating lodge events.
- Public regional events.

### Cross-Lodge Signups

Eligible events may accept:

- Public signups.
- Own-lodge members.
- Participating-lodge members.

The signup should retain the attendee's person and lodge affiliation when authenticated.

### Cross-Lodge Directory

Members who opted into participating-lodge visibility may be found by eligible members of other lodges.

### Regional Grouping

Support configurable generic lodge groups such as:

- Southwest Indiana.
- District grouping.
- Future regional groups.

A lodge may belong to multiple regional groups.

For the initial implementation, platform administrators control creation and membership of regional groups. The authorization model should leave room for future group-specific administrative roles without requiring them now.

## 12.4 Technical Requirements

- Shared discovery must never bypass the underlying resource visibility rule.
- Regional queries must intentionally select visible records rather than disabling tenant scopes globally.
- Membership identity shown at cross-lodge events must follow privacy rules.
- Lodge groups must be configurable entities, not hard-coded enums.
- Search/cache layers must respect lodge participation changes.

## 12.5 Acceptance Criteria

A tester can:

1. Opt Lodge A and Lodge B into a regional group.
2. Keep Lodge C private.
3. Publish a Lodge A public event.
4. Publish a Lodge B participating-members-only event.
5. Confirm appropriate visibility from each account type.
6. Sign a Lodge A member up for a Lodge B event.
7. Find a member from another lodge only when that member opted in.
8. Remove a lodge from regional participation and confirm shared discovery stops.

## 12.6 Automated Test Requirements

Tests must cover:

- Lodge participation.
- Event visibility matrix.
- Cross-lodge signup eligibility.
- Cross-lodge directory privacy.
- Regional group membership.
- Removal from group.
- Direct URL authorization.
- Shared queries without tenant leakage.

## 12.7 Non-Goals

This phase does not include:

- Public member directories.
- Inter-lodge direct messaging.
- Automated district governance.
- Grand Lodge data exchange.

---

# 13. Phase 9 — Scholarship Management

## 13.1 Goal

Provide each lodge with an isolated scholarship application and review workflow.

## 13.2 Assumptions

- Scholarship applicant data is sensitive and lodge-specific.
- Each lodge may have different application windows and reviewer committees.
- Regional discovery does not imply shared scholarship access.
- The current Newburgh scholarship workflow can inform requirements.

## 13.3 Functional Requirements

### Scholarship Module

- The scholarship module is disabled by default for a lodge.
- A platform or authorized lodge administrator may enable it for lodges that want to use the feature.

### Scholarship Cycles

Lodges with the module enabled can configure:

- Name.
- Application open/close dates.
- Award description.
- Instructions.
- Eligibility text.
- Required fields.
- Lodge-defined custom application questions when the scholarship module is enabled.
- Required uploads.
- Reviewer assignments.

### Applications

Applicants can:

- Start an application.
- Save as allowed.
- Upload required documents.
- Verify email if required.
- Submit before the deadline.
- Receive confirmation.

### Review

Authorized reviewers can:

- View eligible applications.
- Score applications.
- Enter private reviewer notes.
- Submit reviews.
- See aggregate scores if permitted.
- Change application status according to workflow.

### Administration

Lodge scholarship administrators can:

- Export application data.
- View review progress.
- Change statuses.
- Close/archive cycles.

## 13.4 Technical Requirements

- Every application is owned by exactly one lodge and one scholarship cycle.
- Reviewers require explicit lodge-scoped scholarship permission.
- Platform administrators should not be treated as scholarship reviewers by default.
- Uploaded transcripts/documents must be private.
- Download access must be authorized.
- Sensitive documents must not use public media URLs.
- Audit important status and review changes.

## 13.5 Acceptance Criteria

A tester can:

1. Configure scholarship cycles independently for two lodges.
2. Submit applications to each lodge.
3. Assign distinct reviewer committees.
4. Confirm Lodge A reviewers cannot access Lodge B applications.
5. Upload and securely retrieve documents.
6. Score an application.
7. Produce aggregate review results.
8. Archive the cycle.

## 13.6 Automated Test Requirements

Tests must cover:

- Lodge isolation.
- Application deadlines.
- Upload authorization.
- Reviewer permissions.
- Scoring.
- Status transitions.
- Email verification where configured.
- Direct document URL protection.
- Scholarship module disabled by default.
- Lodge-defined custom application questions when the module is enabled.

## 13.7 Non-Goals

This phase does not include:

- Shared regional scholarships.
- Payment of awards.
- School transcript API integrations.
- Automated applicant ranking decisions.

---

# 14. Phase 10 — Games and Shared Content

## 14.1 Goal

Provide reusable Masonic game functionality, beginning with a Jeopardy-style trivia game, while allowing both platform-shared and lodge-private content.

## 14.2 Assumptions

- Game engines are platform-level functionality.
- Question banks may have different ownership and visibility.
- A lodge should be able to host a game session without duplicating the engine.
- Shared question banks can reduce duplicated content effort.

## 14.3 Functional Requirements

### Question Banks

Support:

- Platform-shared banks.
- Regional/shared banks.
- Lodge-private banks.
- Categories.
- Questions.
- Answers.
- Point values.
- Optional source/reference notes.

### Jeopardy-Style Game

Support:

- Create game session.
- Select question bank/categories.
- Multiple teams.
- Score tracking.
- Question reveal.
- Answer reveal.
- Completed-question state.
- Final-round support in the first release.

### Session Ownership

Game sessions should record:

- Hosting lodge.
- Creator.
- Date/time.
- Selected content.
- Session state.

## 14.4 Technical Requirements

- Lodge-private questions remain isolated.
- Shared banks are explicitly designated shared.
- Game sessions are lodge-owned.
- Shared content editing requires appropriate platform or shared-content permissions.
- Question history should not leak private banks into shared search.

## 14.5 Acceptance Criteria

A tester can:

1. Create a lodge-private question bank.
2. Use a platform-shared question bank.
3. Start a game session.
4. Add teams.
5. Play through questions.
6. Play and score a Final Round.
7. Track scores.
8. Confirm another lodge cannot see the private bank.
9. Confirm shared content is usable across lodges.

## 14.6 Automated Test Requirements

Tests must cover:

- Question-bank visibility.
- Session ownership.
- Score behavior.
- Final Round behavior.
- Shared versus private access.
- Cross-lodge private-content denial.

## 14.7 Non-Goals

This phase does not include:

- Real-time internet multiplayer.
- Public game hosting service.
- Monetized game packs.
- Advanced tournament brackets.

---

# 15. Phase 11 — Newburgh Migration and Production Cutover

## 15.1 Goal

Migrate the existing Newburgh Lodge application data into the new platform and replace the legacy site without loss of required functionality or historical data.

## 15.2 Assumptions

- The new platform has already been validated independently.
- The old application's schema should not dictate the new application's schema.
- Some legacy records may require transformation or cleanup.
- The existing domain should continue to be usable after cutover.
- A rollback plan is required.

## 15.3 Functional Requirements

Migrate applicable Newburgh data including, where present:

- Public pages/content tiles.
- Lodge identity/settings.
- Officer records.
- Past officer history.
- Members.
- Membership dates.
- Spouses/children/family relationships.
- Widows/orphans or related relationship records.
- Events.
- Recurrence rules.
- Occurrence overrides/cancellations.
- Event signups.
- Newsletters.
- Galleries/photos.
- Scholarship data.
- Ritualist tracking data.
- Existing user accounts where safe and feasible.
- Game/question data where applicable.

### Domain and URL Cutover

Support:

- `newburghlodge174.org` resolving to the Newburgh tenant.
- Preservation or redirects for important legacy URLs.
- New tenant-aware route generation.

## 15.4 Technical Requirements

- Migration scripts must be repeatable in a staging environment.
- Source data must not be mutated by migration.
- Mapping/transformation rules must be documented.
- Record counts and key invariants must be validated.
- User/password migration must be handled securely; forced password reset is acceptable if required.
- Media files must be copied with ownership metadata.
- Recurrence exceptions must be verified carefully.
- A final read-only or maintenance window may be used for final synchronization.

## 15.5 Acceptance Criteria

A tester can verify:

1. Public Newburgh pages are present.
2. Branding and lodge metadata are correct.
3. Current officers are correct.
4. Historical member data is intact.
5. Family relationships are intact.
6. Upcoming recurring events produce the same expected occurrences.
7. Existing cancellations/exceptions are preserved.
8. Event signups are associated correctly.
9. Newsletters and galleries are accessible at the proper visibility level.
10. Scholarship records are intact and private.
11. Ritualist records are transformed correctly.
12. The production domain resolves to the new platform.
13. Required legacy URLs redirect or remain functional.

## 15.6 Automated Test Requirements

Create migration validation tests or scripts for:

- Record counts.
- Required-field mapping.
- Orphan detection.
- Duplicate person detection.
- Membership integrity.
- Event recurrence comparison.
- Signup counts.
- Media references.
- Scholarship ownership.
- User-account linkage.

## 15.7 Non-Goals

This phase does not include:

- Migrating other lodges.
- Supporting every obsolete legacy behavior.
- Preserving implementation-specific quirks that are not valid business requirements.

---

# 16. Phase 12 — Regional Lodge Onboarding and Operational Hardening

## 16.1 Goal

Make the platform practical for onboarding additional Southwest Indiana lodges without requiring custom development for each normal deployment.

## 16.2 Assumptions

- Newburgh has been migrated successfully.
- At least one additional lodge is available as a pilot.
- Platform administrators may initially handle account provisioning.
- Billing is not required for initial adoption.

## 16.3 Functional Requirements

### Lodge Onboarding Wizard

A platform administrator can:

1. Create lodge.
2. Enter lodge identity.
3. Choose a default website template.
4. Upload branding.
5. Configure meeting information.
6. Select enabled features.
7. Assign initial lodge administrator.
8. Configure public hostname/domain.
9. Preview.
10. Publish.

### Custom Domains

Support:

- Platform subpath or subdomain.
- Custom lodge domain.
- Platform administrator entry of the desired custom domain.
- Display of the DNS record or records that must be created.
- Verification that DNS resolves to the platform before activation.
- Verification that the hostname maps to the intended lodge.
- Automatic TLS certificate issuance and renewal by the hosting layer where possible.
- A domain must not be activated for public use until verification succeeds.

DNS changes themselves remain the responsibility of the lodge/domain administrator and are not performed automatically by the application.

### Data Import

Provide at least a documented and testable process for importing:

- Member roster.
- Basic member contact information.
- Membership dates.
- Officers where practical.

CSV import is sufficient initially.

### Operational Administration

Platform administrators need:

- Lodge status view.
- Domain status.
- Recent background-job failures.
- Email delivery diagnostics.
- Storage usage visibility if appropriate.
- Ability to disable or disable-and-lock a compromised or retired lodge.

## 16.4 Technical Requirements

- Domain-to-lodge resolution must be secure and deterministic.
- Unknown hostnames must not fall back to arbitrary lodge content.
- Onboarding must not require code deployment.
- Import validation must provide actionable errors.
- Background queue failures must preserve lodge context in logs.
- Backups and restore procedures must be documented.
- Production observability must include tenant/lodge identifiers where safe.

## 16.5 Acceptance Criteria

Using only platform administration tools and documented import processes, a tester can:

1. Create a new lodge.
2. Apply a template.
3. Configure branding.
4. Import a member roster.
5. Create an administrator.
6. Configure a custom domain.
7. Publish the lodge website.
8. Create an event.
9. Add members.
10. Use the directory.
11. Verify tenant isolation against Newburgh.
12. Disable the lodge and confirm the expected behavior.

## 16.6 Automated Test Requirements

Tests must cover:

- Domain resolution.
- Unknown domains.
- Onboarding workflow.
- Feature flags.
- CSV validation.
- Cross-tenant import safety.
- Disabled and disabled-and-locked behavior, including the correct reactivation permissions.
- Custom-domain tenant selection.

## 16.7 Non-Goals

This phase does not include:

- Automated billing.
- Self-service paid subscriptions.
- Nationwide Grand Lodge hierarchy.
- Multi-region infrastructure.
- Franchise-style white labeling beyond supported lodge branding.

---

# 17. Cross-Cutting Requirements

The following requirements apply to all applicable phases.

## 17.1 Authorization

- Server-side authorization is mandatory for every protected action.
- Resource ownership must be validated, not inferred from route context.
- Lodge administrators may only administer explicitly authorized lodges.
- Platform roles and lodge roles must remain distinct.

## 17.2 Tenant Isolation

Every applicable feature must test:

- ID manipulation.
- Direct URL access.
- Search.
- Exports.
- Background jobs.
- File/media access.
- Cached data.
- API responses.

## 17.3 Auditability

Sensitive administrative actions should record:

- Actor.
- Lodge.
- Action.
- Target resource.
- Timestamp.
- Relevant before/after metadata where justified.

At minimum this should cover:

- Membership changes.
- Role changes.
- Scholarship status/review changes.
- Lodge configuration changes.
- Sensitive account-linking operations.

## 17.4 Background Jobs

Every lodge-related job must explicitly retain enough context to identify:

- Lodge.
- Related resource.
- Intended recipient or action.

Jobs must not rely on an HTTP request's current-lodge state.

## 17.5 Email

Email must support:

- Central delivery infrastructure.
- Lodge-aware display sender.
- Lodge-specific reply-to.
- Durable unsubscribe preferences.
- Idempotent scheduled delivery.
- Delivery logging sufficient for troubleshooting.

## 17.6 File Storage

Files must have explicit ownership and visibility.

Private files must not become publicly accessible merely because the storage backend supports public URLs.

## 17.7 Search

Search must enforce the same authorization and visibility rules as direct record access.

Search must not become a bypass for privacy or tenant isolation.

## 17.8 Caching

Cache keys for lodge-owned or visibility-sensitive data must contain enough identity and scope information to prevent cross-tenant leakage.

## 17.9 Time Zones

Each lodge has a configured time zone.

Events, reminders, recurrence, and date presentation must consistently respect lodge/event time zones.

## 17.10 Accessibility and Responsive Design

Public and authenticated interfaces should:

- Be usable on desktop and mobile.
- Follow reasonable accessibility standards.
- Avoid interactions that require hover-only behavior.
- Preserve readable contrast when lodge branding is applied.

## 17.11 Backup, Restore, and Portability

The platform must support both:

- Full-platform backup and restoration for migration to new hardware.
- Lodge-scoped export/backup capability sufficient to support onboarding, offboarding, or recovery of an individual lodge without requiring unrelated lodge data to be restored.

The design should:

- Keep application configuration portable.
- Include database data and locally stored media in documented backup procedures.
- Preserve lodge ownership metadata in exports.
- Avoid backup formats that make individual-lodge extraction impractical.
- Allow future S3-compatible or other cloud object storage without redesigning the domain model.

## 17.12 Logging and Monitoring

The initial deployment should favor free or open-source operational tooling.

At minimum, the deployment must provide:

- Application logs.
- Nginx access/error logs.
- PHP/runtime logs.
- Queue worker logs.
- Failed-job visibility.
- Disk-space monitoring.
- Basic CPU/memory/load monitoring.
- Service health visibility.

The implementation should avoid requiring a paid monitoring SaaS for normal operation. Platform/lodge identifiers should be included in application log context where appropriate and safe.

---

# 18. Implementation Stack

The initial implementation stack is:

- Laravel 13.
- PHP 8.4.
- PostgreSQL.
- Vue 3.
- Inertia.
- PrimeVue in unstyled mode with Tailwind CSS for styling, layout, and lodge branding.
- Redis for queue/cache if operationally justified.
- Queue workers for reminders, mail, imports, and media processing.
- Local filesystem storage initially for application media.
- Storage abstraction must preserve the option to move to S3-compatible or other cloud object storage later.
- Locally hosted email on the application infrastructure initially.
- Optional support for a transactional email provider later if operational needs justify it.

## 18.1 Production Hosting Assumptions

Initial production hosting is expected to use:

- Ubuntu 24.04.
- Nginx as the web server/reverse proxy.
- PHP-FPM appropriate to the selected PHP version.
- PostgreSQL hosted locally or on infrastructure controlled with the application.
- Local mail services on the Linux host initially.
- Queue workers managed as persistent services.
- Automated TLS certificate issuance/renewal where possible.

Exact package versions and service topology should be pinned in the deployment documentation when implementation begins.

## 18.2 Local Development Environment

Local development uses Docker Compose and exposes the application at `http://localhost`. Nginx, PHP-FPM, PostgreSQL, Redis, a queue worker, Node.js/Vite, and Mailpit run as separate services. PHP-FPM and queue workers share the same application image. Database data and application media use persistent volumes, with a documented explicit reset procedure.

Docker Compose is the required development interface; production is not required to use containers. The initial production baseline remains a conventional Ubuntu installation.

---

# 19. Recommended Repository Documentation Before Coding

Before Phase 1 implementation begins, create these project documents:

```text
/docs
  architecture.md
  domain-model.md
  tenancy-rules.md
  authorization.md
  coding-standards.md
  testing-strategy.md
  phase-01.md
  decisions/
```

Each phase specification should include:

- Schema changes.
- Domain rules.
- Routes/endpoints.
- UI views/components.
- Authorization matrix.
- Background jobs.
- Emails.
- Import/export behavior.
- Automated tests.
- Manual acceptance tests.
- Explicit non-goals.

Architectural decisions that affect later phases should be recorded as ADRs rather than left only in Codex conversation history.

---

# 20. Definition of Done for Every Phase

A phase is complete only when all of the following are true:

- Functional requirements are implemented.
- Automated tests pass.
- Cross-tenant tests pass where applicable.
- Authorization rules are covered by tests.
- Migrations run cleanly on a fresh database.
- Seed/demo data can exercise the feature.
- Manual acceptance criteria have been executed.
- No known blocker prevents deploying the application at the end of the phase.
- Documentation is updated.
- Deferred work is explicitly recorded rather than silently omitted.
- The next phase can begin without requiring unfinished foundational work from the current phase.

---

# 21. Recommended First Pilot Sequence

The practical rollout should be:

1. Build Phases 1-10 on the new platform using synthetic/demo lodges.
2. Continuously exercise at least two lodges in automated and manual tests to detect tenancy assumptions.
3. Perform Phase 11 using Newburgh Lodge as the first real production tenant.
4. Operate Newburgh on the new platform long enough to validate day-to-day behavior.
5. Use one additional Southwest Indiana lodge as the Phase 12 pilot.
6. Refine onboarding based on that real second-lodge experience.
7. Begin broader Southwest Indiana adoption only after the second lodge can be operated without custom development.

---

# 22. Resolved Design Decisions and Deferred Detail

The following decisions are considered established planning requirements unless a later architectural decision record explicitly changes them.

## 22.1 Identity and Membership

- Users may self-register.
- Self-registration requires the registrant to identify their home lodge.
- Self-registration requires approval by a platform administrator or an applicable lodge administrator before normal access is granted.
- If the registering email uniquely matches an existing person record, the account should be linked to that person automatically.
- Person records may be merged manually by a platform administrator.
- Import processes must identify duplicate-person conflicts using matching email addresses and should also flag exact full-name matches for review.
- Normal application writes must enforce uniqueness so two `Person` records cannot share the same email address.
- Platform administrators may manually merge people even when the automatic indicators are absent.
- An email address may only be directly associated with one person.
- Shared family contact information should be represented through person relationships rather than assigning the same email to multiple people.
- People records support:
  - Deceased status.
  - Optional date of death.
- Spouses and children remain represented in the system as appropriate so lodges can support widows and orphans.
- Core `Person` identity/contact information is shared person-owned data. An authorized officer of any lodge in which the person has an active membership may update those fields. Changes apply globally and must be audit logged.
- Lodge-specific information belongs on the `Membership` and may only be edited by an authorized user of that lodge.

## 22.2 Lodge Administration

Initial built-in lodge roles are:

- Administrator.
- Officer.
- Member.
- Non-member.

Additional rules:

- Lodges may define custom roles using platform-defined permissions. Lodges may choose from the platform permission catalog but may not create arbitrary new permission definitions.
- Platform administrators and existing lodge administrators may create additional lodge administrators.
- Lodge administrators and officers may update member/non-member status.
- Lodge administrators and officers may edit core membership data.
- Degree is member/membership information rather than an authorization role.


### Membership Reference Data

Membership-related reference values are database-configured and include a database-controlled default where applicable.

Initial membership types are:

- Initiation.
- Affiliation.
- Dual.
- Honorary.

Initial membership statuses are:

- Active.
- Demitted.
- Suspended.
- Expelled.
- Deceased.

Initial degree values are:

- Entered Apprentice.
- Fellow Craft.
- Master Mason.

Primary lodge is represented by lodge number and does not require the lodge to exist as a platform tenant.

Officer positions are database-configured reference values.

## 22.3 Privacy

- Default directory visibility is own-lodge only.
- Cross-lodge shareable fields are:
  - Name.
  - Address.
  - Phone number.
  - Email address.
  - Degree.
- Officers may view all maintained fields for members of lodges where they are currently officers.
- Family information is not shared outside the owning lodge.
- Another lodge needing family-related information must contact the owning lodge rather than retrieving it through the regional directory.

## 22.4 Public CMS

Initial content-section support is the catalog defined in Phase 2.

Additional rules:

- Lodges may create arbitrary navigation/menu hierarchies.
- Platform administrators may create custom HTML content.
- Initial lodge branding consists of:
  - Seal image.
  - Logo image.
  - Tag line.
  - Primary color.
  - Secondary color.
- Additional components or branding controls may be added later if the initial set proves insufficient.

## 22.5 Events

Event visibility is:

- Public.
- Masons only.
- Lodge only.

Masons-only and lodge-only events require a minimum Masonic qualification. Initial supported requirements are:

- Entered Apprentice (EA), default.
- Fellow Craft (FC).
- Master Mason (MM).
- Past Master (PM).

Eligibility is hierarchical: EA < FC < MM < PM. A member meeting a higher qualification may attend an event requiring a lower qualification. Past Master is an event-eligibility qualification rather than a Masonic degree. Only active memberships are eligible; demitted, suspended, expelled, and deceased memberships are not eligible for Masons-only or lodge-only events.

Signup behavior:

- Lodge-only events do not permit members of other lodges to sign up.
- Masons-only events may permit eligible authenticated Masons from any lodge represented on the platform when cross-lodge participation is enabled for the event.
- Public events may permit authenticated members and, where configured, public unauthenticated signups.
- The data model should support broader cross-lodge event participation later without requiring regional-group infrastructure in Phase 4.
- Waitlists are not required in the initial implementation.

Event categories are lodge-configured selections based on a predetermined platform-wide reference list.

Calendar behavior:

- Events should integrate with Google Calendar.
- Events should support calendar creation/import for Apple/iOS, Android, and Outlook.
- Standards-based ICS/iCalendar support is the preferred common mechanism.
- Two-way external calendar synchronization is not required initially.

## 22.6 Ritualist Program

The detailed ritual taxonomy, official point values, rank thresholds, and complete list of tracked non-point parts are intentionally deferred to the Phase 7 specification.

Established rules are:

- Self-reported proficiency statuses:
  - Not known.
  - Learning.
  - Proficient.
- Willingness to perform is a separate indicator from proficiency.
- New members are not included in ritual-assistance search by default.
- Cross-lodge ritual visibility is independent from general directory visibility.
- Any member of a participating regional lodge may perform regional ritual-assistance searches.
- Availability uses dayparts:
  - Morning.
  - Afternoon.
  - Evening.
- Availability remains informational and is not an appointment or booking system.
- Current point totals always reflect current point values for parts in which the member is proficient.
- A member never loses a previously achieved ritualist-program rank solely because point values later change.
- Members are not periodically prompted to reconfirm proficiency or availability.

## 22.7 Regional Organization

- Regional structures use generic groups initially.
- Platform administrators control regional group creation and membership.
- Future organization-specific administrative roles may be added later.
- A lodge may belong to multiple regional groups.

## 22.8 Scholarship Module

- The scholarship module is disabled by default for each lodge.
- Lodges that enable the scholarship module may define custom application questions in addition to the platform-supported scholarship fields.

## 22.9 Games

- The Jeopardy-style game includes Final Round support in the first release.

## 22.10 Operations

### Hosting

- Initial production server OS: Ubuntu 24.04.
- Web server: Nginx.
- Local development: Docker Compose with separate Nginx, PHP-FPM/application, PostgreSQL, Redis, queue-worker, Node.js/Vite, and Mailpit services.
- Production deployment: conventional Ubuntu services initially; production containerization requires a later ADR.

### Object Storage

- Media is hosted on the web application server initially.
- The storage architecture must support migration to S3-compatible or other cloud storage later.

### Email

- Email is initially hosted on the Linux machine running the application.
- A transactional email provider remains an optional future deployment choice.

### Backup and Restore

The backup strategy must support:

- Full-platform backup and migration to new hardware.
- Lodge-scoped backup/export for individual-lodge onboarding, offboarding, or recovery.
- Preservation of lodge ownership boundaries.
- Database and media portability.

### Logging and Monitoring

- There is no existing monitoring platform requirement.
- Initial monitoring should use free/open-source tooling where practical.
- A paid SaaS monitoring dependency should not be required for ordinary operation.

### Lodge Deactivation

- A lodge may be **disabled**, which disables its public site but allows an authorized lodge administrator to reactivate it.
- A lodge may be **disabled and locked**, which disables its public site and requires a platform administrator to reactivate it.

### Custom Domains and TLS

- Platform administrators enter the desired lodge domain.
- The system displays the required DNS records.
- The platform verifies DNS points to the correct hosting environment.
- The platform verifies that the hostname resolves to the intended lodge before activation.
- The hosting layer automatically obtains and renews TLS certificates where possible.
- DNS record changes remain a manual responsibility outside the application.

---

# 23. Final Planning Recommendation

The rewrite should begin with a detailed Phase 1 specification rather than a whole-application Codex prompt.

For each phase:

1. Finalize unresolved domain decisions relevant to that phase.
2. Produce a phase-specific Codex implementation specification.
3. Implement only that phase.
4. Run automated and manual acceptance tests.
5. Record any architectural decisions.
6. Commit a stable, deployable milestone.
7. Proceed to the next vertical slice.

This approach keeps the new platform continuously testable while preventing early schema shortcuts from silently constraining later multi-lodge functionality.
