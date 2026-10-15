<?php

declare(strict_types=1);

namespace Orchestra\Members\Status;

use Orchestra\Members\Profile\ProfileRepository;
use WP_Error;
use WP_User;

/**
 * Prevents inactive members from logging in, with a password or an application password.
 *
 * The check runs after WordPress has verified the password, so the account status is only
 * revealed to someone who knows it. Existing sessions are ended on deactivation.
 */
final class LoginBlocker
{
    public const ERROR_CODE = 'orchestra_members_inactive';

    public function __construct(private readonly ProfileRepository $profiles)
    {
    }

    public function register(): void
    {
        // After the password checks (20) and the spam check (99).
        \add_filter('authenticate', [$this, 'blockInactive'], 100);
        \add_action('wp_authenticate_application_password_errors', [$this, 'blockInactiveApplicationPassword'], 10, 2);
    }

    /**
     * @internal Hooked to `authenticate`.
     */
    public function blockInactive(mixed $user): mixed
    {
        if ($user instanceof WP_User && !$this->profiles->isActive($user->ID)) {
            return new WP_Error(self::ERROR_CODE, self::message());
        }

        return $user;
    }

    /**
     * @internal Hooked to `wp_authenticate_application_password_errors`.
     */
    public function blockInactiveApplicationPassword(WP_Error $error, WP_User $user): void
    {
        if (!$this->profiles->isActive($user->ID)) {
            $error->add(self::ERROR_CODE, self::message());
        }
    }

    public static function message(): string
    {
        return \__(
            'Your account has been marked as inactive. Please contact an administrator to reactivate it.',
            'orchestra-members'
        );
    }
}
