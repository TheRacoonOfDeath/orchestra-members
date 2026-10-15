# orchestra-members

Member profiles and active status for the Orchestra plugin suite.

Part of the Orchestra plugin suite. Requires the [orchestra-core](https://github.com/TheRacoonOfDeath/orchestra-core) plugin.

## Features

- Profile fields on the WordPress profile screen: financial support and dietary restrictions
- A **Members** screen in wp-admin: names of all members for everyone, emails, roles, status and
  profiles for organizers
- Active / inactive status, changed by organizers
  - inactive members cannot log in, also not with application passwords; after entering the
    correct password they are told that their account is inactive
  - setting a member inactive ends their sessions and deletes their sensitive profile data
- Export and erasure of the profile data with the WordPress privacy tools, and a suggested
  privacy policy text
- An API for the other Orchestra plugins

Accounts are created by administrators (**Users → Add New**, role "Orchestra Member", "Orchestra
Conductor" or "Orchestra Organizer"). There is no self-registration.

## Installation

1. Install and activate orchestra-core.
2. Upload `orchestra-members-{VERSION}.zip` under **Plugins → Add New → Upload Plugin** and activate it.

Updates are installed from the GitHub releases of this repository.

## Documentation

- [Architecture](docs/architecture.md)
- [API for other plugins](docs/api.md)
- [Privacy and security](docs/privacy.md)

The domain model, use cases and capabilities of the whole suite are documented in orchestra-core.

## Development

```bash
composer install
composer check   # coding standard, static analysis and unit tests
composer e2e     # end-to-end tests in a temporary WordPress (see tests/e2e/run.sh)
```

The end-to-end tests install WordPress with SQLite, the latest orchestra-core release and a fresh
build of this plugin, run them on PHP's built-in web server and test them over HTTP as each role.
They need PHP with `pdo_sqlite`, Python 3, curl, unzip and network access.

## Releasing

1. Run `composer check` and `composer e2e`.
2. Bump `Version:` in `orchestra-members.php`.
3. Run `./build-plugin.sh`; it creates `build/orchestra-members-{VERSION}.zip` with production
   dependencies only.
4. Create a GitHub release for the version and attach the zip.
