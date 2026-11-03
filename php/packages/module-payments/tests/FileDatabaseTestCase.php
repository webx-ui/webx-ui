<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Foundation\Application;

/**
 * On a database file, which a second connection can open: the journal's own connection is then a
 * real second session, as on a site (decision 38) — an in-memory database is private to one PDO.
 */
abstract class FileDatabaseTestCase extends TestCase
{
    private ?string $file = null;

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->file = (string) tempnam(sys_get_temp_dir(), 'webx-payments-');
        $app['config']->set('database.connections.testing.database', $this->file);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->file !== null && is_file($this->file)) {
            unlink($this->file);
        }
    }
}
