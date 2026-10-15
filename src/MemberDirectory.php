<?php

declare(strict_types=1);

namespace Orchestra\Members;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Profile\ProfileRepository;
use WP_User;

/**
 * Finds the users with an orchestra role.
 */
final class MemberDirectory
{
    public function __construct(private readonly ProfileRepository $profiles)
    {
    }

    /**
     * @param list<string> $roles Only users with one of these roles; all orchestra roles if empty
     * @return list<Member> ordered by display name
     */
    public function members(array $roles = [], bool $includeInactive = false): array
    {
        $users = \get_users([
            'role__in' => $roles === [] ? Roles::GRANTABLE : $roles,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        $inactive = \array_flip($this->profiles->inactiveUserIds());

        $members = [];
        foreach ($users as $user) {
            $active = !isset($inactive[$user->ID]);
            if ($active || $includeInactive) {
                $members[] = self::fromUser($user, $active);
            }
        }

        return $members;
    }

    public function member(int $userId): ?Member
    {
        $user = $userId > 0 ? \get_userdata($userId) : false;
        if (!$user instanceof WP_User || self::orchestraRoles($user) === []) {
            return null;
        }

        return self::fromUser($user, $this->profiles->isActive($userId));
    }

    /**
     * @return list<string>
     */
    public static function orchestraRoles(WP_User $user): array
    {
        return \array_values(\array_intersect(Roles::GRANTABLE, $user->roles));
    }

    private static function fromUser(WP_User $user, bool $active): Member
    {
        return new Member($user->ID, $user->display_name, $user->user_email, self::orchestraRoles($user), $active);
    }
}
