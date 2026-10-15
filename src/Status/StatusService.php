<?php

declare(strict_types=1);

namespace Orchestra\Members\Status;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\MembersService;
use Orchestra\Members\Profile\ProfileRepository;
use WP_Session_Tokens;
use WP_User;

/**
 * Deactivates and reactivates members.
 */
final class StatusService
{
    public function __construct(private readonly ProfileRepository $profiles)
    {
    }

    /**
     * Why the actor may not change the target's status, or null if they may. The capability
     * {@see \Orchestra\Members\Capabilities::STATUS_EDIT} is checked separately.
     */
    public function refusal(int $actorId, int $targetId): ?string
    {
        $target = $targetId > 0 ? \get_userdata($targetId) : false;
        if (!$target instanceof WP_User || MemberDirectory::orchestraRoles($target) === []) {
            return \__('Only users with an orchestra role can be set inactive.', 'orchestra-members');
        }
        if (\in_array(Roles::ADMINISTRATOR, $target->roles, true)) {
            return \__('Administrators cannot be set inactive.', 'orchestra-members');
        }
        if ($actorId === $targetId) {
            return \__('You cannot change your own status.', 'orchestra-members');
        }

        return null;
    }

    /**
     * Sets the member inactive, deletes their sensitive profile data and logs them out everywhere.
     */
    public function deactivate(int $userId): void
    {
        if (!$this->profiles->isActive($userId)) {
            return;
        }

        $this->profiles->setActive($userId, false);
        $this->profiles->deleteSensitiveData($userId);
        WP_Session_Tokens::get_instance($userId)->destroy_all();

        \do_action(MembersService::ACTION_DEACTIVATED, $userId);
    }

    public function activate(int $userId): void
    {
        if ($this->profiles->isActive($userId)) {
            return;
        }

        $this->profiles->setActive($userId, true);

        \do_action(MembersService::ACTION_ACTIVATED, $userId);
    }
}
