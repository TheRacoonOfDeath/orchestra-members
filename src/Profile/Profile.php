<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * The orchestra-specific data of a WordPress user. Name and email address belong to WordPress.
 */
final class Profile
{
    /**
     * @param array<string, string> $values Sensitive. Field ID => value, for every current
     *     {@see ProfileField}; "" if the member has not entered anything
     */
    public function __construct(
        public readonly int $userId,
        public readonly bool $active,
        public readonly array $values,
    ) {
    }

    public function value(string $fieldId): string
    {
        return $this->values[$fieldId] ?? '';
    }

    public function hasValues(): bool
    {
        return \array_filter($this->values, static fn (string $value): bool => $value !== '') !== [];
    }
}
