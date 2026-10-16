<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\MembersService;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Status\StatusService;
use Orchestra\Members\Tests\Fakes\WordPress;
use PHPUnit\Framework\TestCase;

final class StatusServiceTest extends TestCase
{
    private ProfileRepository $profiles;
    private StatusService $status;

    protected function setUp(): void
    {
        WordPress::reset();
        $this->profiles = new ProfileRepository(new ProfileFields());
        $this->status = new StatusService($this->profiles);
    }

    public function testDeactivationDeletesSensitiveDataAndEndsSessions(): void
    {
        WordPress::addUser(5, [Roles::MEMBER]);
        $this->profiles->saveSensitiveData(5, ['financial_support' => '1', 'dietary_restrictions' => 'Vegan']);

        $this->status->deactivate(5);

        $profile = $this->profiles->find(5);
        $this->assertNotNull($profile);
        $this->assertFalse($profile->active);
        $this->assertSame(['financial_support' => '', 'dietary_restrictions' => ''], $profile->values);
        $this->assertSame([5], WordPress::$destroyedSessions);
        $this->assertSame([[MembersService::ACTION_DEACTIVATED, [5]]], WordPress::$actions);
    }

    public function testReactivationFiresTheActivatedAction(): void
    {
        WordPress::addUser(5, [Roles::MEMBER]);
        $this->status->deactivate(5);
        WordPress::$actions = [];

        $this->status->activate(5);
        $this->status->activate(5);

        $this->assertTrue($this->profiles->isActive(5));
        $this->assertSame([[MembersService::ACTION_ACTIVATED, [5]]], WordPress::$actions);
    }

    public function testDeactivatingTwiceDoesNothingTheSecondTime(): void
    {
        WordPress::addUser(5, [Roles::MEMBER]);

        $this->status->deactivate(5);
        $this->status->deactivate(5);

        $this->assertCount(1, WordPress::$actions);
    }

    public function testOnlyOtherOrchestraUsersWhoAreNoAdministratorsCanBeDeactivated(): void
    {
        WordPress::addUser(1, ['administrator', Roles::ORGANIZER]);
        WordPress::addUser(2, [Roles::ORGANIZER]);
        WordPress::addUser(3, ['subscriber']);
        WordPress::addUser(4, [Roles::CONDUCTOR]);

        $this->assertNotNull($this->status->refusal(2, 1));
        $this->assertNotNull($this->status->refusal(2, 2));
        $this->assertNotNull($this->status->refusal(2, 3));
        $this->assertNotNull($this->status->refusal(2, 99));
        $this->assertNull($this->status->refusal(2, 4));
        $this->assertNull($this->status->refusal(1, 2));
    }
}
