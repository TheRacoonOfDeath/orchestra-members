<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Account\RoleRemovalCleanup;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Tests\Fakes\WordPress;
use PHPUnit\Framework\TestCase;

final class RoleRemovalCleanupTest extends TestCase
{
    private ProfileRepository $profiles;
    private RoleRemovalCleanup $cleanup;

    protected function setUp(): void
    {
        WordPress::reset();
        $this->profiles = new ProfileRepository(new ProfileFields());
        $this->cleanup = new RoleRemovalCleanup($this->profiles);
    }

    public function testDeletesTheProfileOfAUserWhoNoLongerHasAnOrchestraRole(): void
    {
        WordPress::addUser(5, ['subscriber']);
        $this->profiles->saveSensitiveData(5, ['dietary_restrictions' => 'Vegan']);

        $this->cleanup->noteRemovedRole(5, Roles::MEMBER);
        $this->cleanup->deleteOrphanedProfiles();

        $this->assertFalse($this->profiles->hasSensitiveData(5));
    }

    public function testKeepsTheProfileWhenTheRoleWasOnlyChanged(): void
    {
        WordPress::addUser(5, [Roles::ORGANIZER]);
        $this->profiles->saveSensitiveData(5, ['dietary_restrictions' => 'Vegan']);

        $this->cleanup->noteRemovedRole(5, Roles::MEMBER);
        $this->cleanup->deleteOrphanedProfiles();

        $this->assertTrue($this->profiles->hasSensitiveData(5));
    }

    public function testIgnoresOtherRoles(): void
    {
        WordPress::addUser(5, []);
        $this->profiles->saveSensitiveData(5, ['dietary_restrictions' => 'Vegan']);

        $this->cleanup->noteRemovedRole(5, 'editor');
        $this->cleanup->noteReplacedRoles(5, 'subscriber', ['editor']);
        $this->cleanup->deleteOrphanedProfiles();

        $this->assertTrue($this->profiles->hasSensitiveData(5));
    }
}
