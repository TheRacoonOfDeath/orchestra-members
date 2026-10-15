<?php

declare(strict_types=1);

namespace Orchestra\Members;

/**
 * A WordPress user with an orchestra role.
 */
final class Member
{
    /**
     * @param list<string> $roles The user's orchestra roles, see {@see \Orchestra\Core\Authorization\Roles}
     */
    public function __construct(
        public readonly int $id,
        public readonly string $displayName,
        public readonly string $email,
        public readonly array $roles,
        public readonly bool $active,
    ) {
    }
}
