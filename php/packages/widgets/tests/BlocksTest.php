<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The blocks `gallery` and `logos` (§15.1): offered to a site with blocks and a media library,
 * installed and published by the command `webx:setup` runs, and printed with the slider and the
 * lightbox inside — pictures of the library with their sizes, one group per block.
 */
final class BlocksTest extends TestCase
{
    /** Each render a block of its own key, as on a page: k1, k2… */
    private int $rendered = 0;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
            MediaServiceProvider::class,
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['widgets']])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    #[Test]
    public function both_are_installed_and_published_on_their_samples(): void
    {
        foreach (['gallery', 'logos'] as $slug) {
            $block = Block::query()->where('slug', $slug)->with('publishedVersion')->firstOrFail();

            $this->assertSame('Offered by widgets', $block->publishedVersion?->comment, "{$slug} is published — it draws on its sample");
        }
    }

    #[Test]
    public function the_grid_links_every_picture_to_the_lightbox_of_its_block(): void
    {
        $pictures = [...array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path, 'alt' => "Photo {$n}"], [1, 2, 3]), ['path' => 'media/gone.webp']];
        $pictures[0]['title'] = 'Under the first';

        $html = $this->render('gallery', ['heading' => 'Work', 'pictures' => $pictures, 'columns' => 4]);

        $this->assertStringContainsString('class="b-gallery b-gallery--grid"', $html, 'a grid unless told otherwise');
        $this->assertStringContainsString('--gallery-columns: 4', $html);
        $this->assertSame(3, substr_count($html, 'class="b-gallery__cell"'), 'a picture gone from the library is left out');
        $this->assertSame(3, substr_count($html, 'data-webx-lightbox="gallery-k1"'), 'zoom is on unless switched off, one group for the block');
        $this->assertStringContainsString('data-width="1800" data-height="1200"', $html, 'the sizes stored at upload');
        $this->assertStringContainsString('<figcaption class="b-gallery__caption">Under the first</figcaption>', $html);
        $this->assertStringContainsString('alt="Photo 2"', $html);
        $this->assertStringContainsString('<h2 class="b-gallery__heading">Work</h2>', $html);
        $this->assertContains('lightbox', Widgets::claimed());

        $plain = $this->render('gallery', ['pictures' => $pictures, 'zoom' => false]);
        $this->assertStringNotContainsString('data-webx-lightbox', $plain);
        $this->assertSame(3, substr_count($plain, 'class="b-gallery__image"'));
    }

    #[Test]
    public function the_slider_is_the_gallery_variant_with_a_thumbnail_and_a_link_per_slide(): void
    {
        $pictures = array_map(fn (int $n): array => ['path' => $this->picture("photo-{$n}")->path], [1, 2, 3, 4]);

        $html = $this->render('gallery', ['heading' => 'Work', 'pictures' => $pictures, 'view' => 'slider']);

        $this->assertStringContainsString('webx-slider--gallery', $html);
        $this->assertStringContainsString('aria-label="Work"', $html, 'the slider is named by the heading');
        $this->assertSame(4, substr_count($html, 'class="webx-slider__slide"'));
        $this->assertSame(4, substr_count($html, 'data-thumb="'));
        $this->assertSame(4, substr_count($html, 'data-webx-lightbox="gallery-k1"'));
        $this->assertContains('slider', Widgets::claimed());

        // Two galleries on a page page through their own pictures.
        $this->assertStringContainsString('data-webx-lightbox="gallery-k2"', $this->render('gallery', ['pictures' => $pictures, 'view' => 'slider']));
    }

    #[Test]
    public function a_gallery_with_nothing_to_show_prints_nothing(): void
    {
        $this->assertStringNotContainsString('b-gallery', $this->render('gallery', ['heading' => 'Work', 'pictures' => []]));
    }

    #[Test]
    public function the_logos_run_in_the_strip_each_a_link_when_it_has_one(): void
    {
        $logo = $this->picture('logo-1', 'image/svg+xml');

        $html = $this->render('logos', ['heading' => 'Clients', 'logos' => [
            ['name' => 'Strategy Co.', 'logo' => ['path' => $logo->path], 'link' => ['target' => 'url', 'url' => 'https://strategy.example', 'new_tab' => true]],
            ['name' => 'Design Co.', 'logo' => null, 'link' => null],
            ['name' => '', 'logo' => ['path' => $logo->path, 'alt' => 'From the library'], 'link' => null],
            ['name' => '  ', 'logo' => null, 'link' => null],
        ]]);

        $this->assertStringContainsString('webx-slider--logos', $html);
        $this->assertStringContainsString('webx-slider__pause', $html, 'it moves on its own, so it has a pause button');
        $this->assertSame(3, substr_count($html, 'class="webx-slider__slide"'), 'a row with nothing to show is left out');
        $this->assertMatchesRegularExpression('#<a class="b-logos__item" href="https://strategy.example"\s+target="_blank"\s+rel="noopener noreferrer"\s*>#', $html);
        $this->assertStringContainsString('alt="Strategy Co."', $html);
        $this->assertStringContainsString('<span class="b-logos__item">', $html);
        $this->assertStringContainsString('<span class="b-logos__name">Design Co.</span>', $html);
        $this->assertStringContainsString('alt="From the library"', $html, 'no name: the picture says it');
    }

    /** A picture in the library, the way an upload leaves one — the bytes need not be there. */
    private function picture(string $name, string $mime = 'image/webp'): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
        $extension = $mime === 'image/svg+xml' ? 'svg' : 'webp';

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => "media/ab/cd/{$name}.{$extension}",
            'hash' => md5($name),
            'name' => $name,
            'file_name' => "{$name}.{$extension}",
            'extension' => $extension,
            'mime' => $mime,
            'size' => 2048,
            'width' => $extension === 'svg' ? null : 1800,
            'height' => $extension === 'svg' ? null : 1200,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function render(string $type, array $values): string
    {
        $this->rendered++;

        return (string) $this->app->make(Renderer::class)->render([['key' => "k{$this->rendered}", 'type' => $type, 'values' => $values]]);
    }
}
