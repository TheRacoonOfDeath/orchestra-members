<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Status\LoginBlocker;
use Orchestra\Members\Tests\Fakes\WordPress;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class LoginBlockerTest extends TestCase
{
    private ProfileRepository $profiles;
    private LoginBlocker $blocker;

    protected function setUp(): void
    {
        WordPress::reset();
        $this->profiles = new ProfileRepository();
        $this->blocker = new LoginBlocker($this->profiles);
    }

    public function testActiveMembersCanLogIn(): void
    {
        $user = WordPress::addUser(5, [Roles::MEMBER]);

        $this->assertSame($user, $this->blocker->blockInactive($user));
    }

    public function testInactiveMembersAreToldWhyTheyCannotLogIn(): void
    {
        $user = WordPress::addUser(5, [Roles::MEMBER]);
        $this->profiles->setActive(5, false);

        $result = $this->blocker->blockInactive($user);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(LoginBlocker::ERROR_CODE, $result->get_error_code());
    }

    public function testFailedLoginsAreLeftUnchanged(): void
    {
        $this->profiles->setActive(5, false);
        $wrongPassword = new WP_Error('incorrect_password', 'The password is incorrect.');

        $this->assertSame($wrongPassword, $this->blocker->blockInactive($wrongPassword));
        $this->assertNull($this->blocker->blockInactive(null));
    }

    public function testInactiveMembersCannotUseApplicationPasswords(): void
    {
        $user = WordPress::addUser(5, [Roles::MEMBER]);
        $errors = new WP_Error();

        $this->blocker->blockInactiveApplicationPassword($errors, $user);
        $this->assertFalse($errors->has_errors());

        $this->profiles->setActive(5, false);
        $this->blocker->blockInactiveApplicationPassword($errors, $user);
        $this->assertTrue($errors->has_errors());
    }
}
