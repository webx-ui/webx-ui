<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The step's script as it ships (§12.3): committed in dist/, installed from Composer with no Node
 * on the site — so it has to be built from the current source and stay inside its weight.
 */
final class DistTest extends PlainTestCase
{
    /** The script of the checkout's delivery step: 4 KB gzip — the list with a search is the widgets'. */
    private const int BUDGET = 4 * 1024;

    #[Test]
    public function dist_is_built_from_the_current_sources(): void
    {
        $built = json_decode((string) file_get_contents(self::root().'/dist/sources.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($built);

        $this->assertSame(self::sources(), $built['files'] ?? null, 'The script changed since dist/ was built: run `npx vite build` in php/packages/module-delivery and commit dist/.');
    }

    #[Test]
    public function the_script_is_one_file_inside_its_budget(): void
    {
        $files = array_map('basename', (array) glob(self::root().'/dist/*'));
        sort($files);

        // One request: no chunk shared with anything else.
        $this->assertSame(['delivery.js', 'sources.json'], $files);

        $size = strlen((string) gzencode((string) file_get_contents(self::root().'/dist/delivery.js'), 9));

        $this->assertLessThanOrEqual(self::BUDGET, $size, sprintf('The delivery script is %.1f KB gzip.', $size / 1024));
    }

    #[Test]
    public function the_script_formats_no_money(): void
    {
        // Every price is the server's string: one way of writing money for the whole site.
        $this->assertStringNotContainsString('NumberFormat', (string) file_get_contents(self::root().'/dist/delivery.js'));
    }

    private static function root(): string
    {
        return dirname(__DIR__);
    }

    /**
     * The same walk and hash as vite.config.js: the script, without its tests.
     *
     * @return array<string, string>
     */
    private static function sources(): array
    {
        $files = [];
        $root = (string) realpath(self::root());

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || str_contains($file->getFilename(), '.test.')) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr((string) $file->getRealPath(), strlen($root))), '/');
            $files[$relative] = hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($file->getPathname())));
        }

        ksort($files, SORT_STRING);

        return $files;
    }
}
