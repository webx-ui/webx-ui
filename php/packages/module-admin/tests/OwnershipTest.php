<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Checks\StorageOwner;
use WebxUi\Admin\Snapshots\MediaDisk;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Admin\Support\Ownership;
use WebxUi\Admin\Tests\Fixtures\FakeOwnership;

/**
 * A command run as root (`docker exec`) leaves `storage` to the site's user, and one run as
 * somebody who cannot is turned away before it writes. The `chown` itself is only asserted
 * where the suite runs as root; the decisions are asserted everywhere through a stand-in.
 */
final class OwnershipTest extends TestCase
{
    private string $dir;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->dir = sys_get_temp_dir().'/webx-ownership-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/public', 0777, true);
        $app['config']->set('filesystems.links', []);
        $app['config']->set('filesystems.disks.public', ['driver' => 'local', 'root' => $this->dir.'/public']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('demo_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });

        $this->app->make(SnapshotTables::class)->content('demo_pages');
    }

    protected function tearDown(): void
    {
        MediaDisk::deleteDirectory($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function somebody_who_can_neither_chown_nor_share_the_group_is_turned_away_before_writing(): void
    {
        DB::table('demo_pages')->insert(['title' => 'About']);
        $archive = $this->dir.'/snapshot.tar.gz';
        $this->artisan('webx:snapshot', ['--output' => $archive, '--no-media' => true])->assertSuccessful();
        DB::table('demo_pages')->update(['title' => 'Here']);

        $this->app->instance(Ownership::class, new FakeOwnership($this->dir, euid: 1001, groups: [1001]));

        $this->artisan('webx:snapshot:restore', ['archive' => $archive, '--no-backup' => true, '--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain('docker exec -u www-data')
            ->assertFailed();

        $this->assertSame(['Here'], DB::table('demo_pages')->pluck('title')->all());

        // The owner's group is enough: what it writes is left group-writable.
        $this->app->instance(Ownership::class, new FakeOwnership($this->dir, euid: 1001, groups: [1001, 33]));
        $this->artisan('webx:snapshot:restore', ['archive' => $archive, '--no-backup' => true, '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();
        $this->assertSame(['About'], DB::table('demo_pages')->pluck('title')->all());
    }

    #[Test]
    public function root_and_the_owner_may_write(): void
    {
        $this->assertNull((new FakeOwnership($this->dir, euid: 0, groups: [0]))->refusal());
        $this->assertNull((new FakeOwnership($this->dir, euid: 33, groups: [33]))->refusal());
        $this->assertStringContainsString('run it as www-data', strtolower((string) (new FakeOwnership($this->dir, euid: 1001, groups: []))->refusal()));
    }

    #[Test]
    public function writability_is_read_for_the_web_servers_user_not_ours(): void
    {
        $this->assertTrue(Ownership::allows(0o755, 33, 33, 33, [33]));
        $this->assertFalse(Ownership::allows(0o755, 0, 0, 33, [33]), 'root-owned 755: www-data cannot create a thumbnail');
        $this->assertTrue(Ownership::allows(0o775, 0, 33, 33, [33]));
        $this->assertFalse(Ownership::allows(0o555, 33, 33, 33, [33]));
        $this->assertTrue(Ownership::allows(0o777, 0, 0, 33, [33]));
        $this->assertTrue(Ownership::allows(0o500, 1, 1, 0, [0]));
    }

    #[Test]
    public function the_doctor_names_a_folder_the_web_server_cannot_write_and_strangers_under_storage(): void
    {
        $storage = $this->app->storagePath();
        $fake = new FakeOwnership($storage, euid: 0, groups: [0], closed: ['app/public'], strangers: 372);
        $this->app->instance(Ownership::class, $fake);

        $found = $this->app->make(StorageOwner::class)->run();
        $text = implode("\n", array_map(static fn ($diagnosis): string => $diagnosis->detail, $found));

        $this->assertTrue($found[0]->failed());
        $this->assertStringContainsString('www-data cannot write into storage/app/public', $text);
        $this->assertStringContainsString('372 paths under storage', $text);

        // Not root: whose files are whose is not this process's question.
        $this->app->instance(Ownership::class, new FakeOwnership($storage, euid: 33, groups: [33]));
        $found = $this->app->make(StorageOwner::class)->run();
        $this->assertCount(1, $found);
        $this->assertFalse($found[0]->failed());
    }

    #[Test]
    public function as_root_everything_written_is_handed_back(): void
    {
        $ownership = new Ownership($this->dir);

        if (! $ownership->asRoot()) {
            $this->markTestSkipped('chown needs root (and POSIX).');
        }

        // `nobody` stands in for www-data: storage belongs to it, root unpacks into it.
        chown($this->dir, 65534);
        chgrp($this->dir, 65534);
        mkdir($this->dir.'/public/media/originals', 0755, true);
        file_put_contents($this->dir.'/public/media/originals/a.jpg', 'x');
        symlink($this->dir.'/public', $this->dir.'/storage-link');

        $changed = $ownership->adopt([$this->dir]);

        $this->assertGreaterThanOrEqual(4, $changed);

        foreach (['/public', '/public/media', '/public/media/originals', '/public/media/originals/a.jpg'] as $path) {
            $this->assertSame(65534, fileowner($this->dir.$path), $path);
            $this->assertSame(65534, filegroup($this->dir.$path), $path);
        }

        $this->assertSame(65534, lstat($this->dir.'/storage-link')['uid']);
        $this->assertSame(0, $ownership->strangers($this->dir)['count']);
    }

    #[Test]
    public function several_packages_keep_rows_of_one_table(): void
    {
        $tables = $this->app->make(SnapshotTables::class);
        $tables->preserve('demo_pages', 'title', static fn (): array => ['a']);
        $tables->preserve('demo_pages', 'title', static fn (): array => ['b', 'a']);

        $this->assertSame(['column' => 'title', 'values' => ['a', 'b']], $tables->preservedRows('demo_pages'));
    }
}
