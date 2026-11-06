<?php

declare(strict_types=1);

namespace WebxUi\Templates\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Every language the project ships has to say the same things: a missing line degrades to English
 * rather than to a key, so a gap can sit unnoticed for months.
 */
final class TranslationsTest extends PhpUnitTestCase
{
    /** The languages every WebX UI package ships. */
    private const LOCALES = ['en', 'ru', 'uk', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    #[Test]
    public function every_language_has_the_same_lines_as_english(): void
    {
        $english = $this->lines('en');

        $this->assertNotSame([], $english);

        foreach (self::LOCALES as $locale) {
            $lines = $this->lines($locale);

            $this->assertSame(array_keys($english), array_keys($lines), "[{$locale}] does not match English.");

            foreach ($english as $key => $line) {
                preg_match_all('/:[a-z]+/', $line, $expected);
                preg_match_all('/:[a-z]+/', $lines[$key], $found);
                sort($expected[0]);
                sort($found[0]);

                $this->assertSame($expected[0], $found[0], "[{$locale}.{$key}] has other placeholders.");
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function lines(string $locale): array
    {
        /** @var array<string, string> $lines */
        $lines = require __DIR__."/../lang/{$locale}/errors.php";

        return $lines;
    }
}
