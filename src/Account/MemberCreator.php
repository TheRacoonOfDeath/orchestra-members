<?php

declare(strict_types=1);

namespace Orchestra\Members\Account;

/**
 * Creates WordPress users for the orchestra. They set their password with the link in the
 * WordPress welcome email.
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

        \wp_new_user_notification($userId, null, 'user');

        return $userId;
    }
}
