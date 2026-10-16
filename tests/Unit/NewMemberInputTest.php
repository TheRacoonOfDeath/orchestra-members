<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Account\NewMemberInput;
use PHPUnit\Framework\TestCase;

final class NewMemberInputTest extends TestCase
{
    public function testNewUsersAreMembersUnlessTheRoleMayBeChosen(): void
    {
        $post = ['first_name' => ' Mia ', 'last_name' => 'Muster', 'email' => 'mia@example.com',
            'role' => Roles::ORGANIZER];

        $member = NewMemberInput::fromForm($post, false);
        $organizer = NewMemberInput::fromForm($post, true);

        $this->assertSame(Roles::MEMBER, $member->role);
        $this->assertSame(Roles::ORGANIZER, $organizer->role);
        $this->assertSame('Mia Muster', $member->displayName());
        $this->assertSame([], $member->validate(false));
    }

    public function testRejectsMissingNamesBadEmailsTakenEmailsAndForeignRoles(): void
    {
        $input = NewMemberInput::fromForm(['first_name' => '', 'email' => 'no-email', 'role' => 'administrator'], true);

        $this->assertSame([
            'Please enter a first name.',
            'Please enter a valid email address.',
            'Please choose a role.',
        ], $input->validate(false));

        $taken = NewMemberInput::fromForm(['first_name' => 'Mia', 'email' => 'mia@example.com'], false);
        $this->assertSame(['This email address is already used by another account.'], $taken->validate(true));
    }

    public function testDerivesAFreeUsernameFromTheEmailAddress(): void
    {
        $input = NewMemberInput::fromForm(['first_name' => 'Mia', 'email' => 'Mia.Müller+x@example.com'], false);

        $this->assertSame('mia.mullerx', $input->username(static fn (string $name): bool => false));
        $this->assertSame(
            'mia.mullerx3',
            $input->username(static fn (string $name): bool => \in_array($name, ['mia.mullerx', 'mia.mullerx2'], true))
        );
    }
}
