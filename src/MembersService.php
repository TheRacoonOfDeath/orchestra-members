<?php

declare(strict_types=1);

namespace Orchestra\Members;

use Orchestra\Members\Profile\ProfileField;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;

/**
 * The public, read-only API of orchestra-members for other plugins.
 *
 *     $members = \Orchestra\Members\Plugin::instance()->members();
 *
 * Profiles contain sensitive data: callers check {@see Capabilities::PROFILE_READ} before showing
 * them. Other plugins must not access the user meta of orchestra-members directly.
 */
final class MembersService
{
    /**
     * Fires with the user ID when a member is reactivated, and when an account is created (new
     * accounts are active). The user's role may still change later, e.g. with `set_user_role`.
     */
    public const ACTION_ACTIVATED = 'orchestra_members_member_activated';

    /** Fires with the user ID after a member was set inactive. */
    public const ACTION_DEACTIVATED = 'orchestra_members_member_deactivated';

    public function __construct(
        private readonly MemberDirectory $directory,
        private readonly ProfileRepository $profiles,
        private readonly ProfileFields $fields,
    ) {
    }

    public function isActive(int $userId): bool
    {
        return $this->profiles->isActive($userId);
    }

    /**
     * @return Member|null null if the user does not exist or has no orchestra role
     */
    public function member(int $userId): ?Member
    {
        return $this->directory->member($userId);
    }

    /**
     * @param list<string> $roles Only users with one of these roles; all orchestra roles if empty
     * @return list<Member> ordered by display name
     */
    public function members(array $roles = [], bool $includeInactive = false): array
    {
        return $this->directory->members($roles, $includeInactive);
    }

    /**
     * @param list<string> $roles Only users with one of these roles; all orchestra roles if empty
     * @return list<int>
     */
    public function activeMemberIds(array $roles = []): array
    {
        return \array_map(static fn (Member $member): int => $member->id, $this->directory->members($roles));
    }

    public function profile(int $userId): ?Profile\Profile
    {
        return $this->profiles->find($userId);
    }

    /**
     * The profile fields chosen by the administrators, e.g. for column headings. The values are
     * in {@see Profile\Profile::$values}, by field ID.
     *
     * @return list<ProfileField> in display order
     */
    public function profileFields(): array
    {
        return $this->fields->all();
    }

    /**
     * The checkbox field in which members ask for financial support, chosen by the administrators in
     * the profile fields; null if there is none. Since 0.13.
     */
    public function financialSupportField(): ?ProfileField
    {
        return $this->fields->financialSupportField();
    }
}
