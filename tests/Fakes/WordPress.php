<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Fakes;

use WP_User;

/**
 * In-memory state behind the fake WordPress functions in functions.php.
 */
final class WordPress
{
    /** @var array<int, WP_User> */
    public static array $users = [];

    /** @var array<int, array<string, string>> user ID => meta key => value */
    public static array $userMeta = [];

    /** @var list<array{string, list<mixed>}> */
    public static array $actions = [];

    /** @var list<int> */
    public static array $destroyedSessions = [];

    /** @var array<int, list<string>> user ID => capabilities */
    public static array $userCapabilities = [];

    public static function reset(): void
    {
        self::$users = [];
        self::$userMeta = [];
        self::$actions = [];
        self::$destroyedSessions = [];
        self::$userCapabilities = [];
    }

    /**
     * @param list<string> $roles
     */
    public static function addUser(int $id, array $roles, string $name = 'User'): WP_User
    {
        $user = new WP_User();
        $user->ID = $id;
        $user->roles = $roles;
        $user->display_name = $name;
        $user->user_email = "user{$id}@example.com";
        self::$users[$id] = $user;

        return $user;
    }
}
