<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\FirstScreen;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The hero's picture of the library on a site that cuts its copies in AVIF and WebP (WIDGETS
 * §14.2): `<picture>` with a source per format and the ladder of widths, the picture for the phone
 * before them, and the first screen's picture preloaded from the head.
 */
final class HeroPictureTest extends TestCase
{
    use MigratesOnce;

    private const string NAME = 'webx-ui/theme-default';

    /** The ladder of a site of photos on the whole screen, beside the panel's previews. */
    private const array WIDTHS = [160, 320, 480, 768, 1080, 1440, 1920, 2560];

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ThemeServiceProvider::class,
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
            WidgetsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-themes.theme', self::NAME);
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
        $app['config']->set('webx-media.thumbs.widths', self::WIDTHS);
        $app->resolving(ThemeLocator::class, function (ThemeLocator $locator): void {
            $locator->register(self::NAME, str_replace('\\', '/', dirname(__DIR__)));
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function without_formats_the_hero_prints_its_picture_as_it_did(): void
    {
        $coast = $this->picture('coast', 'jpg', 2560, 1440);
        $this->cut('coast', 'avif', [480, 768, 1080, 1440, 1920, 2560]);

        $html = $this->hero($coast);

        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringNotContainsString('srcset', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertSame('', trim(View::yieldPushContent('head')));
    }

    #[Test]
    public function the_first_hero_prints_avif_and_webp_by_width_and_preloads_the_avif(): void
    {
        config(['webx-media.thumbs.formats' => ['avif', 'webp']]);
        $coast = $this->picture('coast', 'jpg', 2560, 1440);
        $this->cut('coast', 'avif', [480, 768, 1080, 1440, 1920, 2560]);
        $this->cut('coast', 'webp', [480, 768, 1080, 1440, 1920, 2560]);
        $this->cut('coast', 'jpg', [480, 768, 1080, 1440, 1920]);

        $html = $this->hero($coast);

        $this->assertMatchesRegularExpression('~<picture class="webx-picture" style="display: contents"><source type="image/avif" srcset="[^"]+/media/thumbs/coast/480\.avif\?v=\w+ 480w, [^"]+/768\.avif\?v=\w+ 768w, [^"]+/1080\.avif\?v=\w+ 1080w, [^"]+/1440\.avif\?v=\w+ 1440w, [^"]+/1920\.avif\?v=\w+ 1920w, [^"]+/2560\.avif\?v=\w+ 2560w" sizes="100vw"><source type="image/webp" srcset="[^"]+/480\.webp\?v=\w+ 480w, [^"]+ 768w, [^"]+ 1080w, [^"]+ 1440w, [^"]+ 1920w, [^"]+/2560\.webp\?v=\w+ 2560w" sizes="100vw"><img class="b-hero__picture" src="[^"]+/coast\.jpg\?v=\w+" alt="A coast at dawn"\s+width="2560" height="1440"\s+fetchpriority="high" srcset="[^"]+/480\.jpg\?v=\w+ 480w, [^"]+ 768w, [^"]+ 1080w, [^"]+ 1440w, [^"]+ 1920w, [^"]+/coast\.jpg\?v=\w+ 2560w" sizes="100vw"></picture>~', $html);

        // From the head, before the stylesheet says where the picture stands: the best format at the width the screen needs.
        $head = trim(View::yieldPushContent('head'));
        $this->assertMatchesRegularExpression('~^<link rel="preload" as="image" type="image/avif" imagesrcset="[^"]+/480\.avif\?v=\w+ 480w, [^"]+ 768w, [^"]+ 1080w, [^"]+ 1440w, [^"]+ 1920w, [^"]+/2560\.avif\?v=\w+ 2560w" imagesizes="100vw" fetchpriority="high">$~', $head);
    }

    #[Test]
    public function a_picture_for_the_phone_stands_before_the_others_and_splits_the_preload(): void
    {
        config(['webx-media.thumbs.formats' => ['avif', 'webp']]);
        $coast = $this->picture('coast', 'webp', 2560, 1440);
        $upright = $this->picture('coast-upright', 'webp', 1200, 2133);
        $this->cut('coast', 'avif', [480, 768, 1080, 1440, 1920, 2560]);
        $this->cut('coast-upright', 'avif', [480, 768, 1080, 1200]);
        $this->cut('coast-upright', 'webp', [480, 768, 1080]);

        $html = $this->hero($coast, ['image_mobile' => $upright]);

        // The phone's in AVIF and in its own format, with its own box; then the wide one's. A picture
        // of WebP is the `<img>`'s: no `<source>` of WebP says it twice.
        $this->assertMatchesRegularExpression('~<picture class="webx-picture" style="display: contents"><source media="\(max-width: 768px\)" type="image/avif" srcset="[^"]+/coast-upright/480\.avif\?v=\w+ 480w, [^"]+ 768w, [^"]+ 1080w, [^"]+/1200\.avif\?v=\w+ 1200w" sizes="100vw" width="1200" height="2133"><source media="\(max-width: 768px\)" type="image/webp" srcset="[^"]+/coast-upright/480\.webp\?v=\w+ 480w, [^"]+ 768w, [^"]+ 1080w, [^"]+/coast-upright\.webp\?v=\w+ 1200w" sizes="100vw" width="1200" height="2133"><source type="image/avif" srcset="[^"]+/coast/480\.avif\?v=\w+ 480w, [^"]+ 768w, [^"]+ 1080w, [^"]+ 1440w, [^"]+ 1920w, [^"]+/2560\.avif\?v=\w+ 2560w" sizes="100vw"><img class="b-hero__picture" [^>]+></picture>~', $html);
        $this->assertSame(1, substr_count($html, 'type="image/webp"'));

        $head = trim(View::yieldPushContent('head'));
        $this->assertSame(2, substr_count($head, '<link rel="preload" as="image"'));
        $this->assertMatchesRegularExpression('~type="image/avif" imagesrcset="[^"]+/coast/480\.avif[^"]+" imagesizes="100vw" media="not all and \(max-width: 768px\)" fetchpriority="high">~', $head);
        $this->assertMatchesRegularExpression('~type="image/avif" imagesrcset="[^"]+/coast-upright/480\.avif[^"]+" imagesizes="100vw" media="\(max-width: 768px\)" fetchpriority="high">~', $head);
    }

    #[Test]
    public function a_hero_after_the_first_is_not_preloaded_and_a_copy_not_cut_yet_waits_for_the_next_page(): void
    {
        config(['webx-media.thumbs.formats' => ['avif']]);
        FirstScreen::claim('hero');
        $coast = $this->picture('coast', 'jpg', 2560, 1440);

        $html = $this->hero($coast);

        // Nothing cut yet: no `<source>` to print, and the `<img>` as it was but for its copies.
        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringNotContainsString('fetchpriority', $html);
        $this->assertSame('', trim(View::yieldPushContent('head')));
    }

    /**
     * @param  array<string, mixed>  $picture
     * @param  array<string, mixed>  $values
     */
    private function hero(array $picture, array $values = []): string
    {
        $type = json_decode((string) file_get_contents(dirname(__DIR__).'/blocks/hero.json'), true, 512, JSON_THROW_ON_ERROR);
        $data = [...array_fill_keys(array_column($type['schema'], 'id'), null), ...$type['sample'], 'tone' => 'image', 'width' => 'bleed', 'image' => $picture, ...$values];

        // Inside the page's view, as a block renders: the stacks live until the layout prints its head.
        View::incrementRender();

        try {
            return Blade::render((string) $type['template'], $data, deleteCachedView: true);
        } finally {
            View::decrementRender();
        }
    }

    /**
     * A picture in the library, the way an upload leaves one, as a media field gives it to a block.
     *
     * @return array<string, mixed>
     */
    private function picture(string $name, string $extension, int $width, int $height): array
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
        $file = MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => "media/ab/cd/{$name}.{$extension}",
            'hash' => md5($name),
            'name' => $name,
            'file_name' => "{$name}.{$extension}",
            'extension' => $extension,
            'mime' => $extension === 'jpg' ? 'image/jpeg' : "image/{$extension}",
            'size' => 2048,
            'width' => $width,
            'height' => $height,
        ]);

        return ['url' => Storage::disk('public')->url($file->path).'?v='.substr($file->hash, 0, 8), 'path' => $file->path, 'alt' => 'A coast at dawn', 'width' => $width, 'height' => $height];
    }

    /** @param  list<int>  $widths The copies already cut: the bytes need not be a picture. */
    private function cut(string $name, string $format, array $widths): void
    {
        foreach ($widths as $width) {
            Storage::disk('public')->put("media/thumbs/{$name}/{$width}.{$format}", 'x');
        }
    }
}
