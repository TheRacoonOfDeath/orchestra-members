# API for other plugins

```php
$members = \Orchestra\Members\Plugin::instance()->members();
```

Check that the plugin is active first, e.g. with `class_exists(\Orchestra\Members\Plugin::class)`
on `plugins_loaded` or later, and declare it in your plugin header:
`Requires Plugins: orchestra-core, orchestra-members`.

Do not read or write the user meta of orchestra-members directly.

## `MembersService`

| Method | Returns |
|--------|---------|
| `isActive(int $userId): bool` | Whether the user is active. Users without a profile are active. |
| `member(int $userId): ?Member` | The member, or null if the user does not exist or has no orchestra role |
| `members(array $roles = [], bool $includeInactive = false): list<Member>` | Members ordered by display name, optionally only with the given roles |
| `activeMemberIds(array $roles = []): list<int>` | IDs of the active members, optionally only with the given roles |
| `profile(int $userId): ?Profile` | The profile including the sensitive data |
| `profileFields(): list<ProfileField>` | The profile fields chosen by the administrators, in display order (since 0.9) |

`Member` has `id`, `displayName`, `email`, `roles` (orchestra roles only), `active`, and since 0.9
`firstName` and `lastName` (`""` if not set).
`Profile` has `userId`, `active` and `values` (field ID => value for every current profile field;
`""` if empty), and `value(string $fieldId): string`. The fields are chosen by the administrators,
so do not rely on a particular field to exist. Checkbox values are `"1"` or `""`.
`ProfileField` has `id`, `label` and `type`; `display(string $value)` returns the value as plain
text, e.g. "Yes" or "No" for checkboxes.

Profiles contain sensitive data. Check the meta capability before showing one:

```php
if (current_user_can(\Orchestra\Members\Capabilities::PROFILE_READ, $userId)) { … }
```

## Actions

| Action | Arguments | Fired |
|--------|-----------|-------|
| `orchestra_members_member_activated` | `int $userId` | When a member is reactivated, and when an account is created |
| `orchestra_members_member_deactivated` | `int $userId` | After a member was set inactive |

`orchestra_members_member_activated` also fires for new accounts, because they are active. It
fires for every new user; check the role yourself. A user whose role changes later, e.g. from
subscriber to member, does not trigger it; use `set_user_role` for that.

Example: orchestra-projects creates participation records for members who become available while a
project is open:

```php
add_action('orchestra_members_member_activated', function (int $userId): void { … });
```
