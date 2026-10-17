<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Every language the packages ship, with the same keys as English: the words of the bar are
 * handed to its script as they are, and a missing one is an empty label on the page.
 */
final class TranslationsTest extends PhpUnitTestCase
{
    /** The languages every WebX UI package ships. */
    private const LOCALES = ['en', 'ru', 'uk', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    #[Test]
    public function every_shipped_language_has_the_keys_of_english(): void
    {
        $present = array_map('basename', glob(dirname(__DIR__).'/lang/*', GLOB_ONLYDIR) ?: []);
        sort($present);
        $expected = self::LOCALES;
        sort($expected);

        $this->assertSame($expected, $present);

        $english = $this->keys('en');
        $this->assertNotSame([], $english);

        foreach (self::LOCALES as $locale) {
            $this->assertSame($english, $this->keys($locale), "[{$locale}] does not match English.");
        }
    }

    /**
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $keys = [];

        foreach (glob(dirname(__DIR__)."/lang/{$locale}/*.php") ?: [] as $file) {
            /** @var array<string, string> $lines */
            $lines = require $file;

            foreach (array_keys($lines) as $key) {
                $keys[] = basename($file, '.php').'.'.$key;
            }
        }

        sort($keys);

        return $keys;
    }
}
