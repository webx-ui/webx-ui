<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;

/**
 * Every Composer package of the monorepo explains itself to a code agent, in one format.
 *
 * The site's root AGENTS.md links to these files inside `vendor`, so a package without one is a
 * dead end in the middle of the guide. Runs only in the monorepo: the mirror of this package on
 * its own has no neighbours to look at.
 */
final class AgentDocsCoverageTest extends PlainTestCase
{
    /**
     * The packages still allowed to go without a guide; every other one must carry it.
     * Empty it once the last package has one, and every package is required from then on.
     */
    private const array PENDING = [
        'localization',
        'mcp',
        'module-admin',
        'module-audit',
        'module-auth',
        'module-banners',
        'module-blocks',
        'module-blog',
        'module-catalog',
        'module-catalog-brands',
        'module-catalog-labels',
        'module-catalog-landings',
        'module-catalog-manticore',
        'module-catalog-properties',
        'module-catalog-stock',
        'module-events',
        'module-faq',
        'module-inbox',
        'module-media',
        'module-menu',
        'module-press',
        'module-recipes',
        'module-reviews',
        'module-seo',
        'module-services',
        'module-settings',
        'module-tariffs',
        'module-team',
        'module-vacancies',
        'nested-set',
        'routing',
    ];

    /** The sections every guide has, in this order. */
    public const array SECTIONS = [
        '## What it owns',
        '## Change it without forking',
        '## Do not',
        '## Check your work',
        '## Read more',
    ];

    public const int MAX_LINES = 150;

    /** @return array<string, string> Package directory name to its path. */
    private function packages(): array
    {
        $root = dirname(__DIR__, 2);
        $packages = [];

        foreach (glob($root.'/*/composer.json') ?: [] as $manifest) {
            $packages[basename(dirname($manifest))] = dirname($manifest);
        }

        if (count($packages) < 2) {
            $this->markTestSkipped('Not in the monorepo: there are no neighbouring packages to check.');
        }

        ksort($packages);

        return $packages;
    }

    #[Test]
    public function every_package_that_must_have_a_guide_has_one(): void
    {
        $packages = $this->packages();
        $missing = array_keys(array_filter($packages, static fn (string $path): bool => ! is_file($path.'/AGENTS.md')));
        $required = array_values(array_diff(array_keys($packages), self::PENDING));

        $this->assertSame(
            [],
            array_values(array_intersect($required, $missing)),
            'These packages have no AGENTS.md: '.implode(', ', array_intersect($required, $missing)),
        );

        if ($missing !== []) {
            fwrite(STDERR, "\nWithout AGENTS.md yet: ".implode(', ', $missing)."\n");
        }
    }

    #[Test]
    public function every_guide_keeps_the_format(): void
    {
        foreach ($this->packages() as $name => $path) {
            if (! is_file($path.'/AGENTS.md')) {
                continue;
            }

            $text = (string) file_get_contents($path.'/AGENTS.md');
            $lines = explode("\n", rtrim($text));

            $this->assertSame("# webx-ui/{$name}", $lines[0], "{$name}/AGENTS.md starts with its package name");
            $this->assertLessThanOrEqual(self::MAX_LINES, count($lines), "{$name}/AGENTS.md is longer than ".self::MAX_LINES.' lines');

            $headings = array_values(array_filter($lines, static fn (string $line): bool => str_starts_with($line, '## ')));
            $this->assertSame(self::SECTIONS, $headings, "{$name}/AGENTS.md has the sections of the format, in order");

            $this->assertStringNotContainsString('](../', $text, "{$name}/AGENTS.md links outside itself relatively — that breaks inside vendor");
        }
    }

    #[Test]
    public function the_guide_ships_with_the_package(): void
    {
        foreach ($this->packages() as $name => $path) {
            $attributes = is_file($path.'/.gitattributes') ? (string) file_get_contents($path.'/.gitattributes') : '';

            $this->assertDoesNotMatchRegularExpression(
                '#^/?AGENTS\.md\s+export-ignore#m',
                $attributes,
                "{$name}/.gitattributes keeps AGENTS.md out of the dist",
            );
        }
    }
}
