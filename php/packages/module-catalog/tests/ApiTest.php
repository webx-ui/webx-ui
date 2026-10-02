<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;

/**
 * §11.2 and §11.5: the list as Laravel paginates it, the white lists, the three permissions, the
 * gallery and «Deleted».
 */
final class ApiTest extends TestCase
{
    #[Test]
    public function the_list_is_a_paginator_with_the_number_of_products_without_a_category(): void
    {
        $laptops = $this->category('laptops');
        $this->product('Alpha', $laptops, ['sku' => 'A-1', 'priority' => 1]);
        $this->product('Beta', $laptops, ['sku' => 'B-1', 'priority' => 5]);
        $this->product('Loose');

        $editor = $this->editor(['catalog.view']);

        $this->actingAs($editor, 'cms')->getJson($this->api('products'))
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('counts.no_category', 1)
            // Priority first, by default.
            ->assertJsonPath('data.0.name', 'Beta')
            ->assertJsonPath('data.0.visible', true)
            ->assertJsonPath('data.0.category.name', 'Laptops');

        $this->actingAs($editor, 'cms')->getJson($this->api('products?state=no-category'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Loose')
            ->assertJsonPath('data.0.visible', false);

        $this->actingAs($editor, 'cms')->getJson($this->api('products?q=A-1'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.sku', 'A-1');

        $this->actingAs($editor, 'cms')->getJson($this->api('products?sort=name'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha');
    }

    #[Test]
    public function the_list_says_when_the_catalogue_has_outgrown_the_database_engine(): void
    {
        $this->product('Alpha');
        $editor = $this->editor(['catalog.view']);

        $this->actingAs($editor, 'cms')->getJson($this->api('products'))
            ->assertOk()
            ->assertJsonPath('outgrown', null);

        $this->product('Beta');
        config(['webx-catalog.sql_engine_limit' => 1]);

        $this->actingAs($editor, 'cms')->getJson($this->api('products'))
            ->assertOk()
            ->assertJsonPath('outgrown.live', 2)
            ->assertJsonPath('outgrown.limit', 1);
    }

    #[Test]
    public function sorting_and_filtering_are_a_white_list(): void
    {
        $editor = $this->editor(['catalog.view']);

        $this->actingAs($editor, 'cms')->getJson($this->api('products?sort=password'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');

        $this->actingAs($editor, 'cms')->getJson($this->api('products?state=everything'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('state');
    }

    #[Test]
    public function the_three_permissions_divide_reading_writing_and_deleting(): void
    {
        $product = $this->product('Belt', $this->category('belts'));

        $viewer = $this->editor(['catalog.view']);
        $this->actingAs($viewer, 'cms')->getJson($this->api("products/{$product->id}"))->assertOk();
        $this->actingAs($viewer, 'cms')->putJson($this->api("products/{$product->id}"), ['values' => []])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('categories'), ['values' => []])->assertForbidden();

        $manager = $this->editor(['catalog.view', 'catalog.manage']);
        $this->actingAs($manager, 'cms')->putJson($this->api("products/{$product->id}"), ['values' => ['priority' => 2]])->assertOk();
        $this->actingAs($manager, 'cms')->deleteJson($this->api("products/{$product->id}"))->assertForbidden();
        $this->actingAs($manager, 'cms')->getJson($this->api('deleted'))->assertForbidden();

        $deleter = $this->editor(['catalog.view', 'catalog.delete']);
        $this->actingAs($deleter, 'cms')->deleteJson($this->api("products/{$product->id}"))->assertNoContent();
        $this->actingAs($deleter, 'cms')->getJson($this->api('deleted'))->assertOk()->assertJsonPath('data.0.id', $product->id);
        $this->actingAs($deleter, 'cms')->postJson($this->api("products/{$product->id}/restore"))->assertOk();
    }

    #[Test]
    public function a_product_restored_after_its_category_went_comes_back_without_it_and_unpublished(): void
    {
        $belts = $this->category('belts');
        $product = $this->product('Belt', $belts);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson($this->api("products/{$product->id}"))->assertNoContent();
        $this->actingAs($editor, 'cms')->deleteJson($this->api("categories/{$belts->id}"))->assertNoContent();

        $this->actingAs($editor, 'cms')->getJson($this->api('deleted?type=products'))
            ->assertJsonPath('data.0.category.deleted', true);

        $this->actingAs($editor, 'cms')->postJson($this->api("products/{$product->id}/restore"))
            ->assertOk()
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.state', 'unpublished');

        $this->assertSame(1, HistoryEntry::query()->where('subject_type', 'catalog.product')->where('event', 'restored')->count());
    }

    #[Test]
    public function the_gallery_uploads_orders_captions_and_deletes(): void
    {
        Storage::fake('public');
        $product = $this->product('Belt', $this->category('belts'));
        $editor = $this->editor();

        $first = $this->actingAs($editor, 'cms')->post($this->api("products/{$product->id}/images"), [
            'file' => UploadedFile::fake()->image('front.jpg', 800, 600),
        ], ['Accept' => 'application/json'])->assertCreated();

        $path = (string) $first->json('data.path');
        $this->assertMatchesRegularExpression("#^catalog/0/{$product->id}/[0-9a-f]{40}\\.jpg$#", $path);
        Storage::disk('public')->assertExists($path);
        $first->assertJsonPath('data.width', 800)->assertJsonPath('data.height', 600);

        // Held in a variable: a fake file is removed from the disk when the object goes away.
        $back = UploadedFile::fake()->image('back.png', 400, 400);
        Http::fake(['https://supplier.test/*' => Http::response(
            (string) file_get_contents($back->getRealPath()),
            200,
            ['Content-Type' => 'image/png'],
        )]);

        $second = $this->actingAs($editor, 'cms')->postJson($this->api("products/{$product->id}/images"), [
            'url' => 'https://supplier.test/back.png',
        ])->assertCreated();

        $ids = [(int) $first->json('data.id'), (int) $second->json('data.id')];

        $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}/images"), [
            'images' => [
                ['id' => $ids[1], 'alt' => ['en' => 'The back']],
                ['id' => $ids[0]],
            ],
        ])->assertOk()->assertJsonPath('data.0.id', $ids[1])->assertJsonPath('data.0.alt.en', 'The back');

        $this->assertSame($ids[1], $product->mainImage()?->id);

        // A reorder that leaves a picture out is refused.
        $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}/images"), [
            'images' => [['id' => $ids[0]]],
        ])->assertStatus(422);

        // A thumbnail is cut beside the picture, and goes with it.
        $thumb = ProductImage::query()->findOrFail($ids[0])->thumbUrl(160, 160);
        $this->assertNotNull($thumb);

        $this->actingAs($editor, 'cms')->deleteJson($this->api("products/{$product->id}/images/{$ids[0]}"))->assertNoContent();
        Storage::disk('public')->assertMissing($path);
        $this->assertSame([], Storage::disk('public')->allFiles(dirname($path).'/thumbs/'.pathinfo($path, PATHINFO_FILENAME)));
        // The other picture's previews stay where they are.
        $this->assertNotSame([], Storage::disk('public')->allFiles(dirname($path).'/thumbs'));

        // Two pictures added, one taken away.
        $this->assertSame(3, HistoryEntry::query()->where('subject_type', 'catalog.product')->where('event', 'updated')->count());
    }

    #[Test]
    public function an_address_that_is_not_a_picture_is_refused(): void
    {
        Storage::fake('public');
        Http::fake(['https://supplier.test/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
        $product = $this->product('Belt');

        $this->actingAs($this->editor(), 'cms')->postJson($this->api("products/{$product->id}/images"), [
            'url' => 'https://supplier.test/page',
        ])->assertStatus(422)->assertJsonValidationErrors('url');

        $this->assertSame(0, ProductImage::query()->count());
    }

    #[Test]
    public function a_deleted_product_keeps_its_pictures(): void
    {
        Storage::fake('public');
        $product = $this->product('Belt', $this->category('belts'));
        $editor = $this->editor();

        $path = (string) $this->actingAs($editor, 'cms')->post($this->api("products/{$product->id}/images"), [
            'file' => UploadedFile::fake()->image('front.jpg', 100, 100),
        ], ['Accept' => 'application/json'])->json('data.path');

        $this->actingAs($editor, 'cms')->deleteJson($this->api("products/{$product->id}"))->assertNoContent();

        Storage::disk('public')->assertExists($path);
        $this->assertTrue(Product::withTrashed()->findOrFail($product->id)->images()->exists());
    }

    #[Test]
    #[DefineEnvironment('withCategoryFacets')]
    public function a_category_opens_with_its_values(): void
    {
        $laptops = $this->category('laptops');

        $this->actingAs($this->editor(['catalog.view']), 'cms')->getJson($this->api("categories/{$laptops->id}"))
            ->assertOk()
            ->assertJsonPath('data.category.slug', 'laptops')
            ->assertJsonPath('data.values.name.en', 'Laptops')
            ->assertJsonPath('data.values.facets', null);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("categories/{$laptops->id}"), [
            'values' => ['facets' => [['key' => 'brand', 'visible' => true], ['key' => 'price', 'visible' => false]]],
        ])->assertOk()->assertJsonPath('data.values.facets.1', ['key' => 'price', 'visible' => false]);
    }

    /**
     * @param  Application  $app
     */
    protected function withCategoryFacets($app): void
    {
        $app['config']->set('webx-catalog.fields.facets', true);
    }
}
