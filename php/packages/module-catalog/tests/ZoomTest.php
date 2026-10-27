<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Gallery\Gallery;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;

/**
 * The pictures of a product's page through the widgets' zoom (§10.1, WIDGETS §14, W5.5): a
 * smaller copy shown, the original magnified under the mouse and two fingers, a click opening it
 * over the page with the product's other pictures.
 */
final class ZoomTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function a_picture_is_a_smaller_copy_magnified_into_its_original(): void
    {
        $product = $this->product('Chair', $this->category('chairs'));
        $front = $this->picture($product, 1600, 1200);
        $front->setTranslation('alt', 'en', 'The chair from the front')->setTranslation('title', 'en', 'Oak, oiled')->save();
        $back = $this->picture($product, 800, 600);

        $html = (string) $this->get("/chair-{$product->id}")->assertOk()->getContent();

        // The original is the link and the zoom's large picture; the lightbox pages through the product.
        $this->assertStringContainsString('href="'.$front->url().'" data-webx-zoom="'.$front->url().'" data-zoom-mode="inner" data-zoom-scale="2.5" data-webx-lightbox="product-'.$product->id.'" data-width="1600" data-height="1200" aria-label="Zoom: The chair from the front" class="webx-zoom webx-zoom-link webx-zoom--inner" title="Oak, oiled"', $html);
        // What is shown is the copy 1200 wide, eager for the first picture, lazy for the rest.
        $this->assertMatchesRegularExpression('~<img class="webx-zoom__picture" src="[^"]+/1200\.jpg" alt="The chair from the front"\s+width="1600" height="1200"\s+decoding="async"~', $html);
        $this->assertStringContainsString('aria-label="Zoom: Chair"', $html, 'no alt: the name of the product');
        $this->assertMatchesRegularExpression('~<img class="webx-zoom__picture" src="[^"]+" alt="Chair"\s+width="800" height="600"\s+loading="lazy"~', $html);
        $this->assertSame(2, substr_count($html, 'data-webx-lightbox="product-'.$product->id.'"'));
        $this->assertStringContainsString($back->url(), $html);
    }

    #[Test]
    public function the_config_puts_the_magnified_view_beside_in_the_whole_frame_or_switches_it_off(): void
    {
        $product = $this->product('Chair', $this->category('chairs'));
        $this->picture($product, 800, 600);

        config(['webx-catalog.images.zoom' => 'side']);
        $this->get("/chair-{$product->id}")->assertOk()->assertSee('data-zoom-mode="side"', false);

        config(['webx-catalog.images.zoom' => 'inner', 'webx-catalog.images.zoom_frame' => 'container']);
        $this->get("/chair-{$product->id}")->assertOk()->assertSee('data-zoom-mode="inner" data-zoom-scale="2.5" data-zoom-frame="container"', false);

        config(['webx-catalog.images.zoom_frame' => 'nonsense']);
        $this->get("/chair-{$product->id}")->assertOk()->assertDontSee('data-zoom-frame', false);

        config(['webx-catalog.images.zoom' => 'off']);
        $this->get("/chair-{$product->id}")->assertOk()
            ->assertDontSee('data-webx-zoom', false)
            ->assertSee('alt="Chair"', false);
    }

    private function picture(Product $product, int $width, int $height): ProductImage
    {
        return $this->app->make(Gallery::class)->upload($product, UploadedFile::fake()->image("p{$width}.jpg", $width, $height));
    }
}
