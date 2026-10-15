<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * The WordPress account fields of the profile form: name, email address and password.
 *
 * Changing the email address or the password requires the current password.
 */
final class AccountInput
{
    public const FIELD_DISPLAY_NAME = 'display_name';
    public const FIELD_EMAIL = 'email';
    public const FIELD_CURRENT_PASSWORD = 'current_password';
    public const FIELD_NEW_PASSWORD = 'new_password';
    public const FIELD_NEW_PASSWORD_REPEAT = 'new_password_repeat';

    public const DISPLAY_NAME_MAX_LENGTH = 250;
    public const PASSWORD_MIN_LENGTH = 8;

    private function __construct(
        public readonly string $displayName,
        public readonly string $email,
        public readonly string $currentPassword,
        public readonly string $newPassword,
        public readonly string $newPasswordRepeat,
    ) {
    }

    /**
     * @param array<mixed> $post Unslashed form data
     */
    public static function fromForm(array $post): self
    {
        $string = static fn (string $key): string => \is_string($post[$key] ?? null) ? $post[$key] : '';

        return new self(
            \trim((string) \preg_replace('/\s+/u', ' ', \strip_tags($string(self::FIELD_DISPLAY_NAME)))),
            \trim($string(self::FIELD_EMAIL)),
            $string(self::FIELD_CURRENT_PASSWORD),
            $string(self::FIELD_NEW_PASSWORD),
            $string(self::FIELD_NEW_PASSWORD_REPEAT),
        );
    }

    public function changesEmail(string $currentEmail): bool
    {
        return \strcasecmp($this->email, $currentEmail) !== 0;
    }

    public function changesPassword(): bool
    {
        return $this->newPassword !== '';
    }

    /**
     * @param bool $emailTaken Whether another account uses the new email address
     * @param bool $currentPasswordValid Whether the current password was entered correctly
     * @return list<string> error messages
     */
    public function validate(string $currentEmail, bool $emailTaken, bool $currentPasswordValid): array
    {
        $errors = [];
        if ($this->displayName === '') {
            $errors[] = \__('Please enter your name.', 'orchestra-members');
        } elseif (\mb_strlen($this->displayName) > self::DISPLAY_NAME_MAX_LENGTH) {
            $errors[] = \__('The name is too long.', 'orchestra-members');
        }

        if ($this->changesEmail($currentEmail)) {
            if (\is_email($this->email) === false) {
                $errors[] = \__('Please enter a valid email address.', 'orchestra-members');
            } elseif ($emailTaken) {
                $errors[] = \__('This email address is already used by another account.', 'orchestra-members');
            }
        }

        if ($this->changesPassword()) {
            if (\mb_strlen($this->newPassword) < self::PASSWORD_MIN_LENGTH) {
                /* translators: %d: minimum number of characters */
                $message = \__('The new password must have at least %d characters.', 'orchestra-members');
                $errors[] = \sprintf($message, self::PASSWORD_MIN_LENGTH);
            } elseif ($this->newPassword !== $this->newPasswordRepeat) {
                $errors[] = \__('The new passwords do not match.', 'orchestra-members');
            }
        }

        if (($this->changesEmail($currentEmail) || $this->changesPassword()) && !$currentPasswordValid) {
            $errors[] = \__(
                'Please enter your current password to change your email address or password.',
                'orchestra-members'
            );
        }

        return $errors;
    }
}
