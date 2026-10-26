<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The widget's script as it ships (§12.4): committed in dist/, installed from Composer with no Node
 * on the site — so it has to be built from the current sources (the widget and the engine of the
 * npm package) and stay inside its weight.
 */
final class DistTest extends PlainTestCase
{
    /** The widget with the engine built in: 8 KB gzip (§12.4). */
    private const int BUDGET = 8 * 1024;

    #[Test]
    public function dist_is_built_from_the_current_sources(): void
    {
        $built = json_decode((string) file_get_contents(self::root().'/dist/sources.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($built);

        $this->assertSame(self::sources(), $built['files'] ?? null, 'The widget or the engine changed since dist/ was built: run `npx vite build` in php/packages/module-configurator and commit dist/.');
    }

    #[Test]
    public function the_script_is_one_file_inside_its_budget(): void
    {
        $files = array_map('basename', (array) glob(self::root().'/dist/*'));
        sort($files);

        // One request: no chunk shared with anything else.
        $this->assertSame(['configurator.js', 'sources.json'], $files);

        $size = strlen((string) gzencode((string) file_get_contents(self::root().'/dist/configurator.js'), 9));

        $this->assertLessThanOrEqual(self::BUDGET, $size, sprintf('The configurator is %.1f KB gzip.', $size / 1024));
    }

    #[Test]
    public function the_script_has_no_eval(): void
    {
        $script = (string) file_get_contents(self::root().'/dist/configurator.js');

        // The formula is parsed, never run (§7.1).
        $this->assertDoesNotMatchRegularExpression('/\beval\s*\(|new\s+Function\s*\(/', $script);
    }

    private static function root(): string
    {
        return dirname(__DIR__);
    }

    /**
     * The same walk and hash as vite.config.js: the widget and the engine, without their tests.
     *
     * @return array<string, string>
     */
    private static function sources(): array
    {
        $files = [];
        $root = self::root();

        foreach ([$root.'/resources/js', $root.'/../../../packages/module-configurator/src/engine'] as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (! $file instanceof SplFileInfo || str_contains($file->getFilename(), '.test.')) {
                    continue;
                }

                $relative = self::relative($root, (string) $file->getRealPath());
                $files[$relative] = hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($file->getPathname())));
            }
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /** `path.relative()` of Node: from the package to a file, `..` where it goes up. */
    private static function relative(string $from, string $to): string
    {
        $from = explode('/', trim((string) realpath($from), '/'));
        $to = explode('/', trim($to, '/'));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return implode('/', [...array_fill(0, count($from), '..'), ...$to]);
    }
}
