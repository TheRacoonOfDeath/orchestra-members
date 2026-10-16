# Architecture

orchestra-members extends WordPress users. WordPress stays the source of truth for accounts,
names, email addresses, passwords and roles; this plugin adds the orchestra profile and the
active status.

## Storage

Everything is stored as user meta. There are no custom tables.

| Meta key | Value | Meaning |
|----------|-------|---------|
| `orchestra_members_inactive` | `1` | Present only while the member is inactive. New users are active. |
| `orchestra_members_{field ID}` | see below | The value of a profile field; present only if not empty. Sensitive. |

The option `orchestra_members_profile_fields` holds the profile fields, in display order. Each has a
stable ID (derived from the label when it is created), a label, a type, a hint and, for choice
lists, the choices. Until an administrator saves the fields, the defaults apply: "Financial
support" (`financial_support`, checkbox) and "Dietary restrictions" (`dietary_restrictions`, long
text), whose meta keys are those of earlier versions.

| Type | Stored value |
|------|--------------|
| `checkbox` | `1` |
| `text` | single line, ≤ 200 characters |
| `textarea` | text with line breaks, ≤ 1000 characters |
| `select` | one of the choices |

The type of a field cannot be changed after it was created. Deleting a field deletes its values
for all members. Removing a choice keeps the values already stored until the member changes them.

Only users with an orchestra role (`orchestra_member`, `orchestra_conductor`,
`orchestra_organizer`) have a profile and appear in the member list.

## Components

| Class | Responsibility |
|-------|----------------|
| `Plugin` | Wiring, update checker |
| `Capabilities` | The capabilities of orchestra-members, registered with orchestra-core |
| `MembersService` | Public API for other plugins, see [api.md](api.md) |
| `MemberDirectory` | Finds the users with an orchestra role |
| `Profile\ProfileFields` | The profile fields chosen by the administrators (option) |
| `Profile\ProfileField` | One field: type, normalization and validation of its value |
| `Profile\ProfileFieldsInput` | Reads and validates the form in which the fields are changed |
| `Profile\ProfileRepository` | Reads and writes the user meta |
| `Profile\ProfileInput` | Normalizes and validates the profile form |
| `Ui\ProfileFieldsView`, `Ui\ProfileFieldsController` | The profile fields form and its handler |
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
| `orchestra_members_fields_manage` | | | |

Administrators have every capability. Editing other members' profiles (`user-edit.php`) and
choosing the profile fields are reserved for them.

## Status rules

- Only users with an orchestra role can be set inactive.
- Administrators cannot be set inactive, so the site cannot be locked out.
- Nobody can change their own status.
- Setting a member inactive deletes the sensitive profile data, ends all their sessions and fires
  `orchestra_members_member_deactivated`. Reactivating fires `orchestra_members_member_activated`.
- The profile of an inactive member cannot be filled in.

## Email groups

When orchestra-email is active, orchestra-members registers these groups for it, listed under
"Orchestra". Emailing them requires `orchestra_members_email_send`.

| Group ID | Recipients |
|----------|------------|
| `orchestra_members_everyone` | everyone with an orchestra role |
| `orchestra_members_members` | role Orchestra Member |
| `orchestra_members_conductors` | role Orchestra Conductor |
| `orchestra_members_organizers` | role Orchestra Organizer |

It also removes inactive members from **every** group, including those of other plugins, through
`orchestra_email_recipients`.
