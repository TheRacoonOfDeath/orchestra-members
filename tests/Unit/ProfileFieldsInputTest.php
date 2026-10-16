<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Members\Profile\ProfileField;
use Orchestra\Members\Profile\ProfileFieldsInput;
use PHPUnit\Framework\TestCase;

final class ProfileFieldsInputTest extends TestCase
{
    /** @var list<ProfileField> */
    private array $current;

    protected function setUp(): void
    {
        $this->current = [
            new ProfileField('support', 'Support', ProfileField::TYPE_CHECKBOX, 'I need support'),
            new ProfileField('diet', 'Diet', ProfileField::TYPE_TEXTAREA),
        ];
    }

    public function testRenamesReordersAddsAndRemovesFields(): void
    {
        $input = ProfileFieldsInput::fromForm([
            ProfileFieldsInput::FIELD_ORDER => ['new:0', 'support', 'diet', 'new:1'],
            ProfileFieldsInput::FIELD_DELETE => 'support',
            ProfileFieldsInput::FIELD_FIELDS => [
                'support' => ['label' => 'Support', 'hint' => ''],
                'diet' => ['label' => ' Dietary needs ', 'hint' => 'Allergies', 'type' => 'text'],
            ],
            ProfileFieldsInput::FIELD_NEW_FIELDS => [
                '0' => ['label' => 'Voice', 'type' => 'select', 'choices' => "Soprano\n\nAlto\nAlto"],
                '1' => ['label' => '', 'type' => 'text'],
            ],
        ], $this->current);

        $this->assertSame([], $input->errors);
        $this->assertEquals([
            new ProfileField('voice', 'Voice', ProfileField::TYPE_SELECT, '', ['Soprano', 'Alto']),
            new ProfileField('diet', 'Dietary needs', ProfileField::TYPE_TEXTAREA, 'Allergies'),
        ], $input->fields, 'the type of an existing field cannot change');
        $this->assertEquals([$this->current[0]], $input->removed);
    }

    public function testTheOrderOfTheRowsIsTheOrderOfTheFields(): void
    {
        $input = ProfileFieldsInput::fromForm([
            ProfileFieldsInput::FIELD_ORDER => ['diet', 'new:5', 'support'],
            ProfileFieldsInput::FIELD_NEW_FIELDS => ['5' => ['label' => 'Voice', 'type' => 'text']],
        ], $this->current);

        $this->assertSame(['diet', 'voice', 'support'], \array_map(static fn ($f) => $f->id, $input->fields));
    }

    public function testRowsWithoutAPlaceInTheOrderKeepTheirOrderAtTheEnd(): void
    {
        $input = ProfileFieldsInput::fromForm([
            ProfileFieldsInput::FIELD_ORDER => ['diet'],
            ProfileFieldsInput::FIELD_NEW_FIELDS => [['label' => 'Voice', 'type' => 'text']],
        ], $this->current);

        $this->assertSame(['diet', 'support', 'voice'], \array_map(static fn ($f) => $f->id, $input->fields));
    }

    public function testKeepsFieldsThatWereNotSubmitted(): void
    {
        $input = ProfileFieldsInput::fromForm([], $this->current);

        $this->assertEquals($this->current, $input->fields);
        $this->assertSame([], $input->removed);
    }

    public function testReportsInvalidFields(): void
    {
        $input = ProfileFieldsInput::fromForm([
            ProfileFieldsInput::FIELD_FIELDS => [
                'support' => ['label' => ''],
                'diet' => ['label' => 'Voice'],
            ],
            ProfileFieldsInput::FIELD_NEW_FIELDS => [
                ['label' => 'voice', 'type' => 'select', 'choices' => ''],
                ['label' => 'Size', 'type' => 'unknown'],
                ['label' => \str_repeat('x', ProfileField::LABEL_MAX_LENGTH + 1), 'type' => 'text'],
            ],
        ], $this->current);

        $this->assertSame([
            'Every field needs a label.',
            'Please enter the choices for "voice", one per line.',
            'Please choose a type for "Size".',
            'The label "' . \str_repeat('x', 101) . '" is longer than 100 characters.',
            'The label "voice" is used more than once.',
        ], $input->errors);
    }
}
