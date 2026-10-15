<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Core\Email\EmailGroupRegistry;
use Orchestra\Members\Capabilities;
use Orchestra\Members\Email\MemberEmailGroups;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Tests\Fakes\WordPress;
use PHPUnit\Framework\TestCase;

final class MemberEmailGroupsTest extends TestCase
{
    private ProfileRepository $profiles;
    private MemberEmailGroups $groups;

    protected function setUp(): void
    {
        WordPress::reset();
        $this->profiles = new ProfileRepository();
        $this->groups = new MemberEmailGroups(new MemberDirectory($this->profiles), $this->profiles);

        WordPress::addUser(1, ['administrator'], 'Admin');
        WordPress::addUser(2, [Roles::MEMBER], 'Bea');
        WordPress::addUser(3, [Roles::ORGANIZER], 'Anna');
        WordPress::addUser(4, [Roles::CONDUCTOR], 'Carl');
        WordPress::addUser(5, [Roles::MEMBER], 'Dora');
        $this->profiles->setActive(5, false);
    }

    public function testRegistersTheGlobalGroups(): void
    {
        $registry = new EmailGroupRegistry();
        $this->groups->registerGroups($registry);

        $recipients = [];
        foreach ($registry->all() as $group) {
            $recipients[$group->id] = $group->recipientIds();
            $this->assertSame(Capabilities::EMAIL_SEND, $group->capability);
        }
        $this->assertSame([
            MemberEmailGroups::EVERYONE => [3, 2, 4],
            MemberEmailGroups::MEMBERS => [2],
            MemberEmailGroups::CONDUCTORS => [4],
            MemberEmailGroups::ORGANIZERS => [3],
        ], $recipients);
    }

    public function testOnlyConductorsAndOrganizersMayEmailThem(): void
    {
        $registry = new EmailGroupRegistry();
        $this->groups->registerGroups($registry);
        WordPress::$userCapabilities[4] = [Capabilities::EMAIL_SEND];

        $this->assertCount(4, $registry->forUser(4));
        $this->assertSame([], $registry->forUser(2));
    }

    public function testRemovesInactiveMembersFromEveryGroup(): void
    {
        $this->assertSame([2, 3], $this->groups->removeInactive([2, 5, 3]));
    }
}
