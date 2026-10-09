<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Pages\Models\Page;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * A theme's demo pages name the pictures of its `demo/media/` as `demo:<file name>`, because the
 * key the library gives a file is not known before it is stored: the media demo stores them, and
 * the pages demo puts each key where its name was.
 */
final class DemoPicturesTest extends TestCase
{
    private string $theme;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), MediaServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->theme = sys_get_temp_dir().'/webx-pages-demo-pictures-'.getmypid();
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->theme.'/demo/media');
        $files->ensureDirectoryExists($this->theme.'/demo/blocks');
        $files->ensureDirectoryExists($this->theme.'/demo/pages');
        // Bytes of its own: the same bytes as the module's demo pictures would be that picture.
        $image = imagecreatetruecolor(30, 20);
        imagefilledrectangle($image, 0, 0, 30, 20, (int) imagecolorallocate($image, 40, 120, 200));
        imagejpeg($image, $this->theme.'/demo/media/showcase-photo.jpg');

        $files->put($this->theme.'/demo/blocks/showcase-pictures.json', (string) json_encode([
            'slug' => 'showcase-pictures',
            'title' => 'Showcase pictures',
            'group' => 'content',
            'schema' => [
                ['id' => 'cover', 'type' => 'wx-media', 'label' => 'Cover'],
                ['id' => 'pictures', 'type' => 'wx-gallery', 'label' => 'Pictures'],
            ],
            'template' => '<section class="b-showcase-pictures" data-wx-block="showcase-pictures">@foreach ($pictures as $picture)<img src="{{ $picture[\'url\'] }}" alt="">@endforeach</section>',
            'sample' => ['pictures' => []],
        ]));

        $files->put($this->theme.'/demo/pages/showcase.json', (string) json_encode([
            'title' => 'Showcase',
            'slug' => 'showcase',
            'blocks' => [[
                'key' => 'pictures',
                'type' => 'showcase-pictures',
                'values' => [
                    'cover' => ['path' => 'demo:showcase-photo', 'alt' => 'The cover'],
                    'pictures' => [['path' => 'demo:nowhere'], ['path' => 'demo:showcase-photo', 'alt' => 'One']],
                ],
            ]],
        ]));

        $this->app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('acme/theme', $this->theme, false)]));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->theme);
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function a_picture_named_in_a_page_is_the_one_the_media_demo_stored(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $file = MediaFile::query()->where('name', 'showcase-photo')->firstOrFail();
        $page = Page::query()->get()->first(static fn (Page $page): bool => (string) $page->slug === 'showcase');
        $values = $page?->blocksTree()[0]['values'] ?? [];

        $this->assertSame(['path' => $file->path, 'alt' => 'The cover'], $values['cover'] ?? null);
        $this->assertSame([['path' => $file->path, 'alt' => 'One']], $values['pictures'] ?? null, 'a name with no picture drops out of the list');

        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();
        $this->assertFalse(MediaFile::query()->where('name', 'showcase-photo')->exists(), 'the theme\'s pictures go with the rest of the demo');
    }
}
