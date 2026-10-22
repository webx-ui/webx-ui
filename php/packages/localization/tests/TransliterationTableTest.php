<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use WebxUi\Localization\Slug;

/**
 * The panel's address field transliterates with the server's own table, written out by
 * `scripts/transliteration.php` — so that the address the editor sees while typing is the one
 * {@see Slug} makes. An update of `voku/portable-ascii` that moves a letter
 * fails here: run the script and commit the table. Runs only in the monorepo.
 */
final class TransliterationTableTest extends PlainTestCase
{
    #[Test]
    public function the_panels_table_is_the_servers(): void
    {
        $repository = dirname(__DIR__, 4);
        $script = $repository.'/scripts/transliteration.php';
        $json = $repository.'/packages/module-admin/src/transliteration.json';

        if (! is_file($script) || ! is_file($json)) {
            $this->markTestSkipped('Not in the monorepo: there is no panel to compare with.');
        }

        $build = require $script;
        $this->assertInstanceOf(\Closure::class, $build);

        $this->assertSame(
            $build(),
            json_decode((string) file_get_contents($json), true, flags: JSON_THROW_ON_ERROR),
            'Run `php scripts/transliteration.php` and commit packages/module-admin/src/transliteration.json.',
        );
    }
}
