<?php

declare(strict_types=1);

namespace Orchestra\Members\Email;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Email\EmailGroupRegistry;
use Orchestra\Email\EmailService;
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
