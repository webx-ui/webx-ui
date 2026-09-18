<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Every language the project ships has to say the same things.
 *
 * A missing line degrades to English rather than to a key, which is the right behaviour and also
 * the reason a gap can sit unnoticed for months: nothing looks broken. This is what notices.
 */
final class TranslationsTest extends PhpUnitTestCase
{
    /** The languages every WebX UI package ships. */
    private const LOCALES = ['en', 'ru', 'uk', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    /**
     * Groups that are pages of prose rather than interface labels.
     *
     * A label is one line in a sentence somebody is reading, and a missing one is a hole in
     * that sentence — which is what the parity check below is for. A page of help is not: it
     * is read whole, and the dictionary the server builds puts the fallback language
     * underneath every group, so a language nobody has written this page in yet hands over the
     * English one entire and readable rather than a key.
     *
     * So these are translated when somebody writes them, not before the rest can ship.
     */
    private const PROSE = ['help'];

    #[Test]
    public function every_shipped_language_is_there(): void
    {
        $present = array_map('basename', glob($this->lang().'/*', GLOB_ONLYDIR) ?: []);

        sort($present);
        $expected = self::LOCALES;
        sort($expected);

        $this->assertSame($expected, $present);
    }

    #[Test]
    public function every_language_has_the_same_keys_as_english(): void
    {
        $english = $this->keys('en');

        $this->assertNotSame([], $english, 'The English translations are missing.');

        foreach (self::LOCALES as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $this->assertSame($english, $this->keys($locale), "[{$locale}] does not match English.");
        }
    }

    /**
     * Every line of a locale as `group.key.path`, sorted.
     *
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $keys = [];

        foreach (glob($this->lang()."/{$locale}/*.php") ?: [] as $file) {
            if (in_array(basename($file, '.php'), self::PROSE, true)) {
                continue;
            }

            /** @var array<string, mixed> $lines */
            $lines = require $file;
            $group = basename($file, '.php');

            foreach ($this->flatten($lines) as $key) {
                $keys[] = "{$group}.{$key}";
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return list<string>
     */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $keys = [...$keys, ...$this->flatten($value, $path)];

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }

    private function lang(): string
    {
        return __DIR__.'/../lang';
    }
}
