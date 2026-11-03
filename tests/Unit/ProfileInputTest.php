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

    public function testAChoiceListTakesTheOtherAnswer(): void
    {
        $diet = new ProfileField('diet', 'Diet', ProfileField::TYPE_SELECT, '', ['Vegan', 'Vegetarian'], false, true);
        $read = static fn (string $choice, string $other): string => ProfileInput::fromForm([
            'orchestra_members_diet' => $choice,
            ProfileField::OTHER_FIELD => ['diet' => $other],
        ], [$diet])->values['diet'];

        $this->assertSame('No fish', $read(ProfileField::OTHER, " No fish\n"));
        $this->assertSame('No fish', $read('', 'No fish'), 'the text counts without choosing "Other"');
        $this->assertSame('Vegan', $read('Vegan', 'No fish'), 'a chosen choice wins');
        $this->assertSame('', $read(ProfileField::OTHER, ''));
        $this->assertSame('No fish', $diet->otherText('No fish'));
        $this->assertSame('', $diet->otherText('Vegan'));
    }

    public function testOtherAnswersNeedTheOptionAndAreLimited(): void
    {
        $voice = $this->fields[3];
        $this->assertSame('', $voice->normalize(ProfileField::OTHER, 'Tenor'), 'no "Other" without the option');

        $diet = new ProfileField('diet', 'Diet', ProfileField::TYPE_SELECT, '', ['Vegan'], false, true);
        $input = ProfileInput::fromForm([
            'orchestra_members_diet' => ProfileField::OTHER,
            ProfileField::OTHER_FIELD => ['diet' => \str_repeat('x', ProfileField::OTHER_MAX_LENGTH + 1)],
        ], [$diet]);

        $this->assertSame(['Diet must not be longer than 100 characters.'], $input->validate());
    }
}
