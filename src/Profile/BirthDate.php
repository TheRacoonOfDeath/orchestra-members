<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

use DateTimeImmutable;

/**
 * The birth date of the profile, which every member has next to the {@see ProfileField}s chosen by
 * the administrators. Stored as `Y-m-d`.
 *
 * Sensitive like the profile fields: only the member, organizers and administrators can see it,
 * and it is deleted when the member is set inactive.
 */
final class BirthDate
{
    /** The user meta key. Also the name of the form field. */
    public const META_KEY = 'orchestra_members_birth_date';

    public const FIELD = self::META_KEY;

    /** The earliest birth date accepted. */
    public const MIN = '1900-01-01';

    /**
     * The value for submitted form data: the trimmed text, or "" if nothing was entered.
     */
    public static function normalize(mixed $submitted): string
    {
        return \is_string($submitted) ? \trim($submitted) : '';
    }

    /**
     * Why the value cannot be saved, or null if it can. Empty is allowed.
     *
     * @param string $today Y-m-d in the site's time zone
     */
    public static function error(string $value, string $today): ?string
    {
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value || $value < self::MIN || $value > $today) {
            return \__('Please enter a valid birth date.', 'orchestra-members');
        }

        return null;
    }

    /**
     * The value in the site's date format, or "" if there is none.
     */
    public static function display(string $value): string
    {
        $date = $value === '' ? false : DateTimeImmutable::createFromFormat('!Y-m-d', $value, \wp_timezone());
        if ($date === false) {
            return $value;
        }
        $format = \get_option('date_format');

        return (string) \wp_date(\is_string($format) && $format !== '' ? $format : 'Y-m-d', $date->getTimestamp());
    }
}
