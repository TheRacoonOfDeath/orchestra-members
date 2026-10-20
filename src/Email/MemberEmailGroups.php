<?php

declare(strict_types=1);

namespace Orchestra\Members\Email;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Email\EmailGroupRegistry;
use Orchestra\Email\EmailService;
use Orchestra\Email\Plugin as EmailPlugin;
use Orchestra\Email\RecipientGroup;
use Orchestra\Members\Capabilities;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\Profile\ProfileRepository;

/**
 * The global recipient groups for the email service of orchestra-core, and the rule that inactive
 * members never receive group emails.
 *
 * Only used with orchestra-core 0.3 or later, which provides the email service.
 */
final class MemberEmailGroups
{
    public const EVERYONE = 'orchestra_members_everyone';
    public const MEMBERS = 'orchestra_members_members';
    public const CONDUCTORS = 'orchestra_members_conductors';
    public const ORGANIZERS = 'orchestra_members_organizers';
    /** Followed by the user ID: a single member, see {@see memberGroupId()}. */
    private const MEMBER_PREFIX = 'orchestra_members_member_';

    public function __construct(
        private readonly MemberDirectory $directory,
        private readonly ProfileRepository $profiles,
    ) {
    }

    public function register(): void
    {
        \add_action(EmailService::REGISTER_GROUPS_ACTION, [$this, 'registerGroups']);
        \add_filter(EmailService::RECIPIENTS_FILTER, [$this, 'removeInactive']);
    }

    /**
     * @internal Hooked to `orchestra_email_register_groups`.
     */
    public function registerGroups(EmailGroupRegistry $registry): void
    {
        $category = \__('Orchestra', 'orchestra-members');
        $groups = [
            self::EVERYONE => [\__('Everyone', 'orchestra-members'), []],
            self::MEMBERS => [\__('All members', 'orchestra-members'), [Roles::MEMBER]],
            self::CONDUCTORS => [\__('All conductors', 'orchestra-members'), [Roles::CONDUCTOR]],
            self::ORGANIZERS => [\__('All organizers', 'orchestra-members'), [Roles::ORGANIZER]],
        ];

        foreach ($groups as $id => [$label, $roles]) {
            $registry->add(new RecipientGroup(
                $id,
                $label,
                $category,
                fn (): array => \array_map(static fn ($member): int => $member->id, $this->directory->members($roles)),
                Capabilities::EMAIL_SEND
            ));
        }

        // A group per member, offered only from the member's profile. Unlisted groups exist since
        // orchestra-email 0.5; with an older version they would be listed, so they are left out.
        if (!\property_exists(RecipientGroup::class, 'listed')) {
            return;
        }
        foreach ($this->directory->members([]) as $member) {
            $registry->add(new RecipientGroup(
                self::memberGroupId($member->id),
                $member->displayName,
                \__('Members', 'orchestra-members'),
                static fn (): array => [$member->id],
                Capabilities::EMAIL_MEMBER,
                [],
                false
            ));
        }
    }

    /**
     * The email group of a single member, for {@see EmailService::composeUrl()}.
     */
    public static function memberGroupId(int $userId): string
    {
        return self::MEMBER_PREFIX . $userId;
    }

    /**
     * Whether organizers can write to single members: orchestra-email 0.5 runs.
     */
    public static function singleMemberAvailable(): bool
    {
        return \class_exists(EmailPlugin::class) && EmailPlugin::running()
            && \property_exists(RecipientGroup::class, 'listed');
    }

    /**
     * Inactive members are never part of a group, whichever plugin defines it.
     *
     * @internal Hooked to `orchestra_email_recipients`.
     * @param array<mixed> $userIds
     * @return array<mixed>
     */
    public function removeInactive(array $userIds): array
    {
        $inactive = \array_flip($this->profiles->inactiveUserIds());

        return \array_values(\array_filter(
            $userIds,
            static fn (mixed $id): bool => !\is_int($id) || !isset($inactive[$id])
        ));
    }
}
