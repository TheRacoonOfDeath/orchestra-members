<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Members\Profile\ProfileInput;
use PHPUnit\Framework\TestCase;

final class ProfileInputTest extends TestCase
{
    public function testReadsTheFormFields(): void
    {
        $input = ProfileInput::fromForm([
            ProfileInput::FIELD_FINANCIAL_SUPPORT => '1',
            ProfileInput::FIELD_DIETARY_RESTRICTIONS => "  Vegan\r\nno <b>nuts</b>\x07  ",
        ]);

        $this->assertTrue($input->financialSupport);
        $this->assertSame("Vegan\nno nuts", $input->dietaryRestrictions);
        $this->assertSame([], $input->validate());
    }

    public function testMissingFieldsMeanNoSupportAndNoRestrictions(): void
    {
        $input = ProfileInput::fromForm([ProfileInput::FIELD_DIETARY_RESTRICTIONS => ['not', 'a string']]);

        $this->assertFalse($input->financialSupport);
        $this->assertSame('', $input->dietaryRestrictions);
    }

    public function testRejectsTooLongDietaryRestrictions(): void
    {
        $tooLong = \str_repeat('ä', ProfileInput::DIETARY_RESTRICTIONS_MAX_LENGTH + 1);
        $input = ProfileInput::fromForm([ProfileInput::FIELD_DIETARY_RESTRICTIONS => $tooLong]);

        $this->assertCount(1, $input->validate());
    }
}
