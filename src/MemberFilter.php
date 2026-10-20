<?php

declare(strict_types=1);

namespace Orchestra\Members;

/**
 * Narrows the member list: a search in names (and email addresses, for those who see them), and
 * filters by status and role.
 */
final class MemberFilter
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';

    /**
     * @param string $status '', {@see ACTIVE} or {@see INACTIVE}
     * @param string $role '' or an orchestra role
     */
    public function __construct(
        public readonly string $search = '',
        public readonly string $status = '',
        public readonly string $role = '',
        private readonly bool $searchEmail = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->search === '' && $this->status === '' && $this->role === '';
    }

    /**
     * @param list<Member> $members
     * @return list<Member>
     */
    public function apply(array $members): array
    {
        $search = \mb_strtolower(\trim($this->search));

        return \array_values(\array_filter($members, function (Member $member) use ($search): bool {
            $haystack = $member->displayName . ' ' . $member->firstName . ' ' . $member->lastName
                . ($this->searchEmail ? ' ' . $member->email : '');

            return ($search === '' || \str_contains(\mb_strtolower($haystack), $search))
                && ($this->status === '' || $member->active === ($this->status === self::ACTIVE))
                && ($this->role === '' || \in_array($this->role, $member->roles, true));
        }));
    }
}
