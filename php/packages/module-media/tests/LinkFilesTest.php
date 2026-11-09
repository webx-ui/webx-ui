<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\AssetDownloads;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * A `wx-link` to a file of the library.
 *
 * The type lives in `module-admin`, which has no library; this is the half that gives it one, so
 * the two are only ever true together and the test belongs on this side — as with the pictures of
 * a document (`RichTextPicturesTest`).
 */
final class LinkFilesTest extends TestCase
{
    #[Test]
    public function installing_the_library_is_what_lets_a_link_point_at_a_file(): void
    {
        $this->assertTrue($this->app->bound(AssetDownloads::class));
    }

    /**
     * The name to save under is the one the file was uploaded with, extension and all — not the
     * key, which is a hash, and not the name in the grid, which a rename changes.
     */
    #[Test]
    public function a_file_link_is_read_with_the_address_now_and_the_name_it_was_uploaded_under(): void
    {
        $file = $this->file();
        $file->update(['name' => 'Renamed in the grid']);

        /** @var array<string, mixed> $read */
        $read = $this->type()->resolve(['target' => 'file', 'path' => $file->path, 'download' => true], ['type' => 'wx-link']);

        $this->assertStringContainsString('/'.$file->path, (string) $read['url']);
        $this->assertSame('Price list 2026.pdf', $read['download']);
        $this->assertTrue($read['available']);
    }

    #[Test]
    public function a_file_deleted_from_the_library_goes_nowhere(): void
    {
        /** @var array<string, mixed> $read */
        $read = $this->type()->resolve(['target' => 'file', 'path' => 'media/no/ne/gone.pdf', 'download' => true], ['type' => 'wx-link']);

        $this->assertNull($read['url']);
        $this->assertNull($read['download']);
        $this->assertFalse($read['available']);
    }

    private function type(): FieldType
    {
        $type = $this->app->make(FieldTypes::class)->get('wx-link');

        $this->assertNotNull($type);

        return $type;
    }

    private function file(): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/f00d.pdf',
            'hash' => str_repeat('c', 32),
            'name' => 'Price list 2026',
            'file_name' => 'Price list 2026.pdf',
            'extension' => 'pdf',
            'mime' => 'application/pdf',
            'size' => 2048,
        ]);
    }
}
