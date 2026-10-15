<?php

/**
 * Minimal fakes of the WordPress functions and classes used by the unit-tested classes.
 */

declare(strict_types=1);

use Orchestra\Members\Tests\Fakes\WordPress;

class WP_User
{
    public int $ID = 0;
    /** @var list<string> */
    public array $roles = [];
    public string $display_name = '';
    public string $user_email = '';
}

class WP_Error
{
    /** @var array<string, list<string>> */
    public array $errors = [];

    public function __construct(string $code = '', string $message = '')
    {
        if ($code !== '') {
            $this->add($code, $message);
        }
    }

    public function add(string $code, string $message): void
    {
        $this->errors[$code][] = $message;
    }

    public function has_errors(): bool
    {
        return $this->errors !== [];
    }

    public function get_error_code(): string
    {
        return (string) array_key_first($this->errors);
    }
}

class WP_Session_Tokens
{
    private function __construct(private readonly int $userId)
    {
    }

    public static function get_instance(int $userId): self
    {
        return new self($userId);
    }

    public function destroy_all(): void
    {
        WordPress::$destroyedSessions[] = $this->userId;
    }
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function do_action(string $hookName, mixed ...$args): void
{
    WordPress::$actions[] = [$hookName, array_values($args)];
}

function get_userdata(int $userId): WP_User|false
{
    return WordPress::$users[$userId] ?? false;
}

function get_user_by(string $field, string $value): WP_User|false
{
    foreach (WordPress::$users as $user) {
        if ($field === 'email' && $user->user_email === $value) {
            return $user;
        }
    }

    return false;
}

function get_user_meta(int $userId, string $key, bool $single = false): mixed
{
    return WordPress::$userMeta[$userId][$key] ?? '';
}

function update_user_meta(int $userId, string $key, string $value): bool
{
    WordPress::$userMeta[$userId][$key] = $value;

    return true;
}

function delete_user_meta(int $userId, string $key): bool
{
    unset(WordPress::$userMeta[$userId][$key]);

    return true;
}

/**
 * Supports the arguments used by orchestra-members: `role__in`, `meta_key`/`meta_value` and
 * `fields => 'ID'`. Users are ordered by display name.
 *
 * @param array<string, mixed> $args
 * @return list<mixed>
 */
function get_users(array $args): array
{
    $users = array_values(array_filter(WordPress::$users, static function (WP_User $user) use ($args): bool {
        if (isset($args['role__in']) && is_array($args['role__in']) && !array_intersect($args['role__in'], $user->roles)) {
            return false;
        }
        if (isset($args['meta_key']) && is_string($args['meta_key'])) {
            return (WordPress::$userMeta[$user->ID][$args['meta_key']] ?? null) === ($args['meta_value'] ?? null);
        }

        return true;
    }));
    usort($users, static fn (WP_User $a, WP_User $b): int => strcmp($a->display_name, $b->display_name));

    return ($args['fields'] ?? null) === 'ID'
        ? array_map(static fn (WP_User $user): string => (string) $user->ID, $users)
        : $users;
}

function user_can(int $userId, string $capability, mixed ...$args): bool
{
    return in_array($capability, WordPress::$userCapabilities[$userId] ?? [], true);
}
