<?php

declare(strict_types=1);

namespace Orchestra\Members\Account;

use Orchestra\Core\Authorization\Roles;
use WP_User;

/**
 * The Orchestra role of a user, kept next to their other roles, e.g. an administrator who plays.
 * A user has at most one Orchestra role.
 */
final class OrchestraRole
{
    /**
     * Whether a value is an Orchestra role, or '' for none.
     */
    public static function isChoice(string $role): bool
    {
        return $role === '' || \in_array($role, Roles::GRANTABLE, true);
    }

    /**
     * The roles to remove and to add so that `$chosen` ('' for none) is the only Orchestra role.
     * Other roles are kept.
     *
     * @param list<string> $roles
     * @return array{remove: list<string>, add: list<string>}
     */
    public static function changes(array $roles, string $chosen): array
    {
        $orchestraRoles = \array_values(\array_intersect($roles, Roles::GRANTABLE));

        return [
            'remove' => \array_values(\array_diff($orchestraRoles, [$chosen])),
            'add' => $chosen === '' || \in_array($chosen, $roles, true) ? [] : [$chosen],
        ];
    }

    public static function change(WP_User $user, string $chosen): void
    {
        $changes = self::changes(\array_values($user->roles), $chosen);
        foreach ($changes['remove'] as $role) {
            $user->remove_role($role);
        }
        foreach ($changes['add'] as $role) {
            $user->add_role($role);
        }
    }
}
