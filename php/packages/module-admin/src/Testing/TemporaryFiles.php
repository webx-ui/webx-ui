<?php

declare(strict_types=1);

namespace WebxUi\Admin\Testing;

use Orchestra\Testbench\TestCase;

/**
 * A file in the system's temporary directory that is gone when the test is, in any package's
 * Testbench case:
 *
 *     use TemporaryFiles;
 *
 *     $path = $this->temporaryFile('webx-test-', '.csv');
 *
 * `tempnam()` alone stays behind, and `tempnam().'.csv'` leaves two files a run, both of them
 * forever: a few thousand tests made tens of thousands of them.
 *
 * @phpstan-require-extends TestCase
 */
trait TemporaryFiles
{
    /** An empty file, with `$extension` (".csv") when the code under test reads by it. */
    protected function temporaryFile(string $prefix, string $extension = ''): string
    {
        $name = (string) tempnam(sys_get_temp_dir(), $prefix);
        $path = $name.$extension;

        if ($extension !== '') {
            rename($name, $path);
        }

        $this->beforeApplicationDestroyed(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        return $path;
    }
}
