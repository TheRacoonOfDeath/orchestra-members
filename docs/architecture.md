# Architecture

orchestra-members extends WordPress users. WordPress stays the source of truth for accounts,
names, email addresses, passwords and roles; this plugin adds the orchestra profile and the
active status.

## Storage

Everything is stored as user meta. There are no custom tables.

| Meta key | Value | Meaning |
|----------|-------|---------|
| `orchestra_members_inactive` | `1` | Present only while the member is inactive. New users are active. |
| `orchestra_members_{field ID}` | see below | The value of a profile field; present only if not empty or an unticked checkbox. Sensitive. |

The option `orchestra_members_profile_fields` holds the profile fields, in display order. Each has a
stable ID (derived from the label when it is created), a label, a type, a hint and, for choice
lists, the choices. Until an administrator saves the fields, the defaults apply: "Financial
support" (`financial_support`, checkbox) and "Dietary restrictions" (`dietary_restrictions`, long
text), whose meta keys are those of earlier versions.

| Type | Stored value |
|------|--------------|
| `checkbox` | `1` (ticked) or `0` (unticked) |
| `text` | single line, ≤ 200 characters |
| `textarea` | text with line breaks, ≤ 1000 characters |
| `select` | one of the choices, or the free-text answer (≤ 100 characters) |

A checkbox can be ticked by default (`checked` in the option). The default is the answer of an
active member who has not answered the field yet (nothing stored), everywhere: on the profile, in
the member view, the participants CSV of orchestra-projects and the privacy export. Unticking is
stored as `0`, so it is kept when the default changes. Inactive members have no defaults, since
their profile was deleted; after a reactivation the default applies again. Up to 0.15, unticked
checkboxes were not stored, so turning on a default also ticks the box of members who unticked it
before 0.16 and have not saved their profile since.

A choice list can offer "Other" with a free-text answer (`other` in the option): the profile shows
an "Other" choice and a text box (`orchestra_members_other[ID]`). The text counts when "Other" or
nothing is chosen and is stored as the value; a value that is not one of the choices is shown as
the free-text answer. Without the option, such a value is a removed choice (see below).

The type of a field cannot be changed after it was created. In the row of a new field, only the
options of the chosen type are shown ("Ticked by default" for checkboxes, "Choices" and "Other"
for choice lists), with `data-orchestra-switch` of orchestra-core 0.14; with an older core or
without JavaScript all are shown, and the options of other types are ignored when saving. Deleting a field deletes its values
for all members. Removing a choice keeps the values already stored until the member changes them.

Only users with an orchestra role (`orchestra_member`, `orchestra_conductor`,
`orchestra_organizer`) have a profile and appear in the member list.

### Orchestra role next to other roles

A user has at most one Orchestra role, which can come in addition to other WordPress roles (e.g.
an administrator who plays, or an editor of the website). WordPress's own screens offer a single
role, and saving the "Role" dropdown on user-edit.php replaces all roles. Therefore, for users with
`promote_user` (administrators), the "Orchestra" section of profile.php and user-edit.php has an
"Orchestra role" choice (none, Member, Conductor, Organizer), the Orchestra roles are left out of
the "Role" dropdown on user-edit.php (`editable_roles`), and the Orchestra role is saved on
`profile_update`, after WordPress has saved the dropdown (`Account\OrchestraRole`). The role
choice on **Users → Add New** and "Change role to…" in the user list are unchanged.

"Add member" (conductors and organizers) refuses email addresses that already have an account:
they cannot see or change accounts that are not Orchestra accounts, and an administrator gives
such an account its Orchestra role.

The member list is for conductors and organizers. Members do not see it (since 0.10, see the
capabilities below): they see who takes part in a project on the project's page in
orchestra-projects, and their own profile under "My profile".

On phones the list is compact (orchestra-core 0.15): the name, then the email address (cut with
"…" if it is too long, the full address as its title), then role and status.

The list has a search (`orchestra_members_q`) in names, and for organizers also in email
addresses, and organizers filter it by status (`orchestra_members_status`: active, inactive) and
role (`orchestra_members_role`), see `MemberFilter`.

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
| `Admin\ProfileSection` | The "Orchestra" section on profile.php and user-edit.php, with the Orchestra role for administrators |
| `Account\OrchestraRole` | Sets the Orchestra role of a user and keeps their other roles |
| `Admin\MembersPage` | The **Members** screen and the status form |
| `Admin\UserListColumn` | The status column in **Users → All Users** |
| `Privacy\PersonalData` | Exporter, eraser and privacy policy text |
| `Email\MemberEmailGroups` | The global email groups and the exclusion of inactive members |

## Capabilities

See the catalogue in orchestra-core's `docs/capabilities.md`.

| Capability | Member | Conductor | Organizer |
|-----------|:------:|:---------:|:---------:|
| `orchestra_members_directory_read` | | ✓ | ✓ |
| `orchestra_members_profile_read` *meta* | own | own | own + others |
| `orchestra_members_profile_edit` *meta* | own | own | own |
| `orchestra_members_status_edit` | | | ✓ |
| `orchestra_members_member_create` | | ✓ | ✓ |
| `orchestra_members_email_send` | | ✓ | ✓ |
| `orchestra_members_email_member` | | | ✓ |
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

With orchestra-email 0.5 there is also an unlisted group per member (`orchestra_members_member_{id}`,
under "Members"), which `orchestra_members_email_member` (organizers) may email. It is not offered
when writing an email; "Write an email" on an active member's profile opens the email page with it
preselected.

| Group ID | Recipients |
|----------|------------|
| `orchestra_members_everyone` | everyone with an orchestra role |
| `orchestra_members_members` | role Orchestra Member |
| `orchestra_members_conductors` | role Orchestra Conductor |
| `orchestra_members_organizers` | role Orchestra Organizer |

It also removes inactive members from **every** group, including those of other plugins, through
`orchestra_email_recipients`.
