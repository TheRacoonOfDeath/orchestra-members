# Privacy and security

The data protection of the whole suite is described in the
[GDPR documentation of orchestra-core](https://github.com/TheRacoonOfDeath/orchestra-core/blob/main/docs/gdpr.md).
This page covers orchestra-members.

## Personal data

The profile fields are chosen by the administrators (by default financial support and dietary
restrictions). Their values are personal data under the GDPR and are treated as sensitive, since
fields like dietary restrictions can reveal health or religious information. Only add fields the
orchestra needs.

- Visible only to the member, organizers and administrators. Members have no member list; they
  see the names of others in projects. Conductors see the member list with names only; they can
  also see the email addresses of the groups they may email (orchestra-email). Organizers also see
  email addresses, roles and the status.
- Optional: the profile page says that entering them is voluntary and who sees them, and links to
  the privacy policy. Entering them is the consent; deleting them withdraws it.
- Editable by the member, and by administrators on user-edit.php.
- Deleted when a member is set inactive (data minimization). They must be entered again after a
  reactivation.
- Deleted when the user no longer has an Orchestra role (since 0.20, `RoleRemovalCleanup`): they
  could neither see nor change them any more. The check runs at the end of the request, so
  changing from one Orchestra role to another keeps them.
- Deleting a profile field deletes its values for all members.
- **Tools → Export Personal Data** exports the profile; **Tools → Erase Personal Data** deletes the
  values of all profile fields. The inactive status and the choice not to receive automatic emails
  are kept, so that an erased account stays blocked and receives none; the eraser reports them as
  retained.
- Organizers can download the profiles of a project's accepted participants as a CSV file
  (orchestra-projects). Such files leave the site; delete them when they are no longer needed.
- **Settings → Privacy** offers a suggested text for the privacy policy: who creates accounts, who
  sees what, the current profile fields as voluntary data with consent, why inactive members are
  kept, and the automatic-email opt-out.

## New accounts

Conductors and organizers add people, so the data does not come from the person (Art. 14 GDPR).
Since 0.20 the welcome email of WordPress gets a paragraph (`wp_new_user_notification_email`,
only for "Add member"): who added them, that only name and email address are stored until they
enter more, the link to their profile and the link to the privacy policy.

Keeping inactive members (name and email in the WordPress user, history in orchestra-projects)
needs a legal basis such as the orchestra's legitimate interest in its history. State it in the
privacy policy and delete or anonymize the account when a former member asks for erasure.

## Automatic emails

Whether a member receives automatic emails (`orchestra_members_no_automatic_emails`) is not
sensitive. It is part of the personal data export and kept when the member is set inactive or the
profile is erased.

The email group of a single member passes the member as `person` to orchestra-email (0.12 or
later), so its log keeps a reference instead of the member's name.

## Inactive members

- The `authenticate` filter (priority 100) runs after WordPress has checked the password. With a
  wrong password the normal error is shown, so nobody can find out the status of an account
  without knowing its password. With the correct password the member is told that the account is
  inactive and that they should contact the organizers.
- Application passwords (REST API) are blocked through `wp_authenticate_application_password_errors`.
- Deactivating a member ends all their sessions, so they are logged out immediately.
- Password resets still work, but the new password cannot be used to log in.
- The login blocker is active even if orchestra-core is not.
- **While orchestra-members is deactivated, inactive members can log in again.** The status is kept
  and applies again once the plugin is reactivated.

## Requests

- The status form posts to `admin-post.php` with a nonce per member and requires
  `orchestra_members_status_edit`.
- The profile fields have their own nonce in addition to WordPress's, and require the meta
  capability `orchestra_members_profile_edit` for the edited user.
- The profile fields form posts to `admin-post.php` with a nonce and requires
  `orchestra_members_fields_manage` (administrators only).
- Profiles on the Members screen are checked with `orchestra_members_profile_read` before any
  output; unauthorized requests get a 403.
