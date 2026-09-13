<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

final class SchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_library_has_a_root_from_the_first_migration(): void
    {
        $roots = MediaDirectory::query()->whereNull('parent_id')->get();

        $this->assertCount(1, $roots);
        $this->assertTrue($roots->first()?->isLibraryRoot());
    }

    #[Test]
    public function folders_nest(): void
    {
        $root = $this->root();

        $pictures = new MediaDirectory(['title' => 'Pictures']);
        $pictures->appendTo($root);

        $covers = new MediaDirectory(['title' => 'Covers']);
        $covers->appendTo($pictures);

        $this->assertTrue($covers->refresh()->isDescendantOf($root->refresh()));
        $this->assertSame(2, $covers->getDepth());
        $this->assertFalse($covers->isLibraryRoot());
    }

    #[Test]
    public function a_file_belongs_to_a_folder(): void
    {
        $folder = new MediaDirectory(['title' => 'Pictures']);
        $folder->appendTo($this->root());

        $file = $this->file($folder);

        $this->assertTrue($file->directory->is($folder));
        $this->assertTrue($folder->files()->whereKey($file->getKey())->exists());
        $this->assertTrue($file->isImage());
        $this->assertFalse($file->hasOriginal());
    }

    #[Test]
    public function deleting_a_file_takes_its_bytes_with_it(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/ab/cd/one.jpg', 'bytes');
        Storage::disk('public')->put('media/originals/ab/cd/one.jpg', 'bytes');

        $file = $this->file($this->root(), [
            'path' => 'media/ab/cd/one.jpg',
            'original_path' => 'media/originals/ab/cd/one.jpg',
        ]);

        $file->delete();

        Storage::disk('public')->assertMissing('media/ab/cd/one.jpg');
        // The copy kept before the first edit is this row's too, and nothing else refers to it.
        Storage::disk('public')->assertMissing('media/originals/ab/cd/one.jpg');
    }

    #[Test]
    public function searching_matches_part_of_the_name(): void
    {
        $root = $this->root();
        $this->file($root, ['name' => 'Sofa Oslo', 'path' => 'media/ab/cd/one.jpg']);
        $this->file($root, ['name' => 'Chair Bergen', 'path' => 'media/ab/cd/two.jpg']);

        $found = MediaFile::query()->search('oslo')->get();

        $this->assertCount(1, $found);
        $this->assertSame('Sofa Oslo', $found->first()?->name);
    }

    private function root(): MediaDirectory
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->first();

        $this->assertNotNull($root);

        return $root;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function file(MediaDirectory $directory, array $attributes = []): MediaFile
    {
        return MediaFile::query()->create([
            'directory_id' => $directory->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/'.uniqid().'.jpg',
            'hash' => str_repeat('a', 32),
            'name' => 'A picture',
            'file_name' => 'a-picture.jpg',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
            ...$attributes,
        ]);
    }
}
