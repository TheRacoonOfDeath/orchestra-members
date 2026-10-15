<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Members\Profile\AccountInput;
use PHPUnit\Framework\TestCase;

final class AccountInputTest extends TestCase
{
    private const EMAIL = 'mia@example.com';

    public function testChangingTheNameNeedsNoPassword(): void
    {
        $input = self::input(['display_name' => "  Mia\n <b>Muster</b> "]);

        $this->assertSame('Mia Muster', $input->displayName);
        $this->assertSame([], $input->validate(self::EMAIL, false, false));
    }

    public function testChangingEmailOrPasswordNeedsTheCurrentPassword(): void
    {
        $email = self::input(['email' => 'mia@example.org']);
        $password = self::input(['new_password' => 'a-long-secret', 'new_password_repeat' => 'a-long-secret']);

        $this->assertCount(1, $email->validate(self::EMAIL, false, false));
        $this->assertSame([], $email->validate(self::EMAIL, false, true));
        $this->assertCount(1, $password->validate(self::EMAIL, false, false));
        $this->assertSame([], $password->validate(self::EMAIL, false, true));
    }

    public function testTheEmailAddressMustBeValidAndFree(): void
    {
        $this->assertCount(1, self::input(['email' => 'not an address'])->validate(self::EMAIL, false, true));
        $this->assertCount(1, self::input(['email' => 'max@example.com'])->validate(self::EMAIL, true, true));
        $this->assertFalse(self::input(['email' => 'MIA@example.com'])->changesEmail(self::EMAIL));
    }

    public function testTheNewPasswordMustBeLongEnoughAndRepeated(): void
    {
        $short = self::input(['new_password' => 'short', 'new_password_repeat' => 'short']);
        $mismatch = self::input(['new_password' => 'a-long-secret', 'new_password_repeat' => 'another-secret']);

        $this->assertCount(1, $short->validate(self::EMAIL, false, true));
        $this->assertCount(1, $mismatch->validate(self::EMAIL, false, true));
    }

    public function testTheNameIsRequired(): void
    {
        $this->assertCount(1, self::input(['display_name' => ' '])->validate(self::EMAIL, false, false));
    }

    /**
     * @param array<string, string> $fields
     */
    private static function input(array $fields): AccountInput
    {
        return AccountInput::fromForm($fields + ['display_name' => 'Mia', 'email' => self::EMAIL]);
    }
}
