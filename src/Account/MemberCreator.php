<?php

declare(strict_types=1);

namespace Orchestra\Members\Account;

use Orchestra\Core\Ui\Pages;
use Orchestra\Members\Ui\ProfileShortcode;

/**
 * Creates WordPress users for the orchestra. They set their password with the link in the
 * WordPress welcome email, which also tells them who added them, what is stored and where to see
 * it (Art. 14 GDPR: the data was not collected from them).
 */
final class MemberCreator
{
    /**
     * @return int|list<string> The new user's ID, or error messages
     */
    public function create(NewMemberInput $input): int|array
    {
        $errors = $input->validate(\email_exists($input->email) !== false);
        if ($errors !== []) {
            return $errors;
        }

        $userId = \wp_insert_user([
            'user_login' => $input->username(static fn (string $name): bool => \username_exists($name) !== false),
            'user_email' => $input->email,
            'user_pass' => \wp_generate_password(32),
            'first_name' => $input->firstName,
            'last_name' => $input->lastName,
            'display_name' => $input->displayName(),
            'nickname' => $input->displayName(),
            'role' => $input->role,
        ]);
        if (\is_wp_error($userId)) {
            return [$userId->get_error_message()];
        }

        $notice = self::welcomeNotice(
            \wp_get_current_user()->display_name,
            Pages::url(ProfileShortcode::TAG) ?? '',
            \get_privacy_policy_url()
        );
        $addNotice = static function (mixed $email) use ($notice): mixed {
            if (\is_array($email) && \is_string($email['message'] ?? null)) {
                $email['message'] .= "\r\n\r\n" . $notice . "\r\n";
            }

            return $email;
        };
        \add_filter('wp_new_user_notification_email', $addNotice);
        \wp_new_user_notification($userId, null, 'user');
        \remove_filter('wp_new_user_notification_email', $addNotice);

        return $userId;
    }

    /**
     * The paragraph added to the welcome email, as plain text.
     *
     * @param string $profileUrl '' if there is no profile page
     * @param string $privacyUrl '' if the site has no privacy policy
     */
    public static function welcomeNotice(string $creator, string $profileUrl, string $privacyUrl): string
    {
        $lines = [
            \sprintf(
                /* translators: %s: name of the conductor or organizer who added the member */
                \__(
                    '%s has added you to the orchestra pages. Your name and email address are stored for this;'
                        . ' everything else only once you enter it yourself.',
                    'orchestra-members'
                ),
                $creator
            ),
        ];
        if ($profileUrl !== '') {
            $lines[] = \sprintf(
                /* translators: %s: URL of the profile page */
                \__('You can see and change your details in your profile: %s', 'orchestra-members'),
                $profileUrl
            );
        }
        if ($privacyUrl !== '') {
            /* translators: %s: URL of the privacy policy */
            $lines[] = \sprintf(\__('Privacy policy: %s', 'orchestra-members'), $privacyUrl);
        }

        return \implode("\r\n", $lines);
    }
}
