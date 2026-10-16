<?php

declare(strict_types=1);

namespace Orchestra\Members;

use Orchestra\Core\Authorization\CapabilityRegistry;
use Orchestra\Core\Authorization\OwnershipMapper;
use Orchestra\Core\Authorization\Roles;

/**
 * The capabilities owned by orchestra-members. See orchestra-core's docs/capabilities.md.
 */
final class Capabilities
{
    /** See the names of all members. */
    public const DIRECTORY_READ = 'orchestra_members_directory_read';

    /** Meta capability, checked with a user ID: read a member's profile. */
    public const PROFILE_READ = 'orchestra_members_profile_read';
    public const PROFILE_READ_OWN = 'orchestra_members_profile_read_own';
    public const PROFILE_READ_OTHERS = 'orchestra_members_profile_read_others';

    /** Meta capability, checked with a user ID: edit a member's profile. */
    public const PROFILE_EDIT = 'orchestra_members_profile_edit';
    public const PROFILE_EDIT_OWN = 'orchestra_members_profile_edit_own';
    public const PROFILE_EDIT_OTHERS = 'orchestra_members_profile_edit_others';

    /** Set members active or inactive. */
    public const STATUS_EDIT = 'orchestra_members_status_edit';

    /** Choose the fields of the orchestra profile. Administrators only. */
    public const FIELDS_MANAGE = 'orchestra_members_fields_manage';

    /** Email the global groups (everyone, all members, all conductors, all organizers). */
    public const EMAIL_SEND = 'orchestra_members_email_send';

    /**
     * @internal Hooked to `orchestra_core_register_capabilities`.
     */
    public static function register(CapabilityRegistry $registry): void
    {
        $everyone = [Roles::MEMBER, Roles::CONDUCTOR, Roles::ORGANIZER];

        $registry
            ->register(self::DIRECTORY_READ, $everyone)
            ->register(self::PROFILE_READ_OWN, $everyone)
            ->register(self::PROFILE_READ_OTHERS, [Roles::ORGANIZER])
            ->register(self::PROFILE_EDIT_OWN, $everyone)
            ->register(self::PROFILE_EDIT_OTHERS)
            ->register(self::STATUS_EDIT, [Roles::ORGANIZER])
            ->register(self::FIELDS_MANAGE)
            ->register(self::EMAIL_SEND, [Roles::CONDUCTOR, Roles::ORGANIZER])
            ->registerMetaCapability(
                self::PROFILE_READ,
                OwnershipMapper::forUsers(self::PROFILE_READ_OWN, self::PROFILE_READ_OTHERS)
            )
            ->registerMetaCapability(
                self::PROFILE_EDIT,
                OwnershipMapper::forUsers(self::PROFILE_EDIT_OWN, self::PROFILE_EDIT_OTHERS)
            );
    }
}
