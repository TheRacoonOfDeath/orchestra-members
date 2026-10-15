<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Privacy\PersonalData;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Tests\Fakes\WordPress;
use PHPUnit\Framework\TestCase;

final class PersonalDataTest extends TestCase
{
    private ProfileRepository $profiles;
    private PersonalData $privacy;

    protected function setUp(): void
    {
        WordPress::reset();
        $this->profiles = new ProfileRepository();
        $this->privacy = new PersonalData($this->profiles);
        WordPress::addUser(5, [Roles::MEMBER]);
        $this->profiles->saveSensitiveData(5, true, 'Vegan');
    }

    public function testExportsTheProfile(): void
    {
        $export = $this->privacy->export('user5@example.com');

        $this->assertTrue($export['done']);
        $this->assertCount(1, $export['data']);
        $this->assertSame([
            ['name' => 'Status', 'value' => 'Active'],
            ['name' => 'Financial support', 'value' => 'Needed'],
            ['name' => 'Dietary restrictions', 'value' => 'Vegan'],
        ], $export['data'][0]['data']);
        $this->assertSame([], $this->privacy->export('unknown@example.com')['data']);
    }

    public function testErasesTheSensitiveData(): void
    {
        $result = $this->privacy->erase('user5@example.com');

        $this->assertTrue($result['items_removed']);
        $this->assertFalse($result['items_retained']);
        $profile = $this->profiles->find(5);
        $this->assertNotNull($profile);
        $this->assertFalse($profile->financialSupport);
        $this->assertSame('', $profile->dietaryRestrictions);
    }

    public function testKeepsTheInactiveStatus(): void
    {
        $this->profiles->setActive(5, false);

        $result = $this->privacy->erase('user5@example.com');

        $this->assertTrue($result['items_retained']);
        $this->assertCount(1, $result['messages']);
        $this->assertFalse($this->profiles->isActive(5));
    }
}
