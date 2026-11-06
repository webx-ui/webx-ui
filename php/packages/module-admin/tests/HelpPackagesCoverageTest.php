<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use WebxUi\Admin\Help\HelpFile;

/**
 * Every package with something to explain is inside the coverage of help.
 *
 * Staying out is silent: a package with no `HelpCoverageTest` never goes red, and one that never
 * calls `directory()` writes files nobody reads. No test of the package notices either, so this one
 * reads the sources of the whole monorepo (WEBX_UI_PANEL_HELP.md §6.3).
 *
 * It is also the only place that sees every package at once, so what crosses packages is checked
 * here: a list of includes, and a pending key whose text has arrived in a neighbour.
 */
final class HelpPackagesCoverageTest extends PhpUnitTestCase
{
    /** @return array<string, string> Package directory name to its path. */
    private function packages(): array
    {
        $packages = [];

        foreach (glob(dirname(__DIR__, 2).'/*/composer.json') ?: [] as $manifest) {
            $packages[basename(dirname($manifest))] = dirname($manifest);
        }

        if (count($packages) < 2) {
            $this->markTestSkipped('Not in the monorepo: no other packages to read.');
        }

        return $packages;
    }

    private function explainsSomething(string $path): bool
    {
        /** @var array{extra?: array{webx?: array{panel?: mixed}}} $manifest */
        $manifest = json_decode((string) file_get_contents($path.'/composer.json'), true);

        if (isset($manifest['extra']['webx']['panel'])) {
            return true;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path.'/src', \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (str_ends_with((string) $file, '.php') && preg_match('/ScreenRegistry::class\)\s*->(register|extend)\(|\$screens->(register|extend)\(/', (string) file_get_contents((string) $file)) === 1) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function a_package_with_a_screen_or_a_section_is_covered(): void
    {
        $missing = [];

        foreach ($this->packages() as $name => $path) {
            if (! $this->explainsSomething($path)) {
                continue;
            }

            if (! is_file($path.'/tests/HelpCoverageTest.php')) {
                $missing[] = "{$name}: no tests/HelpCoverageTest.php";
            }

            $providers = implode('', array_map(
                static fn (string $file): string => (string) file_get_contents($file),
                glob($path.'/src/*ServiceProvider.php') ?: [],
            ));

            if (! str_contains($providers, "HelpRegistry::class)->directory(__DIR__.'/../resources/help')")) {
                $missing[] = "{$name}: its provider does not register resources/help";
            }
        }

        $this->assertSame([], $missing, "See apps/docs/guide/panel-help.md:\n".implode("\n", $missing));
    }

    #[Test]
    public function what_a_page_includes_exists_and_includes_nothing_itself(): void
    {
        $files = [];

        foreach (glob(dirname(__DIR__, 2).'/*/resources/help/en/*.md') ?: [] as $path) {
            $files[basename($path, '.md')] = HelpFile::read($path, 'en');
        }

        $problems = [];

        foreach ($files as $key => $file) {
            foreach ($file->include as $include) {
                if (! isset($files[$include])) {
                    $problems[] = "{$key}: include [{$include}] leads nowhere";
                } elseif ($files[$include]->include !== []) {
                    $problems[] = "{$key}: [{$include}] includes pages of its own, and only one level is shown";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    #[Test]
    public function a_pending_key_has_no_text_in_any_package(): void
    {
        $path = __DIR__.'/help-pending.json';

        if (! is_file($path)) {
            $this->markTestSkipped('The list of pending help is gone: every key has its text.');
        }

        /** @var array<string, string> $pending */
        $pending = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $stale = array_values(array_filter(
            array_keys($pending),
            static fn (string $key): bool => ! str_contains($key, '#')
                && glob(dirname(__DIR__, 2)."/*/resources/help/en/{$key}.md") !== [],
        ));

        $this->assertSame([], $stale, 'Still pending, and the text is there: '.implode(', ', $stale));
    }
}
