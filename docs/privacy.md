# Privacy and security

## Personal data

The profile fields are chosen by the administrators (by default financial support and dietary
restrictions). Their values are personal data under the GDPR and are treated as sensitive, since
fields like dietary restrictions can reveal health or religious information. Only add fields the
orchestra needs.

- Visible only to the member, organizers and administrators. Other members see names only;
  conductors see names only.
- Editable by the member, and by administrators on user-edit.php.
- Deleted when a member is set inactive (data minimization). They must be entered again after a
  reactivation.
- Deleting a profile field deletes its values for all members.
- **Tools → Export Personal Data** exports the profile; **Tools → Erase Personal Data** deletes the
  values of all profile fields. The inactive status is kept, so an erased inactive
  account stays blocked; the eraser reports it as retained.
- **Settings → Privacy** offers a suggested text for the privacy policy, listing the current
  profile fields.

Keeping inactive members (name and email in the WordPress user, history in orchestra-projects)
needs a legal basis such as the orchestra's legitimate interest in its history. State it in the
privacy policy and delete or anonymize the account when a former member asks for erasure.

## Inactive members

- The `authenticate` filter (priority 100) runs after WordPress has checked the password. With a
  wrong password the normal error is shown, so nobody can find out the status of an account
  without knowing its password. With the correct password the member is told that the account is
  inactive and that they should contact an administrator.
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
