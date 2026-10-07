<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Settings\Settings;

/**
 * What the storefront's pages are called when nobody wrote them an SEO card: the product's or
 * the category's own name, short text and picture, through the site's title template — rather
 * than a bare `<title>` the view printed on its own, which missed the template, `og:title` and
 * every picture.
 */
final class SeoFallbackTest extends TestCase
{
    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->save([
            'general.project-name' => ['en' => 'Acme'],
            'seo.title-template' => '{title} — {site}',
        ]);

        $this->laptops = $this->category('laptops');
    }

    #[Test]
    public function a_product_without_a_card_speaks_with_its_name_summary_and_main_picture(): void
    {
        $product = $this->product('ThinkPad', $this->laptops, ['summary' => '<p>A <b>light</b> laptop.</p>']);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => 'catalog/0/1/main.jpg', 'position' => 0]);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => 'catalog/0/1/second.jpg', 'position' => 1]);

        $response = $this->get("/thinkpad-{$product->id}")->assertOk();

        $response->assertSee('<title>ThinkPad — Acme</title>', false);
        $response->assertSee('<meta property="og:title" content="ThinkPad — Acme">', false);
        $response->assertSee('<meta property="og:description" content="A light laptop.">', false);
        $this->assertMatchesRegularExpression('~<meta property="og:image" content="https?://[^"]+/catalog/0/1/main\.jpg">~', (string) $response->getContent());
    }

    #[Test]
    public function the_sites_default_picture_does_not_replace_the_products_own(): void
    {
        $this->defaultPicture();

        $product = $this->product('ThinkPad', $this->laptops);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => 'catalog/0/1/main.jpg', 'position' => 0]);
        $bare = $this->product('Prototype', $this->laptops);

        $own = (string) $this->get("/thinkpad-{$product->id}")->getContent();
        $this->assertStringContainsString('/catalog/0/1/main.jpg"', $own);
        $this->assertStringNotContainsString('default.png', $own);

        // A product with no picture of its own does get the site's.
        $this->assertStringContainsString('/defaults/default.png', (string) $this->get("/prototype-{$bare->id}")->getContent());
    }

    #[Test]
    public function the_trimmed_page_is_named_too_and_stays_out_of_the_index(): void
    {
        $product = $this->product('Old model', $this->laptops);
        $product->update(['is_published' => false]);

        $this->get("/old-model-{$product->id}")
            ->assertOk()
            ->assertSee('<title>Old model — Acme</title>', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    #[Test]
    public function a_category_without_a_card_speaks_with_its_name_description_and_cover(): void
    {
        $this->laptops->forceFill([
            'description' => ['en' => '<p>Every laptop we sell.</p>'],
            'cover_id' => $this->mediaFile('covers/laptops.jpg')->getKey(),
        ])->save();

        $response = $this->get('/laptops')->assertOk();

        $response->assertSee('<title>Laptops — Acme</title>', false);
        $response->assertSee('<meta property="og:title" content="Laptops — Acme">', false);
        $response->assertSee('<meta property="og:description" content="Every laptop we sell.">', false);
        $response->assertSee('/covers/laptops.jpg', false);
    }

    #[Test]
    public function a_filtered_page_does_not_borrow_the_categorys_description(): void
    {
        ColourFacet::migrate();
        $this->app->make(Facets::class)->register(new ColourFacet);
        ColourFacet::paint($this->product('Black one', $this->laptops), 'black');

        $this->laptops->forceFill(['description' => ['en' => 'Every laptop we sell.']])->save();

        // The text is about the whole category, not about its black half — the same reason the
        // category's card is not this page's.
        $this->get('/laptops/colour_black')->assertOk()->assertDontSee('Every laptop we sell.', false);
    }

    #[Test]
    public function the_categorys_own_cover_beats_the_sites_default_picture(): void
    {
        $this->defaultPicture();
        $this->laptops->forceFill(['cover_id' => $this->mediaFile('covers/laptops.jpg')->getKey()])->save();

        $body = (string) $this->get('/laptops')->getContent();

        $this->assertStringContainsString('/covers/laptops.jpg', $body);
        $this->assertStringNotContainsString('default.png', $body);
    }

    #[Test]
    #[DefineEnvironment('withRoot')]
    public function the_root_is_called_by_its_heading(): void
    {
        $this->product('ThinkPad', $this->laptops);

        $this->get('/catalog')->assertOk()->assertSee('<title>Catalogue — Acme</title>', false);
    }

    /**
     * @param  Application  $app
     */
    protected function withRoot($app): void
    {
        $app['config']->set('webx-catalog.root.enabled', true);
    }

    /** Stored the way the panel stores it: a library key, which the setting turns into an address. */
    private function defaultPicture(): void
    {
        $this->mediaFile('defaults/default.png');

        app(Settings::class)->save(['seo.default-og' => ['path' => 'defaults/default.png']]);
    }

    private function mediaFile(string $path): MediaFile
    {
        // Every file belongs to a folder, and the library's migration makes the root one.
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        /** @var MediaFile $file */
        $file = MediaFile::query()->forceCreate([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => md5($path),
            'name' => basename($path),
            'name_lower' => basename($path),
            'file_name' => basename($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'mime' => 'image/jpeg',
            'size' => 2048,
        ]);

        return $file;
    }
}
