<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Themes\Vocabulary;
use WebxUi\Widgets\Widgets;

/**
 * What ships in dist/ and what it may cost: it is committed and installed from Composer with no
 * Node on the site, so it has to match the sources, stay inside its weight budget (spec §4) and
 * keep the rule every stylesheet of the chain keeps (THEMES §8).
 */
final class DistTest extends PlainTestCase
{
    /** Runtime with the behaviours, the mobile menu and the header: 16 KB gzip (§4). */
    private const int RUNTIME_BUDGET = 16 * 1024;

    private const array LOCALES = ['en', 'ru', 'uk', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    #[Test]
    public function dist_is_built_from_the_current_sources(): void
    {
        $built = json_decode((string) file_get_contents(Widgets::path().'/dist/sources.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($built);

        $this->assertSame(self::sources(), $built['files'] ?? null, 'resources/ changed since dist/ was built: run `npx vite build` in php/packages/widgets and commit dist/.');
    }

    #[Test]
    public function the_runtime_stays_inside_its_budget(): void
    {
        $size = 0;

        foreach (['runtime.js', 'runtime.css'] as $file) {
            $this->assertFileExists(Widgets::path().'/dist/'.$file);
            $size += strlen((string) gzencode((string) file_get_contents(Widgets::path().'/dist/'.$file), 9));
        }

        $this->assertLessThanOrEqual(self::RUNTIME_BUDGET, $size, sprintf('The runtime is %.1f KB gzip.', $size / 1024));
    }

    /**
     * Site tokens of the vocabulary and the widgets' own `--webx-<name>-*`, declared here; no
     * literal colour, no fallback in var(), no width @media, one flat class per rule.
     */
    #[Test]
    public function the_stylesheets_know_only_tokens(): void
    {
        $vocabulary = Vocabulary::base();
        $stylesheets = [];

        foreach (array_keys(self::sources()) as $relative) {
            if (str_ends_with($relative, '.css')) {
                $stylesheets[$relative] = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(Widgets::path().'/'.$relative));
            }
        }

        preg_match_all('/(--webx-[a-z0-9-]+)\s*:/', implode("\n", $stylesheets), $declared);

        foreach ($stylesheets as $relative => $css) {
            preg_match_all('/var\(\s*--([a-z0-9-]+)\s*([,)])/i', $css, $references, PREG_SET_ORDER);

            foreach ($references as [, $variable, $after]) {
                if (str_starts_with($variable, 'webx-')) {
                    $this->assertContains("--{$variable}", $declared[1], "{$relative}: --{$variable} is declared nowhere.");
                } else {
                    $this->assertStringStartsWith('site-', $variable, "{$relative}: --{$variable} is neither a site token nor a widget's own.");
                    $this->assertTrue($vocabulary->has(substr($variable, 5)), "{$relative}: --{$variable} is not in the vocabulary.");
                }

                $this->assertSame(')', $after, "{$relative}: var(--{$variable}, …) has a fallback that no preset repaints.");
            }

            $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\(/i', $css, "{$relative}: a literal colour.");
            $this->assertDoesNotMatchRegularExpression('/@media[^{]*width/i', $css, "{$relative}: width is the container's to decide (@container).");

            preg_match_all('/(?:^|[;{}])\s*([^;{}@]+?)\s*\{/', $css, $rules);

            // One flat class (a state or a modifier on it at most): enough to win over the prose of the theme, which is
            // all :where(), and no more than a theme matches by writing the same class later.
            foreach ($rules[1] as $selectors) {
                foreach (array_map('trim', explode(',', $selectors)) as $selector) {
                    $this->assertMatchesRegularExpression(
                        '/^(?::where\(:root\)|(?:html:not\(\.webx-js\) )?\.webx-[a-z0-9_-]+(?:\.(?:is-[a-z-]+|webx-[a-z0-9_-]+--[a-z0-9-]+))?(?:\[[^\]]+\]|::?[a-z-]+)*)$/',
                        $selector,
                        "{$relative}: \"{$selector}\" — a widget's rule is one .webx-* class, with a state or a modifier at most.",
                    );
                }
            }
        }
    }

    #[Test]
    public function every_locale_has_every_word(): void
    {
        $english = self::keys(require Widgets::path().'/lang/en/widgets.php');

        foreach (self::LOCALES as $locale) {
            $file = Widgets::path()."/lang/{$locale}/widgets.php";
            $this->assertFileExists($file);
            $this->assertSame($english, self::keys(require $file), "lang/{$locale} differs from lang/en.");
        }
    }

    /**
     * @param  array<array-key, mixed>  $words
     * @return list<string>
     */
    private static function keys(array $words, string $prefix = ''): array
    {
        $keys = [];

        foreach ($words as $key => $value) {
            array_push($keys, ...(is_array($value) ? self::keys($value, "{$prefix}{$key}.") : ["{$prefix}{$key}"]));
        }

        sort($keys);

        return $keys;
    }

    /**
     * Every source file but the tests, by sha256, line endings normalised — the walk
     * vite.config.js does.
     *
     * @return array<string, string>
     */
    private static function sources(): array
    {
        $files = [];
        $root = Widgets::path();

        foreach (['resources/js', 'resources/css'] as $directory) {
            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (str_contains($file->getFilename(), '.test.')) {
                    continue;
                }

                $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
                $files[$relative] = hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($file->getPathname())));
            }
        }

        ksort($files, SORT_STRING);

        return $files;
    }
}
