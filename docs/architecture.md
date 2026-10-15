# Architecture

orchestra-members extends WordPress users. WordPress stays the source of truth for accounts,
names, email addresses, passwords and roles; this plugin adds the orchestra profile and the
active status.

## Storage

Everything is stored as user meta. There are no custom tables.

| Meta key | Value | Meaning |
|----------|-------|---------|
| `orchestra_members_inactive` | `1` | Present only while the member is inactive. New users are active. |
| `orchestra_members_financial_support` | `1` | Present only if the member needs financial support. Sensitive. |
| `orchestra_members_dietary_restrictions` | text, ≤ 1000 characters | Sensitive. |

Only users with an orchestra role (`orchestra_member`, `orchestra_conductor`,
`orchestra_organizer`) have a profile and appear in the member list.

## Components

| Class | Responsibility |
|-------|----------------|
| `Plugin` | Wiring, update checker |
| `Capabilities` | The capabilities of orchestra-members, registered with orchestra-core |
| `MembersService` | Public API for other plugins, see [api.md](api.md) |
| `MemberDirectory` | Finds the users with an orchestra role |
| `Profile\ProfileRepository` | Reads and writes the user meta |
| `Profile\ProfileInput` | Normalizes and validates the profile form |
| `Status\StatusService` | Deactivation and reactivation, and who may be deactivated |
| `Status\LoginBlocker` | Blocks inactive members on login |
| `Admin\ProfileSection` | The "Orchestra" section on profile.php and user-edit.php |
| `Admin\MembersPage` | The **Members** screen and the status form |
| `Admin\UserListColumn` | The status column in **Users → All Users** |
| `Privacy\PersonalData` | Exporter, eraser and privacy policy text |
| `Email\MemberEmailGroups` | The global email groups and the exclusion of inactive members |

## Capabilities

See the catalogue in orchestra-core's `docs/capabilities.md`.

| Capability | Member | Conductor | Organizer |
|-----------|:------:|:---------:|:---------:|
| `orchestra_members_directory_read` | ✓ | ✓ | ✓ |
| `orchestra_members_profile_read` *meta* | own | own | own + others |
| `orchestra_members_profile_edit` *meta* | own | own | own |
| `orchestra_members_status_edit` | | | ✓ |
| `orchestra_members_email_send` | | ✓ | ✓ |

Administrators have every capability. Editing other members' profiles is reserved for them
(`user-edit.php`).

## Status rules

- Only users with an orchestra role can be set inactive.
- Administrators cannot be set inactive, so the site cannot be locked out.
- Nobody can change their own status.
- Setting a member inactive deletes the sensitive profile data, ends all their sessions and fires
  `orchestra_members_member_deactivated`. Reactivating fires `orchestra_members_member_activated`.
- The profile of an inactive member cannot be filled in.

## Email groups

With orchestra-core 0.3 or later, orchestra-members registers these groups for the email service,
listed under "Orchestra". Emailing them requires `orchestra_members_email_send`.

| Group ID | Recipients |
|----------|------------|
| `orchestra_members_everyone` | everyone with an orchestra role |
| `orchestra_members_members` | role Orchestra Member |
| `orchestra_members_conductors` | role Orchestra Conductor |
| `orchestra_members_organizers` | role Orchestra Organizer |

It also removes inactive members from **every** group, including those of other plugins, through
`orchestra_core_email_recipients`.
