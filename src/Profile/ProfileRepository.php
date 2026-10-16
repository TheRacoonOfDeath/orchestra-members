<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * Stores profiles as user meta.
 */
final class ProfileRepository
{
    /** Present (value "1") only while the member is inactive, so new users are active. */
    public const META_INACTIVE = 'orchestra_members_inactive';

    public function __construct(private readonly ProfileFields $fields)
    {
    }

    public function find(int $userId): ?Profile
    {
        if ($userId <= 0 || \get_userdata($userId) === false) {
            return null;
        }

        $values = [];
        foreach ($this->fields->all() as $field) {
            $values[$field->id] = $this->meta($userId, $field->metaKey());
        }

        return new Profile($userId, $this->isActive($userId), $values);
    }

    public function isActive(int $userId): bool
    {
        return $this->meta($userId, self::META_INACTIVE) !== '1';
    }

    /**
     * @param array<string, string> $values field ID => normalized value; fields that are missing
     *     are left unchanged
     */
    public function saveSensitiveData(int $userId, array $values): void
    {
        foreach ($this->fields->all() as $field) {
            $value = $values[$field->id] ?? null;
            if ($value === null) {
                continue;
            }
            if ($value !== '') {
                \update_user_meta($userId, $field->metaKey(), $value);
            } else {
                \delete_user_meta($userId, $field->metaKey());
            }
        }
    }

    public function deleteSensitiveData(int $userId): void
    {
        foreach ($this->fields->all() as $field) {
            \delete_user_meta($userId, $field->metaKey());
        }
    }

    /**
     * Deletes the values of all members for a field that is removed.
     */
    public function deleteFieldValues(ProfileField $field): void
    {
        \delete_metadata('user', 0, $field->metaKey(), '', true);
    }

    public function setActive(int $userId, bool $active): void
    {
        if ($active) {
            \delete_user_meta($userId, self::META_INACTIVE);
        } else {
            \update_user_meta($userId, self::META_INACTIVE, '1');
        }
    }

    /**
     * IDs of the users who are inactive.
     *
     * @return list<int>
     */
    public function inactiveUserIds(): array
    {
        $ids = \get_users([
            'meta_key' => self::META_INACTIVE,
            'meta_value' => '1',
            'fields' => 'ID',
        ]);

        return \array_values(\array_map('intval', $ids));
    }

    private function meta(int $userId, string $key): string
    {
        $value = \get_user_meta($userId, $key, true);

        return \is_string($value) ? $value : '';
    }
}
