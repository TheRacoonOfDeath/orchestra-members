<?php

declare(strict_types=1);

namespace Orchestra\Members\Account;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Profile\ProfileRepository;
use WP_User;

/**
 * Deletes the profile values of users who no longer have an Orchestra role, like setting a member
 * inactive does (data minimisation): without a role they cannot see or change them any more.
 *
 * Changing the role removes the old one before adding the new one, so the check waits until the
 * end of the request.
 */
final class RoleRemovalCleanup
{
    /** @var array<int, true> */
    private array $changedUsers = [];

    public function __construct(private readonly ProfileRepository $profiles)
    {
    }

    public function register(): void
    {
        \add_action('remove_user_role', [$this, 'noteRemovedRole'], 10, 2);
        \add_action('set_user_role', [$this, 'noteReplacedRoles'], 10, 3);
        \add_action('shutdown', [$this, 'deleteOrphanedProfiles']);
    }

    /**
     * @internal Hooked to `remove_user_role`.
     */
    public function noteRemovedRole(int $userId, string $role): void
    {
        if (\in_array($role, Roles::GRANTABLE, true)) {
            $this->changedUsers[$userId] = true;
        }
    }

    /**
     * @internal Hooked to `set_user_role`, which replaces all roles.
     * @param array<mixed> $oldRoles
     */
    public function noteReplacedRoles(int $userId, string $role, array $oldRoles = []): void
    {
        if (\array_intersect(\array_filter($oldRoles, \is_string(...)), Roles::GRANTABLE) !== []) {
            $this->changedUsers[$userId] = true;
        }
    }

    /**
     * @internal Hooked to `shutdown`.
     */
    public function deleteOrphanedProfiles(): void
    {
        foreach (\array_keys($this->changedUsers) as $userId) {
            $user = \get_userdata($userId);
            if ($user instanceof WP_User && !self::hasOrchestraRole(\array_values($user->roles))) {
                $this->profiles->deleteSensitiveData($userId);
            }
        }
        $this->changedUsers = [];
    }

    /**
     * @param list<string> $roles
     */
    public static function hasOrchestraRole(array $roles): bool
    {
        return \array_intersect($roles, Roles::GRANTABLE) !== [];
    }
}
