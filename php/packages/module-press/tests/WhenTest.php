<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use WebxUi\Press\Rendering\When;

/**
 * When an article ran, in words (§4.5): every line of the table, in Russian and in English — and a
 * month on its own in Russian named, not declined ("август", not "августа").
 */
final class WhenTest extends PhpUnitTestCase
{
    #[Test]
    #[DataProvider('lines')]
    public function every_precision_reads_in_the_language(?string $date, ?string $precision, string $locale, string $expected): void
    {
        $this->assertSame($expected, When::format($date === null ? null : Carbon::parse($date), $precision, $locale));
    }

    /**
     * @return array<string, array{string|null, string|null, string, string}>
     */
    public static function lines(): array
    {
        return [
            'day, ru' => ['2023-08-12', 'day', 'ru', '12 августа 2023'],
            'day, en' => ['2023-08-12', 'day', 'en', 'August 12, 2023'],
            'month, ru — named' => ['2023-08-12', 'month', 'ru', 'август 2023'],
            'month, en' => ['2023-08-12', 'month', 'en', 'August 2023'],
            'year, ru' => ['2023-08-12', 'year', 'ru', '2023'],
            'year, en' => ['2023-08-12', 'year', 'en', '2023'],
            'no date' => [null, 'day', 'ru', ''],
            'no precision is a day' => ['2023-08-12', null, 'en', 'August 12, 2023'],
            'day, uk' => ['2023-08-12', 'day', 'uk', '12 серпня 2023'],
            'month, uk — named' => ['2023-08-12', 'month', 'uk', 'серпень 2023'],
        ];
    }
}
