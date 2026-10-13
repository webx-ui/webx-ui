<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Every section that gives its records an address tells the panel on the site about them.
 *
 * Forgetting is silent: the bar still appears on the record's page and says «nothing in the panel
 * stands behind this address», which reads like a decision rather than a gap. No test of the
 * section itself notices, since each one boots only its own provider — so this one reads the
 * providers of the whole monorepo and counts. A count rather than a match of names, because the
 * types are written as constants (`Product::TYPE`) as often as literals; the guide
 * (`apps/docs/guide/site-bar-modules.md`) says what to register.
 *
 * Outside the monorepo — the package mirrored on its own — there is nothing beside it to read,
 * and the test says so instead of passing on an empty list.
 */
final class SiteBarCoverageTest extends PhpUnitTestCase
{
    #[Test]
    public function every_address_type_of_a_section_is_registered_with_the_bar(): void
    {
        $providers = glob(dirname(__DIR__, 2).'/*/src/*ServiceProvider.php') ?: [];

        if (count($providers) < 2) {
            $this->markTestSkipped('Not in the monorepo: no other packages to read.');
        }

        $missing = [];

        foreach ($providers as $provider) {
            $source = (string) file_get_contents($provider);
            $types = substr_count($source, 'new RouteType(');
            $links = substr_count($source, 'new EntityLink(');

            if ($types > $links) {
                $missing[] = sprintf('%s: %d address type(s), %d EntityLink(s)', basename(dirname($provider, 2)).'/'.basename($provider), $types, $links);
            }
        }

        $this->assertSame([], $missing, "A section registers a RouteType without telling the panel on the site.\nSee apps/docs/guide/site-bar-modules.md:\n".implode("\n", $missing));
    }
}
