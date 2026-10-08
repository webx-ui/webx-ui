<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Checks\Snapshots;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Snapshots\Manifest;
use WebxUi\Admin\Snapshots\MediaDisk;
use WebxUi\Admin\Snapshots\Restorer;
use WebxUi\Admin\Snapshots\SnapshotFailed;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Admin\Snapshots\Snapshotter;
use WebxUi\Admin\Snapshots\Tar;
use WebxUi\Admin\Snapshots\UrlRewriter;

/**
 * `webx:snapshot` and `webx:snapshot:restore` on SQLite: what travels, what stays, and every
 * refusal coming before anything is written.
 */
final class SnapshotsTest extends TestCase
{
    private string $dir;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->dir = sys_get_temp_dir().'/webx-snapshots-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/public', 0777, true);
        mkdir($this->dir.'/www', 0777, true);

        // A restore runs `storage:link` when the link is missing; Testbench's own public folder is
        // shared by every package's tests, and a link left there changes what they see.
        $app->usePublicPath($this->dir.'/www');

        $app['config']->set('app.name', 'Demo Site');
        $app['config']->set('app.url', 'http://demo.local');
        $app['config']->set('filesystems.disks.public', ['driver' => 'local', 'root' => $this->dir.'/public']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('demo_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
        });

        Schema::create('demo_enquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained('demo_pages');
            $table->string('email');
        });

        Schema::create('demo_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('demo_bundles', function (Blueprint $table): void {
            $table->id();
            $table->string('css');
        });

        $this->app->make(SnapshotTables::class)
            ->content('demo_pages')
            ->stand('demo_enquiries')
            ->admins('demo_admins')
            ->derived('demo_bundles');
    }

    protected function tearDown(): void
    {
        $link = $this->dir.'/www/storage';

        if (is_link($link)) {
            @unlink($link) || @rmdir($link);
        }

        MediaDisk::deleteDirectory($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function content_goes_round_and_the_stands_own_rows_stay_put(): void
    {
        DB::table('demo_pages')->insert(['id' => 1, 'title' => 'About', 'body' => 'See http://demo.local/contact', 'data' => '{"link":"http:\/\/demo.local\/x"}']);
        DB::table('demo_enquiries')->insert(['page_id' => 1, 'email' => 'local@example.com']);
        DB::table('demo_admins')->insert(['name' => 'Anna']);

        $archive = $this->snapshot();
        $manifest = $this->manifest($archive);

        $this->assertSame('content', $manifest->tables()['demo_pages']['group']);
        $this->assertArrayNotHasKey('demo_enquiries', $manifest->tables());
        $this->assertArrayNotHasKey('demo_admins', $manifest->tables());
        $this->assertArrayNotHasKey('migrations', $manifest->tables());
        $this->assertArrayNotHasKey('demo_bundles', $manifest->tables());
        $this->assertContains('2026_10_05_000000_rename_the_frame_tables_to_cms', $manifest->migrations());

        // Life on the target goes on: content edited, an enquiry arrives, a bundle is glued.
        DB::table('demo_pages')->where('id', 1)->update(['title' => 'Changed']);
        DB::table('demo_pages')->insert(['id' => 2, 'title' => 'Only here']);
        DB::table('demo_enquiries')->insert(['page_id' => 2, 'email' => 'dev@example.com']);
        DB::table('demo_admins')->insert(['name' => 'Boris']);
        DB::table('demo_bundles')->insert(['css' => 'old']);

        $this->restore($archive)
            ->expectsOutputToContain('1 demo_enquiries rows point at demo_pages')
            ->assertSuccessful();

        $this->assertSame(['About'], DB::table('demo_pages')->pluck('title')->all());
        $this->assertSame(['local@example.com', 'dev@example.com'], DB::table('demo_enquiries')->pluck('email')->all());
        $this->assertSame(['Anna', 'Boris'], DB::table('demo_admins')->pluck('name')->all());
        $this->assertSame(0, DB::table('demo_bundles')->count());
    }

    #[Test]
    public function the_admins_and_the_stand_travel_only_when_asked(): void
    {
        DB::table('demo_admins')->insert(['name' => 'Anna']);
        DB::table('demo_enquiries')->insert(['email' => 'a@example.com']);

        $admins = $this->manifest($this->snapshot(['--with-admins' => true]))->tables();
        $this->assertArrayHasKey('demo_admins', $admins);
        $this->assertArrayNotHasKey('demo_enquiries', $admins);

        $all = $this->snapshot(['--all' => true]);
        $tables = $this->manifest($all)->tables();
        $this->assertArrayHasKey('demo_enquiries', $tables);
        $this->assertArrayNotHasKey('migrations', $tables);

        // An archive with everything still replaces only the content unless told otherwise.
        DB::table('demo_admins')->insert(['name' => 'Boris']);
        DB::table('demo_enquiries')->insert(['email' => 'b@example.com']);

        $this->restore($all)->assertSuccessful();
        $this->assertSame(2, DB::table('demo_admins')->count());
        $this->assertSame(2, DB::table('demo_enquiries')->count());

        $this->restore($all, ['--with-admins' => true])->assertSuccessful();
        $this->assertSame(['Anna'], DB::table('demo_admins')->pluck('name')->all());
        $this->assertSame(2, DB::table('demo_enquiries')->count());

        $this->restore($all, ['--all' => true])->assertSuccessful();
        $this->assertSame(['a@example.com'], DB::table('demo_enquiries')->pluck('email')->all());
    }

    #[Test]
    public function an_undeclared_table_is_named_and_left_out(): void
    {
        Schema::create('shop_things', function (Blueprint $table): void {
            $table->id();
        });

        $this->artisan('webx:snapshot', ['--output' => $this->dir.'/a.tar.gz', '--no-media' => true])
            ->expectsOutputToContain('shop_things')
            ->assertSuccessful();

        $this->assertArrayNotHasKey('shop_things', $this->manifest($this->dir.'/a.tar.gz')->tables());

        config()->set('webx-admin.snapshot.tables', ['content' => ['shop_*']]);
        $this->assertArrayHasKey('shop_things', $this->manifest($this->snapshot())->tables());
    }

    #[Test]
    public function migrations_this_code_does_not_know_are_refused_before_anything_is_touched(): void
    {
        DB::table('demo_pages')->insert(['title' => 'About']);
        $archive = $this->repack($this->snapshot(), static function (array $manifest): array {
            $manifest['migrations'][] = '2099_01_01_000000_from_the_future';

            return $manifest;
        });
        DB::table('demo_pages')->update(['title' => 'Changed']);

        $this->restore($archive)
            ->expectsOutputToContain('Deploy the code first')
            ->assertFailed();

        $this->assertSame('Changed', DB::table('demo_pages')->value('title'));
    }

    #[Test]
    public function an_older_archive_goes_into_the_newer_tables_column_by_column(): void
    {
        DB::table('demo_pages')->insert(['title' => 'About']);
        $archive = $this->repack($this->snapshot(), static function (array $manifest): array {
            array_pop($manifest['migrations']);

            return $manifest;
        });

        Schema::table('demo_pages', function (Blueprint $table): void {
            $table->dropColumn('body');
        });

        $this->restore($archive)
            ->expectsOutputToContain('older than this stand by 1 migrations')
            ->expectsOutputToContain('demo_pages: the archive\'s body no longer exists here')
            ->assertSuccessful();

        $this->assertSame('About', DB::table('demo_pages')->value('title'));
    }

    #[Test]
    public function an_archive_from_a_newer_patch_is_read_and_an_unknown_version_is_refused(): void
    {
        $archive = $this->snapshot();

        $patch = $this->repack($archive, static fn (array $manifest): array => ['version' => '1.0.9', 'extra' => true] + $manifest);
        $this->restore($patch)->assertSuccessful();

        $minor = $this->repack($archive, static fn (array $manifest): array => ['version' => '1.1.0'] + $manifest);
        $this->restore($minor)->expectsOutputToContain('format [webx-snapshot 1.1.0]')->assertFailed();

        $this->assertFalse(Manifest::readable('2.0.0'));
        $this->assertFalse(Manifest::readable('1.0'));
    }

    #[Test]
    public function production_refuses_without_force_and_nobody_is_restored_without_a_yes(): void
    {
        DB::table('demo_pages')->insert(['title' => 'About']);
        $archive = $this->snapshot();
        DB::table('demo_pages')->update(['title' => 'Changed']);

        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->artisan('webx:snapshot:restore', ['archive' => $archive, '--no-backup' => true, '--no-interaction' => true])
            ->expectsOutputToContain('This stand is production')
            ->assertFailed();

        $this->artisan('webx:snapshot:restore', ['archive' => $archive, '--force' => true, '--no-backup' => true])
            ->expectsConfirmation('Replace the content of this stand with the archive\'s?', 'no')
            ->assertFailed();

        $this->app->detectEnvironment(static fn (): string => 'local');
        $this->artisan('webx:snapshot:restore', ['archive' => $archive, '--no-backup' => true, '--no-interaction' => true])
            ->expectsOutputToContain('pass --force')
            ->assertFailed();

        $this->assertSame('Changed', DB::table('demo_pages')->value('title'));

        $this->restore($archive, ['--force' => true])->assertSuccessful();
        $this->assertSame('About', DB::table('demo_pages')->value('title'));
    }

    #[Test]
    public function a_failed_rollback_dump_stops_the_restore(): void
    {
        DB::table('demo_pages')->insert(['title' => 'About']);
        $archive = $this->snapshot();
        DB::table('demo_pages')->update(['title' => 'Changed']);

        // An in-memory SQLite database has no file for the dump to copy.
        $this->artisan('webx:snapshot:restore', ['archive' => $archive, '--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain('The rollback dump failed')
            ->assertFailed();

        $this->assertSame('Changed', DB::table('demo_pages')->value('title'));
    }

    #[Test]
    public function addresses_of_the_source_stand_become_this_stands(): void
    {
        DB::table('demo_pages')->insert([
            'title' => 'About',
            'body' => 'http://demo.local/a, //demo.local:8000/b, http://demo.localhost/c, mail@demo.local',
            'data' => '{"link":"http:\/\/demo.local\/x"}',
        ]);
        $archive = $this->snapshot();

        config()->set('app.url', 'https://dev.example.com');
        $this->restore($archive)->expectsOutputToContain('http://demo.local → https://dev.example.com')->assertSuccessful();

        $row = DB::table('demo_pages')->first();
        $this->assertSame('https://dev.example.com/a, //dev.example.com/b, http://demo.localhost/c, mail@demo.local', $row->body);
        $this->assertSame('{"link":"https:\/\/dev.example.com\/x"}', $row->data);

        $same = new UrlRewriter('https://a.test', 'https://a.test');
        $this->assertFalse($same->active());
    }

    #[Test]
    public function media_is_mirrored_by_default_and_added_to_with_keep_extra(): void
    {
        $public = $this->dir.'/public';
        $this->write($public.'/media/a.jpg', 'A');
        $this->write($public.'/catalog/0/1/b.jpg', 'B');
        $this->write($public.'/media/thumbs/a/320.webp', 'preview');
        $this->write($public.'/.gitignore', '*');

        $archive = $this->snapshot([], media: true);
        $manifest = $this->manifest($archive);
        $this->assertSame(['catalog/0/1/b.jpg', 'media/a.jpg'], array_keys($manifest->mediaFiles()));
        $this->assertSame(hash('sha256', 'A'), $manifest->mediaFiles()['media/a.jpg']);

        $this->write($public.'/media/a.jpg', 'A, edited');
        $this->write($public.'/media/extra.jpg', 'E');

        $this->restore($archive, ['--keep-extra' => true])->assertSuccessful();
        $this->assertSame('A', file_get_contents($public.'/media/a.jpg'));
        $this->assertFileExists($public.'/media/extra.jpg');
        $this->assertDirectoryDoesNotExist($public.'/media/thumbs', 'a preview of a replaced picture is a preview of the wrong picture');

        $this->restore($archive)->assertSuccessful();
        $this->assertFileDoesNotExist($public.'/media/extra.jpg');
        $this->assertSame('B', file_get_contents($public.'/catalog/0/1/b.jpg'));
        $this->assertFileExists($public.'/.gitignore');

        $this->restore($archive, ['--no-media' => true])->expectsOutputToContain('left as they are')->assertSuccessful();
    }

    #[Test]
    public function a_damaged_file_is_refused_before_anything_changes(): void
    {
        $this->write($this->dir.'/public/media/a.jpg', 'A');
        DB::table('demo_pages')->insert(['title' => 'About']);
        $archive = $this->repack($this->snapshot([], media: true), static function (array $manifest): array {
            $manifest['media']['files']['media/a.jpg'] = hash('sha256', 'something else');

            return $manifest;
        });
        DB::table('demo_pages')->update(['title' => 'Changed']);

        $this->restore($archive)->expectsOutputToContain('does not match the hash')->assertFailed();
        $this->assertSame('Changed', DB::table('demo_pages')->value('title'));
    }

    #[Test]
    public function rows_that_point_at_content_that_went_are_reported_not_deleted(): void
    {
        $archive = $this->snapshot();
        DB::table('demo_pages')->insert(['id' => 5, 'title' => 'Only here']);
        DB::table('demo_enquiries')->insert(['page_id' => 5, 'email' => 'dev@example.com']);

        $this->restore($archive)
            ->expectsOutputToContain('1 demo_enquiries rows point at demo_pages that no longer exist (demo_enquiries.page_id)')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('demo_enquiries')->count());
    }

    #[Test]
    public function the_doctor_mentions_archives_left_lying_in_storage(): void
    {
        $directory = $this->app->make(Snapshotter::class)->directory();
        $check = $this->app->make(Snapshots::class);
        $this->assertCount(0, $check->run());

        $this->write($directory.'/old.tar.gz', 'x');

        try {
            $this->assertSame(Diagnosis::OK, $check->run()[0]->state);

            touch($directory.'/old.tar.gz', time() - 30 * 86400);
            $this->assertSame(Diagnosis::WARN, $check->run()[0]->state);
        } finally {
            unlink($directory.'/old.tar.gz');
        }
    }

    #[Test]
    public function a_path_out_of_the_media_folder_is_not_a_path(): void
    {
        $this->assertTrue(Restorer::safe('media/a b/c.jpg'));
        $this->assertFalse(Restorer::safe('../.env'));
        $this->assertFalse(Restorer::safe('media/../../.env'));
        $this->assertFalse(Restorer::safe('/etc/passwd'));
        $this->assertFalse(Restorer::safe('C:/Windows/x'));
    }

    #[Test]
    public function a_long_name_survives_the_archive(): void
    {
        $name = 'media/'.str_repeat('folder/', 20).'picture.jpg';
        $tar = Tar::create($this->dir.'/long.tar.gz');
        $tar->addString($name, 'contents');
        $tar->close();

        foreach (Tar::read($this->dir.'/long.tar.gz') as $read => $entry) {
            $this->assertSame($name, $read);
            $this->assertSame('contents', $entry->contents());
        }

        $this->expectException(SnapshotFailed::class);
        file_put_contents($this->dir.'/cut.tar.gz', substr((string) file_get_contents($this->dir.'/long.tar.gz'), 0, 40));
        iterator_to_array(Tar::read($this->dir.'/cut.tar.gz'));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function snapshot(array $options = [], bool $media = false): string
    {
        $path = $this->dir.'/snapshot-'.bin2hex(random_bytes(3)).'.tar.gz';

        $this->artisan('webx:snapshot', ['--output' => $path, '--no-media' => ! $media] + $options)->assertSuccessful();

        return $path;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function restore(string $archive, array $options = []): PendingCommand
    {
        /** @var PendingCommand */
        return $this->artisan('webx:snapshot:restore', ['archive' => $archive, '--no-backup' => true, '--force' => true, '--no-interaction' => true] + $options);
    }

    private function manifest(string $archive): Manifest
    {
        foreach (Tar::read($archive) as $name => $entry) {
            return Manifest::fromJson($entry->contents(), $archive);
        }

        $this->fail('Empty archive.');
    }

    /**
     * The same archive with its manifest changed — what an archive from another version is.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function repack(string $archive, callable $change): string
    {
        $path = $this->dir.'/repacked-'.bin2hex(random_bytes(3)).'.tar.gz';
        $tar = Tar::create($path);

        foreach (Tar::read($archive) as $name => $entry) {
            $contents = $entry->contents();

            if ($name === Manifest::NAME) {
                $contents = (string) json_encode($change(json_decode($contents, true)));
            }

            $tar->addString($name, $contents);
        }

        $tar->close();

        return $path;
    }

    private function write(string $path, string $contents): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }
}
