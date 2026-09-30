<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\CoreRules;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogStock\Catalog\StockFacet;
use WebxUi\CatalogStock\Catalog\StockRule;
use WebxUi\CatalogStock\Models\StockStatus;

/**
 * §2.2 and §9 of the dictionaries spec: a product without a row is in the default status, a status
 * that cannot be bought refuses with its own name, in its place in the chain, the default holds on
 * to its flag, and edits mark the products in one statement.
 */
final class StockTest extends TestCase
{
    #[Test]
    public function the_migration_makes_three_statuses_and_in_stock_is_the_default(): void
    {
        $statuses = StockStatus::query()->scopes(['ordered'])->get();

        $this->assertSame(['in-stock', 'out-of-stock', 'on-order'], $statuses->pluck('code')->all());
        $this->assertSame([true, false, true], $statuses->pluck('is_purchasable')->all());
        $fallback = StockStatus::fallback();
        $this->assertInstanceOf(StockStatus::class, $fallback);
        $this->assertSame('in-stock', $fallback->code);
        $this->assertSame('In stock', $fallback->displayName('en'));
    }

    #[Test]
    public function a_product_without_a_row_is_in_the_default_status(): void
    {
        $product = $this->product('Belt');

        $values = $this->actingAs($this->editor(), 'cms')->getJson($this->api("products/{$product->id}"))->assertOk()->json('data.values');
        $this->assertSame($this->stockStatus('in-stock')->id, $values['stock.status']);

        $this->assertTrue($this->app->make(Purchasability::class)->for($product)->purchasable);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->actingAs($this->editor(), 'cms')->getJson($this->api('products'))->assertOk()->json('data');
        $row = collect($rows)->firstWhere('id', $product->id);
        $this->assertSame('in-stock', $row['columns']['stock']['code']);
    }

