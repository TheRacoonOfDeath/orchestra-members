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

    /** Present (value "1") only if the member needs financial support. Sensitive. */
    public const META_FINANCIAL_SUPPORT = 'orchestra_members_financial_support';

    /** Sensitive. */
    public const META_DIETARY_RESTRICTIONS = 'orchestra_members_dietary_restrictions';

    public const META_KEYS = [self::META_INACTIVE, self::META_FINANCIAL_SUPPORT, self::META_DIETARY_RESTRICTIONS];

    public function find(int $userId): ?Profile
    {
        if ($userId <= 0 || \get_userdata($userId) === false) {
            return null;
        }

        return new Profile(
            $userId,
            $this->isActive($userId),
            $this->meta($userId, self::META_FINANCIAL_SUPPORT) === '1',
            $this->meta($userId, self::META_DIETARY_RESTRICTIONS),
        );
    }

    public function isActive(int $userId): bool
    {
        return $this->meta($userId, self::META_INACTIVE) !== '1';
    }

    public function saveSensitiveData(int $userId, bool $financialSupport, string $dietaryRestrictions): void
    {
        if ($financialSupport) {
            \update_user_meta($userId, self::META_FINANCIAL_SUPPORT, '1');
        } else {
            \delete_user_meta($userId, self::META_FINANCIAL_SUPPORT);
        }

        if ($dietaryRestrictions !== '') {
            \update_user_meta($userId, self::META_DIETARY_RESTRICTIONS, $dietaryRestrictions);
        } else {
            \delete_user_meta($userId, self::META_DIETARY_RESTRICTIONS);
        }
    }

    public function deleteSensitiveData(int $userId): void
    {
        \delete_user_meta($userId, self::META_FINANCIAL_SUPPORT);
        \delete_user_meta($userId, self::META_DIETARY_RESTRICTIONS);
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
