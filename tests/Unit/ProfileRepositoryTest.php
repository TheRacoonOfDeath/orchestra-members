<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Profile\ProfileField;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Tests\Fakes\WordPress;
use PHPUnit\Framework\TestCase;

final class ProfileRepositoryTest extends TestCase
{
    private ProfileRepository $profiles;

    protected function setUp(): void
    {
        WordPress::reset();
        $fields = new ProfileFields();
        $fields->save([
            new ProfileField('photos', 'Photos', ProfileField::TYPE_CHECKBOX, 'Photos may be published', [], true),
            new ProfileField('support', 'Support', ProfileField::TYPE_CHECKBOX),
        ]);
        $this->profiles = new ProfileRepository($fields);
        WordPress::addUser(5, [Roles::MEMBER]);
    }

    public function testACheckboxNeverAnsweredHasItsDefault(): void
    {
        $this->assertSame(['photos' => '1', 'support' => ''], $this->profiles->find(5)?->values);
        $this->assertFalse($this->profiles->hasSensitiveData(5), 'a default is not stored data');
    }

    public function testAnUntickedCheckboxIsStoredAndKeepsItsAnswer(): void
    {
        $this->profiles->saveSensitiveData(5, ['photos' => '', 'support' => '']);

        $this->assertSame(['photos' => '', 'support' => ''], $this->profiles->find(5)?->values);
        $this->assertSame(ProfileField::UNCHECKED, \get_user_meta(5, 'orchestra_members_photos', true));
        $this->assertTrue($this->profiles->hasSensitiveData(5));
    }

    public function testInactiveMembersHaveNoDefaults(): void
    {
        $this->profiles->setActive(5, false);

        $this->assertSame(['photos' => '', 'support' => ''], $this->profiles->find(5)?->values);
    }

    public function testAfterTheDataIsDeletedTheDefaultAppliesAgain(): void
    {
        $this->profiles->saveSensitiveData(5, ['photos' => '']);
        $this->profiles->deleteSensitiveData(5);

        $this->assertSame('1', $this->profiles->find(5)?->value('photos'));
    }
}
