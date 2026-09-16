<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFieldType;

/**
 * `wx-media` on the server: what a screen — or a block — stores, and what it hands back.
 */
final class FieldTypeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_site_reads_the_address_the_field_never_stored(): void
    {
        $file = $this->file();

        $resolved = $this->field()->resolve(['path' => $file->path, 'alt' => 'A cat'], ['type' => 'wx-media']);

        $this->assertIsArray($resolved);
        $this->assertSame('A cat', $resolved['alt']);
        $this->assertIsString($resolved['url']);
        $this->assertStringContainsString($file->path, $resolved['url']);
    }

    #[Test]
    public function a_key_the_library_no_longer_has_resolves_to_no_address(): void
    {
        $resolved = $this->field()->resolve(['path' => 'media/ab/cd/gone.jpg'], ['type' => 'wx-media']);

        $this->assertIsArray($resolved);
        $this->assertNull($resolved['url']);
    }

    #[Test]
    public function the_same_picture_is_looked_up_once_in_one_response(): void
    {
        // A page of blocks prints the same gallery picture as often as it mentions it; one
        // query each would be a query per thumbnail.
        $file = $this->file();
        $field = $this->field();
        $field->resolve(['path' => $file->path], ['type' => 'wx-media']);

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $field->resolve(['path' => $file->path], ['type' => 'wx-media']);
        $field->resolve(['path' => $file->path], ['type' => 'wx-media']);

        $this->assertSame(0, $queries);

        // And what the next response asks about, it asks the database about.
        $field->flush();
        $field->resolve(['path' => $file->path], ['type' => 'wx-media']);

        $this->assertSame(1, $queries);
    }

    private function field(): MediaFieldType
    {
        $field = $this->app->make(FieldTypes::class)->get('wx-media');

        $this->assertInstanceOf(MediaFieldType::class, $field);

        return $field;
    }

    private function file(): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/one.jpg',
            'hash' => str_repeat('a', 32),
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
