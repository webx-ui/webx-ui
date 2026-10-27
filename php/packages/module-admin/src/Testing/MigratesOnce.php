<?php

declare(strict_types=1);

namespace WebxUi\Admin\Testing;

use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;

/**
 * `artisan migrate` once per schema, not once per test, in any package's Testbench case:
 *
 *     use MigratesOnce;
 *
 * Every test still gets a fresh `:memory:` database of its own — the same isolation as a
 * migration per test, with no transactions and autoincrements from zero. The first test of a
 * schema migrates for real and the result is kept; the next ones get it replayed, which costs a
 * few milliseconds against 20–50 for the migrations of a module and what it is used with.
 *
 * A schema is the migrations it is made of (paths, file names, sizes, times) and the config a
 * migration could read — so a test that loads one more path, or a `#[DefineEnvironment]` that
 * changes what a migration writes, migrates for itself. Any other connection migrates as usual,
 * and so does a database something wrote to first: add a path with `migrator->path()` rather
 * than Testbench's `loadMigrationsFrom()`, which migrates on the spot.
 *
 * @phpstan-require-extends TestCase
 */
trait MigratesOnce
{
    protected function defineDatabaseMigrations(): void
    {
        $this->migrateOnce();
    }

    protected function migrateOnce(): void
    {
        $connection = DB::connection();

        // Something already wrote here — Testbench's `loadMigrationsFrom()` migrates on the spot —
        // and a snapshot of half a schema would be replayed as the whole of it.
        if (! $connection instanceof SQLiteConnection
            || $connection->getDatabaseName() !== ':memory:'
            || ! SchemaSnapshots::isEmpty($connection->getPdo())) {
            $this->artisan('migrate', ['--force' => true])->run();

            return;
        }

        $key = $this->schemaKey();
        $schema = SchemaSnapshots::get($key);

        if ($schema === null) {
            $this->artisan('migrate', ['--force' => true])->run();
            SchemaSnapshots::put($key, SchemaSnapshots::take($connection->getPdo()));

            return;
        }

        SchemaSnapshots::restore($connection->getPdo(), $schema);
    }

    private function schemaKey(): string
    {
        $migrator = $this->app->make('migrator');
        $paths = [...$migrator->paths(), $this->app->databasePath('migrations')];
        $files = [];

        foreach (array_unique($paths) as $path) {
            foreach (is_dir($path) ? (glob($path.'/*.php') ?: []) : [$path] as $file) {
                $files[] = is_file($file) ? $file.':'.filesize($file).':'.filemtime($file) : $file;
            }
        }

        $config = $this->app['config']->all();
        // Random per test and read by no migration.
        unset($config['app']['key']);

        return md5(implode("\n", $files)."\n".json_encode($config, JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
}
