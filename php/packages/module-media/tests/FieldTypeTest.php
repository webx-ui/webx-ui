<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFieldType;
use WebxUi\Media\Screens\MediaFiles;

/**
 * `wx-media` and `wx-file` on the server: what a screen — or a block — stores for one file, and
 * what it hands back.
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
    public function the_site_reads_everything_it_would_need_a_library_for(): void
    {
        // A template has nothing to reach the library with: what is not handed over here — the
        // size of a picture, the size of a download — cannot be worked out on the page at all.
        $file = $this->file();

        $resolved = $this->field()->resolve(['path' => $file->path], ['type' => 'wx-media']);

        $this->assertIsArray($resolved);
        $this->assertSame('A picture', $resolved['name']);
        $this->assertSame('jpg', $resolved['extension']);
        $this->assertSame('image/jpeg', $resolved['mime']);
        $this->assertSame(1024, $resolved['size']);
        $this->assertSame(800, $resolved['width']);
        $this->assertSame(600, $resolved['height']);
        $this->assertIsString($resolved['thumb']);
    }

    #[Test]
    public function a_document_has_no_preview_to_show(): void
    {
        $file = $this->file(['path' => 'media/ab/cd/terms.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf']);

        $resolved = $this->type('wx-file')->resolve(['path' => $file->path], ['type' => 'wx-file']);

        $this->assertIsArray($resolved);
        $this->assertIsString($resolved['url']);
        $this->assertNull($resolved['thumb']);
    }

    #[Test]
    public function a_key_the_library_no_longer_has_resolves_to_no_address(): void
    {
        $resolved = $this->field()->resolve(['path' => 'media/ab/cd/gone.jpg'], ['type' => 'wx-media']);

        $this->assertIsArray($resolved);
        $this->assertNull($resolved['url']);
        // The shape stays the same on purpose: a template that prints a width does not have to
        // ask whether the picture is still there before it can ask how wide it is.
        $this->assertNull($resolved['width']);
        $this->assertArrayHasKey('name', $resolved);
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
        $this->app->make(MediaFiles::class)->flush();
        $field->resolve(['path' => $file->path], ['type' => 'wx-media']);

        $this->assertSame(1, $queries);
    }

    #[Test]
    public function a_key_that_is_not_there_is_not_asked_about_twice_either(): void
    {
        $field = $this->field();
        $field->resolve(['path' => 'media/ab/cd/gone.jpg'], ['type' => 'wx-media']);

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $field->resolve(['path' => 'media/ab/cd/gone.jpg'], ['type' => 'wx-media']);

        $this->assertSame(0, $queries);
    }

    #[Test]
    public function the_address_a_field_drew_a_preview_from_is_not_what_is_kept(): void
    {
        $stored = $this->field()->store(
            ['path' => 'media/ab/cd/one.jpg', 'alt' => 'A cat', 'title' => '', 'url' => 'https://cdn.test/one.jpg'],
            ['type' => 'wx-media'],
        );

        $this->assertSame(['path' => 'media/ab/cd/one.jpg', 'alt' => 'A cat'], $stored);
    }

    #[Test]
    public function a_file_of_the_wrong_kind_is_refused(): void
    {
        $file = $this->file(['path' => 'media/ab/cd/terms.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf']);

        $node = ['type' => 'wx-media', 'props' => ['accept' => 'image']];
        $errors = $this->check($this->field()->rules($node), ['path' => $file->path]);

        $this->assertSame(['This field takes images only.'], $errors);
    }

    #[Test]
    public function a_key_the_library_no_longer_has_is_not_refused(): void
    {
        // One picture deleted from the library must not be what stops a page from being saved:
        // the field draws it as broken, and whoever is editing takes it out.
        $node = ['type' => 'wx-media', 'props' => ['accept' => 'image']];

        $this->assertSame([], $this->check($this->field()->rules($node), ['path' => 'media/ab/cd/gone.jpg']));
    }

    #[Test]
    public function a_field_of_files_says_it_cannot_be_translated(): void
    {
        $node = ['type' => 'wx-media', 'localized' => true];
        $errors = $this->check($this->field()->rules($node), ['path' => 'media/ab/cd/one.jpg']);

        $this->assertSame(
            ['A field of files cannot be translated — the captions inside it are.'],
            $errors,
        );
    }

    /**
     * What a validator makes of one value under these rules.
     *
     * @param  list<mixed>  $rules
     * @return list<string>
     */
    private function check(array $rules, mixed $value): array
    {
        $validator = $this->app->make(ValidationFactory::class)->make(['value' => $value], ['value' => $rules]);

        return $validator->fails() ? array_values($validator->errors()->all()) : [];
    }

    private function field(): MediaFieldType
    {
        $field = $this->type('wx-media');

        $this->assertInstanceOf(MediaFieldType::class, $field);

        return $field;
    }

    private function type(string $name): FieldType
    {
        $field = $this->app->make(FieldTypes::class)->get($name);

        $this->assertInstanceOf(FieldType::class, $field);

        return $field;
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
