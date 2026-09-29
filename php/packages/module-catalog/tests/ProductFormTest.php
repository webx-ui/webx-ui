<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Tests\Fixtures\NotePart;

/**
 * §7.4 and §11.2: the form is one save — the core, every part, one journal row — and the rules
 * every door into the table obeys.
 */
final class ProductFormTest extends TestCase
{
    #[Test]
    public function an_article_number_held_by_a_deleted_product_is_a_422_naming_it(): void
    {
        $old = $this->product('Old belt', null, ['sku' => 'B-100']);
        $old->delete();

        $new = $this->product('New belt');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$new->id}"), [
            'values' => ['sku' => 'B-100'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sku')
            ->assertJsonPath('meta.taken_by.id', $old->id)
            ->assertJsonPath('meta.taken_by.name', 'Old belt')
            ->assertJsonPath('meta.taken_by.deleted', true)
            ->assertJsonPath('meta.taken_by.url', "http://localhost/cms/catalog/products/{$old->id}");

        $this->assertNull($new->refresh()->sku);
    }

    #[Test]
    public function a_product_without_a_main_category_is_not_published(): void
    {
        $product = $this->product('Loose');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['is_published' => true],
        ])->assertStatus(422)->assertJsonValidationErrors('category_id');

        $this->assertFalse($product->refresh()->is_published);
    }

    #[Test]
    public function a_new_product_takes_its_slug_from_the_name_and_its_values_come_back(): void
    {
        $laptops = $this->category('laptops');
        $sale = $this->category('sale');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('products'), [
            'values' => [
                'name' => ['en' => 'ThinkPad X1'],
                'sku' => ' TP-X1 ',
                'category_id' => $laptops->id,
                'categories' => [$sale->id, $laptops->id],
                'price' => '1299.9',
                'unit' => 'pcs',
                'is_published' => true,
            ],
        ])->assertCreated();

        $id = (int) $response->json('data.product.id');

        $response
            ->assertJsonPath('data.product.state', 'published')
            ->assertJsonPath('data.product.visible', true)
            ->assertJsonPath('data.product.url', "http://localhost/thinkpad-x1-{$id}")
            ->assertJsonPath('data.values.slug.en', 'thinkpad-x1')
            ->assertJsonPath('data.values.sku', 'TP-X1')
            ->assertJsonPath('data.values.price', 1299.9)
            // The main category never stands among the additional ones (decision 2).
            ->assertJsonPath('data.values.categories', [$sale->id]);

        $this->assertSame(1, HistoryEntry::query()->where('subject_type', 'catalog.product')->where('subject_id', $id)->where('event', 'created')->count());
    }

    #[Test]
    public function a_unit_not_in_the_config_is_refused(): void
    {
        $product = $this->product('Belt');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['unit' => 'parsec'],
        ])->assertStatus(422)->assertJsonValidationErrors('unit');
    }

    #[Test]
    public function one_save_writes_the_core_and_every_part_into_one_journal_row(): void
    {
        $this->withNotes();
        $product = $this->product('Belt', $this->category('belts'), ['price' => 100]);

        $values = $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['price' => 120, 'notes.text' => 'fragile'],
        ])->assertOk()->json('data.values');

        // A key with a dot is one key, which a JSON path would read as two (docs/pitfalls).
        $this->assertSame('fragile', $values['notes.text']);

        $this->assertSame(120.0, (float) $product->refresh()->price);

        $rows = HistoryEntry::query()->where('subject_type', 'catalog.product')->where('event', 'updated')->get();
        $this->assertCount(1, $rows);
        $this->assertEqualsCanonicalizing(['price', 'notes.text'], array_column((array) $rows[0]->changes, 'field'));
    }

    #[Test]
    public function a_part_that_fails_takes_the_whole_save_back(): void
    {
        $this->withNotes();
        $product = $this->product('Belt', $this->category('belts'), ['price' => 100]);

        try {
            $this->withoutExceptionHandling()->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
                'values' => ['price' => 120, 'notes.text' => 'explode'],
            ]);
            $this->fail('The part should have failed the save.');
        } catch (RuntimeException $failure) {
            $this->assertSame('The part failed after writing.', $failure->getMessage());
        }

        $this->assertSame(100.0, (float) $product->refresh()->price);
        $this->assertNull(DB::table('test_notes')->where('product_id', $product->id)->value('text'));
        $this->assertSame(0, HistoryEntry::query()->where('event', 'updated')->count());
    }

    #[Test]
    public function a_part_checks_its_own_rules_before_anything_is_written(): void
    {
        $this->withNotes();
        $product = $this->product('Belt', $this->category('belts'), ['price' => 100]);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['price' => 120, 'notes.text' => str_repeat('x', 30)],
        ])->assertStatus(422)->assertJsonValidationErrors('notes.text');

        $this->assertSame(100.0, (float) $product->refresh()->price);
    }

    #[Test]
    public function publishing_is_the_word_the_journal_uses(): void
    {
        $product = $this->product('Belt', $this->category('belts'));
        $product->update(['is_published' => false]);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['is_published' => true, 'priority' => 5],
        ])->assertOk();

        $entry = HistoryEntry::query()->where('subject_id', $product->id)->latest('id')->firstOrFail();
        $this->assertSame('published', $entry->event);
        $this->assertContains('priority', array_column((array) $entry->changes, 'field'));
    }

    #[Test]
    #[DefineEnvironment('withoutPriceAndBarcode')]
    public function price_and_barcode_switched_off_are_neither_on_the_form_nor_written(): void
    {
        $names = array_column($this->app->make(ScreenRegistry::class)->fields(Product::SCREEN), 'name');

        $this->assertNotContains('price', $names);
        $this->assertNotContains('old_price', $names);
        $this->assertNotContains('barcode', $names);
        $this->assertContains('sku', $names);

        $product = $this->product('Belt');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['price' => 120, 'barcode' => '4600000000000', 'sku' => 'B-1'],
        ])
            ->assertOk()
            ->assertJsonMissingPath('data.product.price')
            ->assertJsonMissingPath('data.product.barcode');

        $product->refresh();
        $this->assertNull($product->price);
        $this->assertNull($product->barcode);
        $this->assertSame('B-1', $product->sku);
    }

    /**
     * @param  Application  $app
     */
    protected function withoutPriceAndBarcode($app): void
    {
        $app['config']->set('webx-catalog.price.enabled', false);
        $app['config']->set('webx-catalog.fields.barcode', false);
    }

    private function withNotes(): void
    {
        Schema::create('test_notes', static function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->primary();
            $table->string('text')->nullable();
        });

        $this->app->make(ProductParts::class)->register(new NotePart);
        $this->app->make(ScreenRegistry::class)->extend(Product::SCREEN, [[
            'op' => 'add',
            'target' => 'tabs',
            'node' => [
                'id' => 'notes-tab',
                'type' => 'wx-tab',
                'children' => [['id' => 'notes-text', 'type' => 'wx-input', 'name' => 'notes.text']],
            ],
        ]]);
    }
}
