<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Members\Profile\ProfileField;
use Orchestra\Members\Profile\ProfileInput;
use PHPUnit\Framework\TestCase;

final class ProfileInputTest extends TestCase
{
    /** @var list<ProfileField> */
    private array $fields;

    protected function setUp(): void
    {
        $this->fields = [
            new ProfileField('support', 'Support', ProfileField::TYPE_CHECKBOX),
            new ProfileField('diet', 'Diet', ProfileField::TYPE_TEXTAREA),
            new ProfileField('shirt', 'T-shirt', ProfileField::TYPE_TEXT),
            new ProfileField('voice', 'Voice', ProfileField::TYPE_SELECT, '', ['Soprano', 'Alto']),
        ];
    }

    public function testReadsEveryFieldUnderItsMetaKey(): void
    {
        $input = ProfileInput::fromForm([
            'orchestra_members_support' => '1',
            'orchestra_members_diet' => "  Vegan\r\nno <b>nuts</b>\x07  ",
            'orchestra_members_shirt' => "L\nXL",
            'orchestra_members_voice' => 'Alto',
        ], $this->fields);

        $this->assertSame(
            ['support' => '1', 'diet' => "Vegan\nno nuts", 'shirt' => 'L XL', 'voice' => 'Alto'],
            $input->values
        );
        $this->assertSame([], $input->validate());
    }

    public function testMissingOrInvalidValuesAreEmpty(): void
    {
        $input = ProfileInput::fromForm([
            'orchestra_members_support' => 'yes',
            'orchestra_members_diet' => ['not', 'a string'],
            'orchestra_members_voice' => 'Tenor',
        ], $this->fields);

        $this->assertSame(['support' => '', 'diet' => '', 'shirt' => '', 'voice' => ''], $input->values);
    }

    public function testRejectsTooLongText(): void
    {
        $input = ProfileInput::fromForm([
            'orchestra_members_diet' => \str_repeat('ä', ProfileField::TEXTAREA_MAX_LENGTH + 1),
            'orchestra_members_shirt' => \str_repeat('x', ProfileField::TEXT_MAX_LENGTH),
        ], $this->fields);

        $this->assertSame(['Diet must not be longer than 1000 characters.'], $input->validate());
    }
}
