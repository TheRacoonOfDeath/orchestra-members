<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Members\Account\MemberCreator;
use PHPUnit\Framework\TestCase;

final class MemberCreatorTest extends TestCase
{
    public function testTheWelcomeEmailSaysWhoAddedTheMemberAndWhereToSeeTheirData(): void
    {
        $notice = MemberCreator::welcomeNotice(
            'Olga Organizer',
            'https://orchestra.example/profile/',
            'https://orchestra.example/privacy/'
        );

        $this->assertStringContainsString('Olga Organizer has added you', $notice);
        $this->assertStringContainsString('https://orchestra.example/profile/', $notice);
        $this->assertStringContainsString('https://orchestra.example/privacy/', $notice);
    }

    public function testMissingPagesAreLeftOut(): void
    {
        $notice = MemberCreator::welcomeNotice('Olga Organizer', '', '');

        $this->assertStringNotContainsString('profile:', $notice);
        $this->assertStringNotContainsString('Privacy policy', $notice);
    }
}
