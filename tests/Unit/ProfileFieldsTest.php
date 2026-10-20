<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Members\Profile\ProfileField;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Tests\Fakes\WordPress;
use Orchestra\Core\Authorization\Roles;
use PHPUnit\Framework\TestCase;

final class ProfileFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        WordPress::reset();
    }

    public function testStartsWithTheFieldsOfEarlierVersionsAndKeepsTheirData(): void
    {
        WordPress::addUser(5, [Roles::MEMBER]);
        WordPress::$userMeta[5] = [
            'orchestra_members_financial_support' => '1',
            'orchestra_members_dietary_restrictions' => 'Vegan',
        ];

        $profile = (new ProfileRepository(new ProfileFields()))->find(5);

        $this->assertNotNull($profile);
        $this->assertSame(['financial_support' => '1', 'dietary_restrictions' => 'Vegan'], $profile->values);
    }

    public function testSavedFieldsReplaceTheDefaults(): void
    {
        $fields = new ProfileFields();
        $fields->save([new ProfileField('voice', 'Voice', ProfileField::TYPE_SELECT, '', ['Soprano', 'Alto'])]);

        $this->assertEquals(
            [new ProfileField('voice', 'Voice', ProfileField::TYPE_SELECT, '', ['Soprano', 'Alto'])],
            (new ProfileFields())->all()
        );

        $fields->save([]);
        $this->assertSame([], (new ProfileFields())->all());
    }

    public function testTheDefaultFieldMeansFinancialSupportUntilAnotherIsChosen(): void
    {
        $fields = new ProfileFields();
        $this->assertSame('financial_support', $fields->financialSupportField()?->id);

        $fields->save([
            new ProfileField('financial_support', 'Financial support', ProfileField::TYPE_CHECKBOX),
            new ProfileField('reduced_fee', 'Reduced fee', ProfileField::TYPE_CHECKBOX),
            new ProfileField('diet', 'Diet', ProfileField::TYPE_TEXT),
        ]);
        $fields->saveFinancialSupportField('reduced_fee');
        $this->assertSame('reduced_fee', $fields->financialSupportField()?->id);

        $fields->saveFinancialSupportField('diet');
        $this->assertNull($fields->financialSupportField(), 'only checkboxes');
        $fields->saveFinancialSupportField('');
        $this->assertNull($fields->financialSupportField());
    }

    public function testIgnoresBrokenStoredFields(): void
    {
        WordPress::$options[ProfileFields::OPTION] = [
            ['id' => 'shirt', 'label' => 'T-shirt', 'type' => 'text'],
            ['id' => 'x', 'label' => 'X', 'type' => 'unknown'],
            'garbage',
        ];

        $this->assertEquals(
            [new ProfileField('shirt', 'T-shirt', ProfileField::TYPE_TEXT)],
            (new ProfileFields())->all()
        );
    }

    public function testDerivesUnusedIdsFromTheLabel(): void
    {
        $this->assertSame('t_shirt_grosse', ProfileFields::newId('T-Shirt-Größe', []));
        $this->assertSame('voice_2', ProfileFields::newId('Voice', ['voice']));
        $this->assertSame('field', ProfileFields::newId('!!!', []));
        $this->assertSame('inactive_2', ProfileFields::newId('Inactive', []), 'reserved for the status');
    }
}
