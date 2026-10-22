<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use WebxUi\Localization\Slug;

/**
 * No package makes an address or a code out of a name without saying the name's language.
 *
 * `Str::slug($name)` reads Cyrillic the English way — «Хіт» comes out `xit` — and passes every
 * test written in English. It has to go through {@see Slug} (or at least pass the language as
 * the third argument), so this reads the source of every package and fails on a call that does
 * not. Runs only in the monorepo: the mirror of this package on its own has no neighbours.
 */
final class SlugCoverageTest extends PlainTestCase
{
    /**
     * Calls that make no address and no code of anybody's words, with the reason.
     *
     * @var array<string, string>
     */
    private const array ALLOWED = [
        // A file name of the config's own `app.name` and `app.env`, never shown on the site.
        'module-admin/src/Snapshots/Snapshotter.php' => 'file name of a snapshot',
        // The URI of an MCP resource out of its English machine name.
        'mcp/src/Server/RegistryResource.php' => 'MCP resource URI',
    ];

    #[Test]
    public function no_package_slugs_a_name_without_its_language(): void
    {
        $root = dirname(__DIR__, 2);

        if (count(glob($root.'/*/composer.json') ?: []) < 2) {
            $this->markTestSkipped('Not in the monorepo: there are no neighbouring packages to check.');
        }

        $found = [];

        foreach (glob($root.'/*/src', GLOB_ONLYDIR) ?: [] as $src) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($root) + 1);

                if ($relative === 'localization/src/Slug.php' || isset(self::ALLOWED[$relative])) {
                    continue;
                }

                foreach ($this->calls((string) file_get_contents($file->getPathname())) as $line) {
                    $found[] = $relative.':'.$line;
                }
            }
        }

        $this->assertSame([], $found, 'Str::slug() without a language, or Str::transliterate(): use WebxUi\Localization\Slug.');
    }

    #[Test]
    public function the_reader_catches_what_it_is_meant_to(): void
    {
        $source = <<<'PHP'
            <?php
            $a = Str::slug($title);
            $b = Str::slug($title, '_');
            $c = \Illuminate\Support\Str::slug(trim($title, '-'));
            $d = Str::transliterate($title);
            $e = Str::slug($title, '-', $locale);
            $f = Str::slug(
                $title,
                '-',
                $locale,
            );
            PHP;

        $this->assertSame([2, 3, 4, 5], $this->calls($source));
    }

    /**
     * The lines of `Str::slug(…)` calls with fewer than three arguments, and of any
     * `Str::transliterate(…)` — which knows no language at all.
     *
     * @return list<int>
     */
    private function calls(string $source): array
    {
        $tokens = array_values(array_filter(
            \PhpToken::tokenize($source),
            static fn (\PhpToken $token): bool => ! $token->isIgnorable(),
        ));
        $lines = [];

        foreach ($tokens as $i => $token) {
            $class = $tokens[$i - 2] ?? null;

            if (! $token->is(T_STRING) || ! in_array($token->text, ['slug', 'transliterate'], true)
                || ($tokens[$i - 1] ?? null)?->is(T_DOUBLE_COLON) !== true
                || $class === null || ! in_array(ltrim($class->text, '\\'), ['Str', 'Illuminate\Support\Str'], true)
                || ($tokens[$i + 1] ?? null)?->text !== '(') {
                continue;
            }

            if ($token->text === 'transliterate' || $this->arguments($tokens, $i + 1) < 3) {
                $lines[] = $token->line;
            }
        }

        return $lines;
    }

    /**
     * @param  list<\PhpToken>  $tokens
     */
    private function arguments(array $tokens, int $open): int
    {
        $depth = 0;
        $count = 0;
        $empty = true;

        for ($i = $open; $i < count($tokens); $i++) {
            $text = $tokens[$i]->text;

            if (in_array($text, ['(', '[', '{'], true) || $tokens[$i]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;

                if ($depth === 1) {
                    continue;
                }
            } elseif (in_array($text, [')', ']', '}'], true)) {
                $depth--;

                if ($depth === 0) {
                    return $empty ? $count : $count + 1;
                }
            } elseif ($text === ',' && $depth === 1) {
                $count++;
                $empty = true;

                continue;
            }

            $empty = false;
        }

        return $count;
    }
}
