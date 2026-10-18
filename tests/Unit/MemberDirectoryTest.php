<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Member;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\MembersService;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Tests\Fakes\WordPress;
use PHPUnit\Framework\TestCase;

final class MemberDirectoryTest extends TestCase
{
    private ProfileRepository $profiles;
    private MembersService $members;

    protected function setUp(): void
    {
        WordPress::reset();
        $fields = new ProfileFields();
        $this->profiles = new ProfileRepository($fields);
        $this->members = new MembersService(new MemberDirectory($this->profiles), $this->profiles, $fields);

        WordPress::addUser(1, ['administrator'], 'Admin');
        WordPress::addUser(2, [Roles::MEMBER], 'Bea');
        WordPress::addUser(3, [Roles::ORGANIZER], 'Anna');
        WordPress::addUser(4, [Roles::CONDUCTOR], 'Carl');
        WordPress::addUser(5, [Roles::MEMBER], 'Dora');
        WordPress::addUser(6, ['subscriber'], 'Sub');
        $this->profiles->setActive(5, false);
    }

    public function testListsActiveOrchestraUsersByName(): void
    {
        $this->assertSame(['Anna', 'Bea', 'Carl'], self::names($this->members->members()));
        $this->assertSame(['Anna', 'Bea', 'Carl', 'Dora'], self::names($this->members->members([], true)));
    }

    public function testFiltersByRole(): void
    {
        $this->assertSame([3, 2], $this->members->activeMemberIds([Roles::MEMBER, Roles::ORGANIZER]));
    }

    public function testFindsOnlyOrchestraUsers(): void
    {
        $this->assertSame([Roles::MEMBER], $this->members->member(5)?->roles);
        $this->assertFalse($this->members->member(5)->active);
        $this->assertNull($this->members->member(6));
        $this->assertNull($this->members->member(99));
    }

    public function testProvidesFirstAndLastName(): void
    {
        $user = WordPress::addUser(7, [Roles::MEMBER], 'Mia Muster');
        $user->first_name = 'Mia';
        $user->last_name = 'Muster';

        $member = $this->members->member(7);
        $this->assertSame(['Mia', 'Muster'], [$member?->firstName, $member?->lastName]);
    }

    public function testListsTheProfileFields(): void
    {
        $this->assertSame(
            ['financial_support', 'dietary_restrictions'],
            \array_map(static fn ($field): string => $field->id, $this->members->profileFields())
        );
    }

    /**
     * @param list<Member> $members
     * @return list<string>
     */
    private static function names(array $members): array
    {
        return \array_map(static fn (Member $member): string => $member->displayName, $members);
    }
}