    #[Test]
    public function the_form_writes_a_row_even_for_the_default_and_journals_the_change_with_the_save(): void
    {
        $product = $this->product('Belt');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['stock.status' => $this->stockStatus('in-stock')->id],
        ])->assertOk();

        // Written down, though nothing changed for the reader: moving the default later must not
        // move a product somebody set by hand.
        $this->assertSame($this->stockStatus('in-stock')->id, (int) DB::table(StockStatus::LINKS)->where('product_id', $product->id)->value('status_id'));

        $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['price' => 12, 'stock.status' => $this->stockStatus('on-order')->id],
        ])->assertOk();

        $rows = HistoryEntry::query()->where('subject_type', Product::TYPE)->where('event', 'updated')->get();
        $last = collect((array) $rows->last()?->changes)->keyBy('field');
        $this->assertEqualsCanonicalizing(['price', 'stock.status'], $last->keys()->all());
        $this->assertSame(['In stock', 'On order'], [$last['stock.status']['from'], $last['stock.status']['to']]);

        $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['stock.status' => 404],
        ])->assertStatus(422)->assertJsonValidationErrors('stock.status');
    }

    #[Test]
    public function a_status_that_cannot_be_bought_refuses_with_its_own_name(): void
    {
        $product = $this->product('Belt', $this->stockStatus('out-of-stock'));

        $verdict = $this->app->make(Purchasability::class)->for($product);

        $this->assertFalse($verdict->purchasable);
        $this->assertSame(StockRule::CODE, $verdict->code);
        $this->assertSame('Out of stock', $verdict->label);
    }

    #[Test]
    public function not_on_sale_beats_the_stock_and_the_stock_beats_price_on_request(): void
    {
        $out = $this->stockStatus('out-of-stock');
        $hidden = $this->product('Hidden', $out, ['is_published' => false]);
        $unpriced = $this->product('Unpriced', $out, ['price' => null]);
        $orderable = $this->product('Orderable', $this->stockStatus('on-order'), ['price' => null]);

        $verdicts = $this->app->make(Purchasability::class)->forMany(new Collection([$hidden, $unpriced, $orderable]));

        $this->assertSame(CoreRules::UNAVAILABLE, $verdicts[$hidden->id]->code);
        $this->assertSame(StockRule::CODE, $verdicts[$unpriced->id]->code);
        $this->assertSame(CoreRules::PRICE_ON_REQUEST, $verdicts[$orderable->id]->code);
    }

    #[Test]
    public function the_default_cannot_be_deleted_nor_switched_off_only_handed_over(): void
    {
        $editor = $this->editor();
        $inStock = $this->stockStatus('in-stock');
        $onOrder = $this->stockStatus('on-order');

        $this->actingAs($editor, 'cms')->deleteJson($this->api("stock/{$inStock->id}"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'The default status cannot be deleted: make another status the default first.');

        $this->actingAs($editor, 'cms')->putJson($this->api("stock/{$inStock->id}"), ['values' => ['is_default' => false]])
            ->assertStatus(422)->assertJsonValidationErrors('is_default');

        $this->actingAs($editor, 'cms')->putJson($this->api("stock/{$onOrder->id}"), ['values' => ['is_default' => true]])->assertOk();

        $this->assertFalse($inStock->refresh()->is_default);
        $this->assertSame($onOrder->id, StockStatus::fallback()?->id);
        $this->assertSame(1, StockStatus::query()->where('is_default', true)->count());

        $this->actingAs($editor, 'cms')->deleteJson($this->api("stock/{$inStock->id}"))->assertNoContent();
    }

    #[Test]
    public function a_status_products_are_in_cannot_be_deleted_and_says_how_many(): void
    {
        $out = $this->stockStatus('out-of-stock');
        $this->product('One', $out);

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api("stock/{$out->id}"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Products are in this status. Products: 1');
    }

    #[Test]
    public function the_filter_counts_a_product_without_a_row_under_the_default(): void
    {
        $inStock = $this->stockStatus('in-stock');
        $out = $this->stockStatus('out-of-stock');
        $this->product('Rowless');
        $this->product('Written', $inStock);
        $this->product('Gone', $out);
        $this->stockStatus('on-order')->update(['is_visible' => false]);
        $this->product('Ordered', $this->stockStatus('on-order'));

        $engine = $this->app->make(Catalog::class)->engine();
        $counted = $engine->search(new CatalogQuery(locale: 'en', count: [StockFacet::KEY]));

        $this->assertSame([(string) $inStock->id => 2, (string) $out->id => 1], $counted->facet(StockFacet::KEY)?->counts);

        $chosen = $engine->search(new CatalogQuery(locale: 'en', facets: [StockFacet::KEY => FacetValue::of([(string) $inStock->id])]));
        $this->assertSame(2, $chosen->total);

        $facet = new StockFacet;
        $this->assertSame(['in-stock' => (string) $inStock->id], $facet->resolveSlugs(['in-stock', 'on-order'], 'en'));
        $this->assertFalse($facet->indexable());
    }

    #[Test]
    public function an_edit_of_the_default_marks_its_products_and_every_product_without_a_row_in_one_statement(): void
    {
        $inStock = $this->stockStatus('in-stock');
        $rowless = $this->product('Rowless');
        $written = $this->product('Written', $inStock);
        $this->product('Elsewhere', $this->stockStatus('on-order'));
        DB::table('catalog_index_queue')->delete();

        DB::enableQueryLog();
        $this->actingAs($this->editor(), 'cms')->putJson($this->api("stock/{$inStock->id}"), [
            'values' => ['is_purchasable' => false],
        ])->assertOk();
        $statements = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'catalog_index_queue'));
        DB::disableQueryLog();

        $this->assertCount(1, $statements);
        $this->assertEqualsCanonicalizing([$rowless->id, $written->id], DB::table('catalog_index_queue')->pluck('product_id')->map(intval(...))->all());
        $this->assertSame(StockRule::CODE, $this->app->make(Purchasability::class)->for($rowless)->code);
    }

    #[Test]
    public function a_template_picks_the_products_that_can_be_bought(): void
    {
        $rowless = $this->product('Rowless');
        $ordered = $this->product('Ordered', $this->stockStatus('on-order'));
        $this->product('Gone', $this->stockStatus('out-of-stock'));

        $this->assertEqualsCanonicalizing([$rowless->id, $ordered->id], products()->{'inStock'}()->models()->modelKeys());
    }

    #[Test]
    public function the_status_is_set_on_many_products_at_once(): void
    {
        $rowless = $this->product('Rowless');
        $written = $this->product('Written', $this->stockStatus('on-order'));
        $out = $this->stockStatus('out-of-stock');

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('bulk'), [
            'action' => 'set-stock',
            'params' => ['status_id' => $out->id],
            'selection' => ['ids' => [$rowless->id, $written->id]],
        ])->assertOk();

        $this->assertSame(2, DB::table(StockStatus::LINKS)->where('status_id', $out->id)->count());
    }

    #[Test]
    public function the_line_is_printed_on_the_card_and_beside_the_buy_button(): void
    {
        $product = $this->product('Belt', $this->stockStatus('out-of-stock'));
        $parts = $this->app->make(StorefrontParts::class);
        $parts->prepare(new Collection([$product]));

        $card = $parts->render('catalog.card.meta', ['product' => $product]);
        $aside = $parts->render('catalog.product.aside', ['product' => $product]);

        $this->assertStringContainsString('wx-catalog-stock--danger', $card);
        $this->assertStringContainsString('wx-catalog-stock--card', $card);
        $this->assertStringContainsString('wx-catalog-stock--product', $aside);
        $this->assertStringContainsString('>Out of stock<', $aside);
    }
}
