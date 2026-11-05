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

    /** Present (value "1") only while the member does not want automatic emails. Not sensitive. */
    public const META_NO_AUTOMATIC_EMAILS = 'orchestra_members_no_automatic_emails';

    public function __construct(private readonly ProfileFields $fields)
    {
    }

    public function find(int $userId): ?Profile
    {
        if ($userId <= 0 || \get_userdata($userId) === false) {
            return null;
        }

        // The profile of inactive members was deleted; a default would pretend an answer.
        $active = $this->isActive($userId);
        $values = [];
        foreach ($this->fields->all() as $field) {
            $stored = $this->meta($userId, $field->metaKey());
            $values[$field->id] = $active || $stored !== '' ? $field->fromStored($stored) : '';
        }

        return new Profile($userId, $active, $values);
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
            $value = $field->stored($value);
            if ($value !== '') {
                \update_user_meta($userId, $field->metaKey(), $value);
            } else {
                \delete_user_meta($userId, $field->metaKey());
            }
        }
    }

    /**
     * Whether anything of the profile is stored, also an unticked checkbox. Defaults do not count.
     */
    public function hasSensitiveData(int $userId): bool
    {
        foreach ($this->fields->all() as $field) {
            if ($this->meta($userId, $field->metaKey()) !== '') {
                return true;
            }
        }

        return false;
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

    public function receivesAutomaticEmails(int $userId): bool
    {
        return $this->meta($userId, self::META_NO_AUTOMATIC_EMAILS) !== '1';
    }

    public function setReceivesAutomaticEmails(int $userId, bool $receives): void
    {
        if ($receives) {
            \delete_user_meta($userId, self::META_NO_AUTOMATIC_EMAILS);
        } else {
            \update_user_meta($userId, self::META_NO_AUTOMATIC_EMAILS, '1');
        }
    }

    /**
     * IDs of the users who turned automatic emails off.
     *
     * @return list<int>
     */
    public function noAutomaticEmailsUserIds(): array
    {
        $ids = \get_users([
            'meta_key' => self::META_NO_AUTOMATIC_EMAILS,
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
