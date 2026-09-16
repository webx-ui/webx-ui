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

/**
 * `wx-gallery` and `wx-files`: many files under one field name.
 *
 * The element is the one `wx-media` already stored, so what is worth checking here is what only
 * a list has — the order, the number of queries it takes to read it back, and a complaint that
 * says which of the files it is about.
 */
final class ListFieldTypeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function what_is_kept_is_the_keys_and_the_captions_in_order(): void
    {
        $stored = $this->type('wx-gallery')->store([
            ['path' => 'media/ab/cd/two.jpg', 'alt' => 'Second', 'url' => 'https://cdn.test/two.jpg'],
            ['path' => 'media/ab/cd/one.jpg', 'title' => ''],
            ['alt' => 'No file at all'],
        ], ['type' => 'wx-gallery']);

        $this->assertSame([
            ['path' => 'media/ab/cd/two.jpg', 'alt' => 'Second'],
            ['path' => 'media/ab/cd/one.jpg'],
        ], $stored);
    }

    #[Test]
    public function a_gallery_is_read_back_in_one_query(): void
    {
        // The whole point of the type: ten pictures used to be ten round trips, because every
        // value was resolved on its own.
        $files = [$this->file('one.jpg'), $this->file('two.jpg'), $this->file('three.jpg')];

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $resolved = $this->type('wx-gallery')->resolve(
            array_map(static fn (MediaFile $file): array => ['path' => $file->path], $files),
            ['type' => 'wx-gallery'],
        );

        $this->assertSame(1, $queries);
        $this->assertIsArray($resolved);
        $this->assertCount(3, $resolved);
        $this->assertSame($files[1]->path, $resolved[1]['path']);
        $this->assertIsString($resolved[1]['url']);
        $this->assertIsString($resolved[1]['thumb']);
        $this->assertSame(1024, $resolved[0]['size']);
    }

    #[Test]
    public function a_picture_that_is_gone_keeps_its_place(): void
    {
        $file = $this->file('one.jpg');

        $resolved = $this->type('wx-gallery')->resolve([
            ['path' => 'media/ab/cd/gone.jpg'],
            ['path' => $file->path],
        ], ['type' => 'wx-gallery']);

        $this->assertIsArray($resolved);
        $this->assertCount(2, $resolved);
        $this->assertNull($resolved[0]['url']);
        $this->assertIsString($resolved[1]['url']);
    }

    #[Test]
    public function what_is_wrong_says_which_file_it_is_about(): void
    {
        $this->file('one.jpg');
        $this->file('terms.pdf', ['mime' => 'application/pdf', 'extension' => 'pdf']);

        $errors = $this->check($this->type('wx-gallery')->rules(['type' => 'wx-gallery']), [
            ['path' => 'media/ab/cd/one.jpg'],
            ['path' => 'media/ab/cd/terms.pdf'],
        ]);

        $this->assertSame(['File 2: This field takes images only.'], $errors);
    }

    #[Test]
    public function a_gallery_takes_pictures_whatever_its_props_say(): void
    {
        // The name is the promise: a template printing `<img>` over a list that quietly holds a
        // PDF is what this prevents, and `props` is not where that promise lives.
        $this->file('terms.pdf', ['mime' => 'application/pdf', 'extension' => 'pdf']);

        $node = ['type' => 'wx-gallery', 'props' => ['accept' => 'document']];
        $errors = $this->check($this->type('wx-gallery')->rules($node), [['path' => 'media/ab/cd/terms.pdf']]);

        $this->assertSame(['File 1: This field takes images only.'], $errors);
    }

    #[Test]
    public function a_list_of_files_takes_what_its_props_say(): void
    {
        $this->file('terms.pdf', ['mime' => 'application/pdf', 'extension' => 'pdf']);

        $node = ['type' => 'wx-files', 'props' => ['accept' => 'document']];

        $this->assertSame([], $this->check($this->type('wx-files')->rules($node), [['path' => 'media/ab/cd/terms.pdf']]));
    }

    #[Test]
    public function the_limit_the_schema_names_is_the_servers_too(): void
    {
        // The picker stops at the limit as well, but the limit lives in the schema and not in
        // the dialog: a request that never opened one has to meet it too.
        $node = ['type' => 'wx-gallery', 'props' => ['max' => 2]];
        $value = [
            ['path' => 'media/ab/cd/one.jpg'],
            ['path' => 'media/ab/cd/two.jpg'],
            ['path' => 'media/ab/cd/three.jpg'],
        ];

        $this->assertNotSame([], $this->check($this->type('wx-gallery')->rules($node), $value));
        $this->assertSame([], $this->check($this->type('wx-gallery')->rules($node), array_slice($value, 0, 2)));
    }

    #[Test]
    public function a_list_says_it_cannot_be_translated(): void
    {
        $node = ['type' => 'wx-gallery', 'localized' => true];
        $errors = $this->check($this->type('wx-gallery')->rules($node), [['path' => 'media/ab/cd/one.jpg']]);

        $this->assertSame(['A field of files cannot be translated — the captions inside it are.'], $errors);
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<string>
     */
    private function check(array $rules, mixed $value): array
    {
        $validator = $this->app->make(ValidationFactory::class)->make(['value' => $value], ['value' => $rules]);

        return $validator->fails() ? array_values($validator->errors()->all()) : [];
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
    private function file(string $name, array $attributes = []): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create($attributes + [
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/'.$name,
            'hash' => md5($name),
            'name' => $name,
            'file_name' => $name,
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
        ]);
    }
}
