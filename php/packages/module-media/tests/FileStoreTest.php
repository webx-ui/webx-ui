<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Storage\FileStore;

final class FileStoreTest extends TestCase
{
    use RefreshDatabase;

    private FileStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->store = $this->app->make(FileStore::class);
    }

    #[Test]
    public function an_upload_becomes_a_file_on_the_disk_and_a_row(): void
    {
        $file = $this->store->store(UploadedFile::fake()->image('Sofa Oslo.jpg', 800, 600), $this->root());

        Storage::disk('public')->assertExists($file->path);

        $this->assertSame('Sofa Oslo', $file->name);
        $this->assertSame('Sofa Oslo.jpg', $file->file_name);
        $this->assertSame('jpg', $file->extension);
        $this->assertSame(800, $file->width);
        $this->assertSame(600, $file->height);
        $this->assertSame('public', $file->disk);
        $this->assertStringStartsWith('media/', $file->path);
        $this->assertTrue($file->wasRecentlyCreated);
    }

    #[Test]
    public function the_same_bytes_in_the_same_folder_are_the_same_file(): void
    {
        $root = $this->root();

        $first = $this->store->store($this->picture(), $root);
        $second = $this->store->store($this->picture(), $root);

        $this->assertTrue($first->is($second));
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame(1, $root->files()->count());
    }

    #[Test]
    public function the_same_bytes_in_two_folders_are_two_files_with_two_keys(): void
    {
        $folder = new MediaDirectory(['title' => 'Pictures']);
        $folder->appendTo($this->root());

        $here = $this->store->store($this->picture(), $this->root());
        $there = $this->store->store($this->picture(), $folder);

        $this->assertFalse($here->is($there));
        $this->assertNotSame($here->path, $there->path);

        // The reason for the uuid: one of them can go without emptying the other.
        $here->delete();

        Storage::disk('public')->assertMissing($here->path);
        Storage::disk('public')->assertExists($there->path);
    }

    #[Test]
    public function a_key_says_nothing_about_the_folder_it_is_in(): void
    {
        $folder = new MediaDirectory(['title' => 'Pictures']);
        $folder->appendTo($this->root());

        $file = $this->store->store($this->picture(), $this->root());
        $key = $file->path;

        $file->update(['directory_id' => $folder->getKey()]);

        // Moving is a column, not a copy — which on a remote disk is the whole point.
        $this->assertSame($key, $file->refresh()->path);
        Storage::disk('public')->assertExists($key);
    }

    #[Test]
    public function a_copy_is_the_same_bytes_under_a_new_key(): void
    {
        $file = $this->store->store($this->picture(), $this->root());

        $copy = $this->store->copy($file, 'A picture (copy)');

        $this->assertNotSame($file->path, $copy->path);
        $this->assertSame($file->hash, $copy->hash);
        $this->assertSame('A picture (copy)', $copy->name);
        Storage::disk('public')->assertExists($copy->path);
        Storage::disk('public')->assertExists($file->path);
    }

    #[Test]
    public function a_file_without_a_usable_extension_still_lands_somewhere(): void
    {
        $file = $this->store->store(UploadedFile::fake()->create('notes', 10, 'text/plain'), $this->root());

        $this->assertNotSame('', $file->extension);
        $this->assertNull($file->width);
        Storage::disk('public')->assertExists($file->path);
    }

    private function picture(): UploadedFile
    {
        // The same bytes every time: UploadedFile::fake()->image() draws a different picture on
        // each call, and this test is about two uploads of one file.
        return UploadedFile::fake()->createWithContent('picture.jpg', 'the same bytes');
    }

    private function root(): MediaDirectory
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return $root;
    }
}
