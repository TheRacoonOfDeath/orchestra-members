<?php

declare(strict_types=1);

namespace Orchestra\Members\Tests\Unit;

use Orchestra\Members\Profile\BirthDate;
use Orchestra\Members\Tests\Fakes\WordPress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BirthDateTest extends TestCase
{
    public function testNormalizesTheSubmittedValue(): void
    {
        $this->assertSame('1990-05-08', BirthDate::normalize(' 1990-05-08 '));
        $this->assertSame('', BirthDate::normalize(['1990-05-08']));
        $this->assertSame('', BirthDate::normalize(null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validDates(): array
    {
        return [
            'empty' => [''],
            'a normal date' => ['1990-05-08'],
            'the earliest date' => ['1900-01-01'],
            'today' => ['2026-11-20'],
            'a leap day' => ['2000-02-29'],
        ];
    }

    #[DataProvider('validDates')]
    public function testAcceptsValidDates(string $value): void
    {
        $this->assertNull(BirthDate::error($value, '2026-11-20'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'not a date' => ['soon'],
            'a day that does not exist' => ['2023-02-30'],
            'another format' => ['08.05.1990'],
            'in the future' => ['2026-11-21'],
            'before 1900' => ['1899-12-31'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function testRejectsInvalidDates(string $value): void
    {
        $this->assertSame('Please enter a valid birth date.', BirthDate::error($value, '2026-11-20'));
    }

    public function testDisplaysTheDateInTheSiteFormat(): void
    {
        WordPress::reset();
        WordPress::$options['date_format'] = 'd.m.Y';

        $this->assertSame('08.05.1990', BirthDate::display('1990-05-08'));
        $this->assertSame('', BirthDate::display(''));
    }
}
