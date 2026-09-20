<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * The pictures inside a `wx-rich-text` document.
 *
 * The type lives in `module-admin`, which has no library; this is the half that gives it one,
 * so the two are only ever true together and the test belongs on this side.
 */
final class RichTextPicturesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function installing_the_library_is_what_teaches_the_panel_where_a_key_lives(): void
    {
        $this->assertTrue($this->app->bound(AssetUrls::class));
    }

    #[Test]
    public function a_document_is_read_with_the_address_the_library_gives_now(): void
    {
        $file = $this->file();

        $stored = '<p>Before</p><p><img src="https://cdn.example.com/old.jpg?v=stale" '
            .'data-wx-path="'.$file->path.'" alt="A cat"></p>';

        $read = $this->type()->resolve($stored, ['type' => 'wx-rich-text']);

        $this->assertIsString($read);
        $this->assertStringContainsString('data-wx-path="'.$file->path.'"', $read, 'the key is the record and stays');
        $this->assertStringNotContainsString('cdn.example.com', $read);
        // The address carries the hash of the file, so an image edited in place — which writes
        // over the same key — comes back with a different one and no CDN serves the old picture.
        $this->assertStringContainsString(substr($file->hash, 0, 8), $read);
    }

    #[Test]
    public function a_document_full_of_one_picture_asks_the_library_once(): void
    {
        $file = $this->file();

        $stored = str_repeat('<p><img src="/old.jpg" data-wx-path="'.$file->path.'"></p>', 12);

        DB::enableQueryLog();
        $this->type()->resolve($stored, ['type' => 'wx-rich-text']);

        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
    }

    private function type(): FieldType
    {
        $type = $this->app->make(FieldTypes::class)->get('wx-rich-text');

        $this->assertNotNull($type);

        return $type;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function file(array $attributes = []): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create($attributes + [
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/one.jpg',
            'hash' => str_repeat('b', 32),
            'name' => 'A picture',
            'file_name' => 'a-picture.jpg',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
        ]);
    }
}
