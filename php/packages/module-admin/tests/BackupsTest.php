<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Backups\BackupFailed;
use WebxUi\Admin\Backups\Backups;
use WebxUi\Admin\Backups\Dumper;

/**
 * What can be checked without a database server.
 *
 * The dumps here are SQLite ones, which are a copy of a file: enough to prove that a whole
 * gzipped snapshot appears under the right name, that rotation keeps what it should and that a
 * failure leaves last week alone. What `mysqldump` actually produces is not testable here and
 * is not pretended to be — that is `scripts/php-smoke.sh` and a real server (§7).
 */
final class BackupsTest extends TestCase
{
    private string $storage;

    private string $database = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir().'/webx-backups-'.bin2hex(random_bytes(6));
        mkdir($this->storage.'/backups', 0700, true);

        config()->set('filesystems.disks.local', ['driver' => 'local', 'root' => $this->storage]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storage.'/backups/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->storage.'/backups');
        @rmdir($this->storage);
        @unlink($this->database);

        parent::tearDown();
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // A file rather than `:memory:`: the SQLite dump is a copy of the database, and there
        // is nothing to copy when the database is a page of RAM.
        $this->database = sys_get_temp_dir().'/webx-backup-source-'.bin2hex(random_bytes(6)).'.sqlite';
        file_put_contents($this->database, str_repeat('x', 4096));

        // The database here is a file of rubbish, not a schema, so nothing may go looking in
        // it. Testbench caches in memory by default — but a stray `.env` beside its skeleton
        // (running `vendor/bin/testbench` writes one) switches that to the database store, and
        // then the manifest's own locale lookup is a query against this file.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $this->database,
            'prefix' => '',
        ]);
    }

    #[Test]
    public function it_writes_one_gzipped_file_named_after_the_database_and_the_minute(): void
    {
        $snapshot = $this->backups()->take();

        $this->assertFileExists($snapshot->path);
        $this->assertMatchesRegularExpression(
            '/^webx-backup-source-[0-9a-f]+-\d{4}-\d{2}-\d{2}-\d{4}\.sqlite\.gz$/',
            $snapshot->name(),
        );
        $this->assertSame(filesize($snapshot->path), $snapshot->bytes);

        // Gzip, and the real thing: the copy reads back as what went in.
        $this->assertSame(str_repeat('x', 4096), gzdecode((string) file_get_contents($snapshot->path)));
    }

    #[Test]
    public function the_newest_file_is_the_one_the_panel_reports(): void
    {
        $this->snapshotAged('old.sql.gz', 5);
        $this->snapshotAged('newer.sql.gz', 1);

        $this->assertSame('newer.sql.gz', $this->backups()->latest()?->name());
    }

    #[Test]
    public function rotation_removes_what_is_older_than_the_limit_and_keeps_the_rest(): void
    {
        $this->snapshotAged('ancient.sql.gz', 40);
        $this->snapshotAged('old.sql.gz', 31);
        $this->snapshotAged('recent.sql.gz', 3);
        $this->snapshotAged('today.sql.gz', 0);

        $removed = $this->backups()->prune(30);

        sort($removed);
        $this->assertSame(['ancient.sql.gz', 'old.sql.gz'], $removed);
        $this->assertFileExists($this->storage.'/backups/recent.sql.gz');
        $this->assertFileExists($this->storage.'/backups/today.sql.gz');
    }

    /**
     * The one file there is survives whatever the limit says. After a dump this cannot happen
     * — the newest file is a second old — but a directory where the schedule has been broken
     * for a month is exactly where deleting the last copy would be unforgivable.
     */
    #[Test]
    public function rotation_never_removes_the_only_snapshot_there_is(): void
    {
        $this->snapshotAged('lonely.sql.gz', 90);

        $this->assertSame([], $this->backups()->prune(30));
        $this->assertFileExists($this->storage.'/backups/lonely.sql.gz');
    }

    #[Test]
    public function a_failed_dump_leaves_no_half_file_and_touches_nothing_that_was_there(): void
    {
        $this->snapshotAged('yesterday.sql.gz', 1);
        $this->pointAtNothing();

        try {
            $this->backups()->take();
            $this->fail('The dump should have failed.');
        } catch (BackupFailed) {
            // The point is what is left behind, not the message.
        }

        $this->assertSame(['yesterday.sql.gz'], array_map(
            static fn (string $path): string => basename($path),
            glob($this->storage.'/backups/*') ?: [],
        ));
    }

    #[Test]
    public function rotation_only_runs_after_a_dump_has_succeeded(): void
    {
        $this->snapshotAged('ancient.sql.gz', 90);
        $this->snapshotAged('old.sql.gz', 60);
        $this->pointAtNothing();

        $this->artisan('webx:db:backup', ['--keep' => 30])->assertExitCode(1);

        $this->assertFileExists($this->storage.'/backups/ancient.sql.gz');
        $this->assertFileExists($this->storage.'/backups/old.sql.gz');
    }

    #[Test]
    public function the_command_writes_a_snapshot_and_then_rotates(): void
    {
        $this->snapshotAged('ancient.sql.gz', 90);

        $this->artisan('webx:db:backup', ['--keep' => 30])->assertExitCode(0);

        $this->assertFileDoesNotExist($this->storage.'/backups/ancient.sql.gz');
        $this->assertCount(1, glob($this->storage.'/backups/*') ?: []);
    }

    #[Test]
    public function a_disk_that_is_not_local_is_refused(): void
    {
        config()->set('filesystems.disks.local', ['driver' => 's3', 'root' => '']);

        $this->expectException(BackupFailed::class);

        $this->backups()->directory();
    }

    #[Test]
    public function the_throwaway_tables_keep_their_structure_and_lose_their_rows(): void
    {
        $split = Dumper::split(
            ['cache', 'cms_pages', 'jobs', 'sessions'],
            ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'],
        );

        $this->assertSame(['cms_pages'], $split['kept']);
        // `cache_locks` and the rest are not named: this site does not have them, and naming a
        // table that is not there is how the whole dump fails.
        $this->assertSame(['cache', 'jobs', 'sessions'], $split['skipped']);
    }

    /**
     * The flags that were each paid for once: a dump that locks the tables takes the site down
     * with it, `--no-tablespaces` is what lets a shared-hosting user past the first table, and
     * without `utf8mb4` the translated JSON columns come back as mojibake.
     */
    #[Test]
    public function the_mysql_command_asks_for_a_dump_that_does_not_lock_the_site(): void
    {
        $argv = $this->mysqlDumper()->mysqlCommand('/tmp/defaults.cnf');

        $this->assertSame('mysqldump', $argv[0]);
        // It is only read as the first argument; MySQL rejects it anywhere else.
        $this->assertSame('--defaults-extra-file=/tmp/defaults.cnf', $argv[1]);
        $this->assertContains('--single-transaction', $argv);
        $this->assertContains('--quick', $argv);
        $this->assertContains('--no-tablespaces', $argv);
        $this->assertContains('--default-character-set=utf8mb4', $argv);
        $this->assertSame('shop', end($argv));
        $this->assertNotContains('--no-data', $argv);
    }

    #[Test]
    public function the_column_statistics_flag_is_a_setting_and_not_a_guess(): void
    {
        $this->assertNotContains('--skip-column-statistics', $this->mysqlDumper()->mysqlCommand('x'));

        $off = $this->mysqlDumper(['column_statistics' => false])->mysqlCommand('x');
        $this->assertContains('--skip-column-statistics', $off);
    }

    #[Test]
    public function extra_flags_reach_the_tool_as_a_list_or_as_a_line(): void
    {
        $this->assertContains(
            '--ssl-verify-server-cert=0',
            $this->mysqlDumper(['options' => ['--ssl-verify-server-cert=0']])->mysqlCommand('x'),
        );

        // What an environment variable can carry, because it cannot carry a list. A container is
        // where this comes up: the client in the image is not the server it is dumping.
        $line = $this->mysqlDumper(['options' => '  --ssl-verify-server-cert=0   --set-gtid-purged=OFF '])
            ->mysqlCommand('x');

        $this->assertContains('--ssl-verify-server-cert=0', $line);
        $this->assertContains('--set-gtid-purged=OFF', $line);
        $this->assertNotContains('', $line);
    }

    #[Test]
    public function the_password_is_in_a_file_and_never_in_an_argument(): void
    {
        $dumper = $this->mysqlDumper();
        $defaults = $dumper->mysqlDefaultsFile();

        try {
            $contents = (string) file_get_contents($defaults);

            $this->assertStringContainsString('[client]', $contents);
            $this->assertStringContainsString('password="s3cr#t pass"', $contents);

            foreach ($dumper->mysqlCommand($defaults) as $argument) {
                $this->assertStringNotContainsString('s3cr#t', $argument);
            }
        } finally {
            @unlink($defaults);
        }
    }

    #[Test]
    public function the_manifest_carries_the_newest_snapshot(): void
    {
        $snapshot = $this->backups()->take();
        $manifest = $this->get('/api/cms/manifest')->json('data');

        $this->assertSame(
            $snapshot->takenAt->format(DATE_ATOM),
            $manifest['backup']['at'],
        );
        $this->assertSame($snapshot->bytes, $manifest['backup']['bytes']);
    }

    #[Test]
    public function an_empty_directory_is_reported_rather_than_hidden(): void
    {
        $manifest = $this->get('/api/cms/manifest')->json('data');

        $this->assertNull($manifest['backup']['at']);
        $this->assertNull($manifest['backup']['bytes']);
    }

    /** A site that has switched the dump off is not nagged about not having one. */
    #[Test]
    public function a_site_with_the_backup_switched_off_says_nothing(): void
    {
        config()->set('webx-admin.backup.enabled', false);

        $this->assertNull($this->get('/api/cms/manifest')->json('data.backup'));
    }

    /** Repoints the default connection at a file that is not there, the way a moved volume would. */
    private function pointAtNothing(): void
    {
        config()->set('database.connections.sqlite.database', $this->storage.'/not-a-database.sqlite');
        // The manager caches connections, and a connection remembers the name it was made
        // with: without this the dump happily copies the old file.
        DB::purge('sqlite');
    }

    private function backups(): Backups
    {
        return $this->app->make(Backups::class);
    }

    /**
     * A MySQL connection nobody connects to: the PDO is lazy, so the configuration, the driver
     * name and the database name are all readable without a server behind them.
     *
     * @param  array<string, mixed>  $settings
     */
    private function mysqlDumper(array $settings = []): Dumper
    {
        config()->set('database.connections.dump-test', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'shop',
            'username' => 'shop_user',
            'password' => 's3cr#t pass',
            'prefix' => '',
        ]);

        $connection = DB::connection('dump-test');
        $this->assertInstanceOf(Connection::class, $connection);

        return new Dumper($connection, $settings);
    }

    private function snapshotAged(string $name, int $days): void
    {
        $path = $this->storage.'/backups/'.$name;

        file_put_contents($path, gzencode('-- a dump'));
        touch($path, time() - $days * 86400 - 60);
    }
}
