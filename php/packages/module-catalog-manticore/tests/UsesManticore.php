<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use Throwable;
use WebxUi\Catalog\Manticore\Manticore;
use WebxUi\Catalog\Manticore\ManticoreServiceProvider;

/**
 * A live Manticore for a test (decision 26 of the Manticore spec): `MANTICORE_TEST_HOST` and
 * `MANTICORE_TEST_PORT` — a service container in CI, a shared server on a developer's network —
 * and a prefix of its own under `wxtest_`, so that a test touches no table but those it made and
 * removes them when it is done. Without a server that answers, the test is skipped, not failed.
 */
trait UsesManticore
{
    protected string $prefix = '';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ManticoreServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function useManticore($app): void
    {
        $this->prefix = 'wxtest_'.bin2hex(random_bytes(3));

        $app['config']->set('webx-catalog.engine', 'manticore');
        $app['config']->set('webx-catalog-manticore.host', (string) (getenv('MANTICORE_TEST_HOST') ?: '127.0.0.1'));
        $app['config']->set('webx-catalog-manticore.port', (int) (getenv('MANTICORE_TEST_PORT') ?: 9308));
        $app['config']->set('webx-catalog-manticore.table_prefix', $this->prefix);
        $app['config']->set('webx-catalog-manticore.connect_timeout', 1);
    }

    protected function skipWithoutManticore(): void
    {
        /** @var array<string, string|null> $asked address → why not, asked once per run */
        static $asked = [];
        $server = $this->app->make(Manticore::class);

        if (! array_key_exists($server->address(), $asked)) {
            try {
                $server->sql('SHOW VERSION');
                $asked[$server->address()] = null;
            } catch (Throwable $failure) {
                $asked[$server->address()] = $failure->getMessage();
            }
        }

        if ($asked[$server->address()] !== null) {
            $this->markTestSkipped('No Manticore at '.$server->address().': '.$asked[$server->address()]);
        }
    }

    /** Every table of this test's prefix, and nothing else. */
    protected function dropOwnTables(): void
    {
        if ($this->prefix === '' || ! isset($this->app)) {
            return;
        }

        try {
            $server = $this->app->make(Manticore::class);

            foreach ($server->sql('SHOW TABLES')[0]['data'] ?? [] as $row) {
                $name = is_array($row) ? (string) ($row['Table'] ?? '') : '';

                if (str_starts_with($name, $this->prefix.'_')) {
                    $server->sql('DROP TABLE IF EXISTS '.$name);
                }
            }
        } catch (Throwable) {
            // A server that went away has nothing of ours left to drop.
        }
    }
}
