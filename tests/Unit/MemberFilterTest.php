<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Member;
use Orchestra\Members\MemberFilter;
use PHPUnit\Framework\TestCase;

final class MemberFilterTest extends TestCase
{
    public function testSearchesNamesAndOnlyWithPermissionEmails(): void
    {
        $members = self::members();

        $this->assertSame([1], self::ids((new MemberFilter('müller'))->apply($members)));
        $this->assertSame([], self::ids((new MemberFilter('anna@'))->apply($members)));
        $this->assertSame([2], self::ids((new MemberFilter('anna@', searchEmail: true))->apply($members)));
    }

    public function testFiltersByStatusAndRole(): void
    {
        $members = self::members();

        $this->assertSame([3], self::ids((new MemberFilter(status: MemberFilter::INACTIVE))->apply($members)));
        $this->assertSame([1, 2], self::ids((new MemberFilter(status: MemberFilter::ACTIVE))->apply($members)));
        $this->assertSame([2], self::ids((new MemberFilter(role: Roles::ORGANIZER))->apply($members)));
        $this->assertTrue((new MemberFilter())->isEmpty());
    }

    /**
     * @return list<Member>
     */
    private static function members(): array
    {
        return [
            new Member(1, 'Mia', 'mia@example.com', [Roles::MEMBER], true, 'Mia', 'Müller'),
            new Member(2, 'Olga', 'anna@example.com', [Roles::ORGANIZER], true),
            new Member(3, 'Max', 'max@example.com', [Roles::MEMBER], false),
        ];
    }

    /**
     * @param list<Member> $members
     * @return list<int>
     */
    private static function ids(array $members): array
    {
        return \array_map(static fn (Member $member): int => $member->id, $members);
    }
}
