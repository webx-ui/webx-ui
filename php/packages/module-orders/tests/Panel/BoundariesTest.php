<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every change of an order is a call of `Orders` (decision 18): the panel's controllers and the
 * classes that read for them never write an order, its lines, its history or its statuses
 * themselves — not even a «quick» save of one column. Read off the source, so a shortcut taken
 * later fails here before it skips a lock, a line of history or an event.
 */
final class BoundariesTest extends TestCase
{
    /** What writes a model or a table, as it would be spelled in a controller. */
    private const array WRITES = [
        '/->save\(/', '/->saveQuietly\(/', '/->update\(/', '/->delete\(/', '/->forceDelete\(/',
        '/->increment\(/', '/->decrement\(/', '/->insert\(/', '/->upsert\(/', '/->fill\(/',
        '/::create\(/', '/->create\(/', '/->push\(/', '/->touch\(/', '/DB::/',
    ];

    #[Test]
    public function the_panel_reads_and_leaves_every_write_to_the_service(): void
    {
        $root = dirname(__DIR__, 2).'/src';
        $files = [
            ...glob($root.'/Http/Controllers/Panel/*.php') ?: [],
            ...glob($root.'/Panel/*.php') ?: [],
        ];

        $this->assertNotEmpty($files);
        $found = [];

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            foreach (self::WRITES as $write) {
                if (preg_match($write, $source) === 1) {
                    $found[] = basename($file).': '.trim($write, '/\\');
                }
            }
        }

        $this->assertSame([], $found, 'A panel class writes on its own instead of through Orders.');
    }
}
