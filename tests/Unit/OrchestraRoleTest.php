<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Members\Account\OrchestraRole;
use PHPUnit\Framework\TestCase;

final class OrchestraRoleTest extends TestCase
{
    public function testAnAdministratorBecomesAMemberAndStaysAdministrator(): void
    {
        $this->assertSame(
            ['remove' => [], 'add' => ['orchestra_member']],
            OrchestraRole::changes(['administrator'], 'orchestra_member')
        );
    }

    public function testTheOrchestraRoleIsReplacedAndOtherRolesAreKept(): void
    {
        $this->assertSame(
            ['remove' => ['orchestra_member'], 'add' => ['orchestra_organizer']],
            OrchestraRole::changes(['editor', 'orchestra_member'], 'orchestra_organizer')
        );
        $this->assertSame(
            ['remove' => ['orchestra_member'], 'add' => []],
            OrchestraRole::changes(['editor', 'orchestra_member'], '')
        );
    }

    public function testAnUnchangedRoleChangesNothing(): void
    {
        $this->assertSame(
            ['remove' => [], 'add' => []],
            OrchestraRole::changes(['orchestra_conductor'], 'orchestra_conductor')
        );
    }

    public function testOnlyOrchestraRolesCanBeChosen(): void
    {
        $this->assertTrue(OrchestraRole::isChoice(''));
        $this->assertTrue(OrchestraRole::isChoice('orchestra_organizer'));
        $this->assertFalse(OrchestraRole::isChoice('administrator'));
    }
}
