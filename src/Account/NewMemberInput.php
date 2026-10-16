<?php

declare(strict_types=1);

namespace Orchestra\Members\Account;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Profile\AccountInput;

/**
 * The form for adding a WordPress user to the orchestra: name, email address and orchestra role.
 */
final class NewMemberInput
{
    public const FIELD_FIRST_NAME = 'first_name';
    public const FIELD_LAST_NAME = 'last_name';
    public const FIELD_EMAIL = 'email';
    public const FIELD_ROLE = 'role';

    private function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $role,
    ) {
    }

    /**
     * @param array<mixed> $post Unslashed form data
     * @param bool $canChooseRole Whether the role is taken from the form; otherwise it is Member
     */
    public static function fromForm(array $post, bool $canChooseRole): self
    {
        $string = static fn (string $key): string => \is_string($post[$key] ?? null) ? $post[$key] : '';
        $name = static fn (string $key): string => \trim(
            (string) \preg_replace('/\s+/u', ' ', \strip_tags($string($key)))
        );
        $role = $canChooseRole ? $string(self::FIELD_ROLE) : Roles::MEMBER;

        return new self(
            $name(self::FIELD_FIRST_NAME),
            $name(self::FIELD_LAST_NAME),
            \trim($string(self::FIELD_EMAIL)),
            \in_array($role, Roles::GRANTABLE, true) ? $role : '',
        );
    }

    public function displayName(): string
    {
        return \trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * @param bool $emailTaken Whether an account uses the email address already
     * @return list<string> error messages
     */
    public function validate(bool $emailTaken): array
    {
        $errors = [];
        if ($this->firstName === '') {
            $errors[] = \__('Please enter a first name.', 'orchestra-members');
        }
        if (\mb_strlen($this->displayName()) > AccountInput::DISPLAY_NAME_MAX_LENGTH) {
            $errors[] = \__('The name is too long.', 'orchestra-members');
        }
        if (\is_email($this->email) === false) {
            $errors[] = \__('Please enter a valid email address.', 'orchestra-members');
        } elseif ($emailTaken) {
            $errors[] = \__('This email address is already used by another account.', 'orchestra-members');
        }
        if ($this->role === '') {
            $errors[] = \__('Please choose a role.', 'orchestra-members');
        }

        return $errors;
    }

    /**
     * A free username derived from the email address, e.g. "mia.muster" or "mia.muster2".
     *
     * @param callable(string): bool $taken
     */
    public function username(callable $taken): string
    {
        $local = \strtolower(\remove_accents(\strstr($this->email, '@', true) ?: $this->email));
        $base = \substr((string) \preg_replace('/[^a-z0-9._-]/', '', $local), 0, 50);
        $base = $base === '' ? 'member' : $base;

        $username = $base;
        for ($suffix = 2; $taken($username); $suffix++) {
            $username = $base . $suffix;
        }

        return $username;
    }
}
