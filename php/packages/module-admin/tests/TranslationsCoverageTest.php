<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;

/**
 * Every Composer package of the monorepo that speaks ships every language, with the same keys.
 *
 * A package's own TranslationsTest compares the languages it has; a package that never got a
 * language passes it, and on a site in that language the panel prints keys
 * (`webx-catalog::module.title`) instead of words — the fallback fills a missing line, not a
 * missing directory of a namespace the panel asks about. Runs only in the monorepo: the mirror
 * of this package on its own has no neighbours to look at.
 */
final class TranslationsCoverageTest extends PlainTestCase
{
    /** The languages every WebX UI package ships — see `WEBX_UI_COMPOSER_PACKAGES.md`. */
    public const array LOCALES = ['en', 'ru', 'uk', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    /**
     * Groups of prose rather than labels, per package: read whole, so a language nobody has
     * written them in yet gets the English page entire. The package's own TranslationsTest
     * explains each.
     *
     * @var array<string, list<string>>
     */
    private const array PROSE = ['module-blocks' => ['help']];

    /** @return array<string, string> Package directory name to its `lang` path, for packages that have one. */
    private function packages(): array
    {
        $root = dirname(__DIR__, 2);

        if (count(glob($root.'/*/composer.json') ?: []) < 2) {
            $this->markTestSkipped('Not in the monorepo: there are no neighbouring packages to check.');
        }

        $packages = [];

        foreach (glob($root.'/*/lang', GLOB_ONLYDIR) ?: [] as $lang) {
            $packages[basename(dirname($lang))] = $lang;
        }

        ksort($packages);

        return $packages;
    }

    #[Test]
    public function every_package_with_translations_ships_every_language(): void
    {
        $missing = [];

        foreach ($this->packages() as $package => $lang) {
            $present = array_map('basename', glob($lang.'/*', GLOB_ONLYDIR) ?: []);
            $absent = array_values(array_diff(self::LOCALES, $present));

            if ($absent !== []) {
                $missing[] = $package.': '.implode(', ', $absent);
            }
        }

        $this->assertSame([], $missing, 'Packages without some of the shipped languages.');
    }

    #[Test]
    public function every_language_of_a_package_has_the_keys_of_english(): void
    {
        $mismatches = [];

        foreach ($this->packages() as $package => $lang) {
            $prose = self::PROSE[$package] ?? [];
            $english = $this->keys($lang.'/en', $prose);

            foreach (self::LOCALES as $locale) {
                if ($locale === 'en' || ! is_dir($lang.'/'.$locale)) {
                    continue;
                }

                $keys = $this->keys($lang.'/'.$locale, $prose);
                $missing = array_diff($english, $keys);
                $extra = array_diff($keys, $english);

                if ($missing !== [] || $extra !== []) {
                    $mismatches[] = "{$package} [{$locale}]"
                        .($missing === [] ? '' : ' missing: '.implode(', ', array_slice($missing, 0, 5)))
                        .($extra === [] ? '' : ' extra: '.implode(', ', array_slice($extra, 0, 5)));
                }
            }
        }

        $this->assertSame([], $mismatches, 'Languages that do not say what English says.');
    }

    /**
     * Every line of a language directory as `group.key.path`, sorted.
     *
     * @param  list<string>  $skip  Groups left out.
     * @return list<string>
     */
    private function keys(string $directory, array $skip): array
    {
        $keys = [];

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            /** @var array<string, mixed> $lines */
            $lines = require $file;
            $group = basename($file, '.php');

            if (in_array($group, $skip, true)) {
                continue;
            }

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
}
