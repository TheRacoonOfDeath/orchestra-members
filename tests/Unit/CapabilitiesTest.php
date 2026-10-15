<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\CapabilityRegistry;
use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Capabilities;
use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase
{
    public function testRegistersTheDocumentedGrants(): void
    {
        $registry = new CapabilityRegistry();

        Capabilities::register($registry);

        $byRole = $registry->capabilitiesByRole();
        $own = [Capabilities::DIRECTORY_READ, Capabilities::PROFILE_EDIT_OWN, Capabilities::PROFILE_READ_OWN];
        $this->assertSame($own, $byRole[Roles::MEMBER]);
        $this->assertSame($own, $byRole[Roles::CONDUCTOR]);
        $this->assertSame([
            Capabilities::DIRECTORY_READ,
            Capabilities::PROFILE_EDIT_OWN,
            Capabilities::PROFILE_READ_OTHERS,
            Capabilities::PROFILE_READ_OWN,
            Capabilities::STATUS_EDIT,
        ], $byRole[Roles::ORGANIZER]);
        $this->assertContains(Capabilities::PROFILE_EDIT_OTHERS, $byRole[Roles::ADMINISTRATOR]);
    }

    public function testProfileCapabilitiesDependOnOwnership(): void
    {
        $registry = new CapabilityRegistry();
        Capabilities::register($registry);

        $read = $registry->metaCapabilityMapper(Capabilities::PROFILE_READ);
        $edit = $registry->metaCapabilityMapper(Capabilities::PROFILE_EDIT);
        $this->assertNotNull($read);
        $this->assertNotNull($edit);

        $this->assertSame([Capabilities::PROFILE_READ_OWN], $read(7, [7]));
        $this->assertSame([Capabilities::PROFILE_READ_OTHERS], $read(7, [8]));
        $this->assertSame([Capabilities::PROFILE_EDIT_OTHERS], $edit(7, [8]));
        $this->assertSame(['do_not_allow'], $read(7, []));
    }
}
