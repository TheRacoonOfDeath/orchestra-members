<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * The orchestra-specific data of a WordPress user. Name and email address belong to WordPress.
 */
final class Profile
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $active,
        /** Sensitive. */
        public readonly bool $financialSupport,
        /** Sensitive. */
        public readonly string $dietaryRestrictions,
    ) {
    }
}
