<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * §6.3: what the values on the form would show, nothing saved, and of a hand-picked list what will
 * not reach the site, with the reason.
 */
final class PreviewTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), StockServiceProvider::class];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function preview(array $values): array
    {
        return $this->actingAs($this->manager(), 'cms')->postJson($this->api('preview'), ['locale' => 'en', ...$values])->assertOk()->json('data');
    }

    /**
     * @param  array<int, array<string, mixed>>  $dropped
     * @return array<int, string>
     */
    private function reasons(array $dropped): array
    {
        return array_column($dropped, 'reason', 'id');
    }

    private function inStatus(string $name, string $code): Product
    {
        $product = $this->product($name);
        $status = StockStatus::query()->where('code', $code)->firstOrFail();
        DB::table(StockStatus::LINKS)->insert(['product_id' => $product->id, 'status_id' => $status->id]);

        return $product;
    }

    #[Test]
    public function a_filter_shows_what_is_on_the_form_and_saves_nothing(): void
    {
        $cheap = $this->product('Cheap', ['price' => 10, 'old_price' => 20]);
        $this->product('Plain', ['price' => 10]);

        $answer = $this->preview(['source' => 'filter', 'on_sale' => true, 'limit' => 8]);

        $this->assertSame([$cheap->id], array_column($answer['products'], 'id'));
        $this->assertSame(1, $answer['count']);
        $this->assertFalse($answer['more']);
        $this->assertSame(20.0, (float) $answer['products'][0]['old_price']);
        $this->assertSame(0, ProductCollection::query()->count());
    }

    #[Test]
    public function the_limit_cuts_what_is_shown_and_not_what_is_counted(): void
    {
        foreach (['One', 'Two', 'Three'] as $name) {
            $this->product($name);
        }

        $answer = $this->preview(['source' => 'filter', 'limit' => 2]);

        $this->assertCount(2, $answer['products']);
        $this->assertSame(3, $answer['count']);
    }

    #[Test]
    public function a_filter_stops_counting_past_the_ceiling_and_says_so(): void
    {
        config(['webx-catalog-collections.max_limit' => 2]);

        foreach (['One', 'Two', 'Three'] as $name) {
            $this->product($name);
        }

        $answer = $this->preview(['source' => 'filter', 'limit' => 2]);

        $this->assertSame(2, $answer['count']);
        $this->assertTrue($answer['more']);
    }

    #[Test]
    public function a_price_stays_hidden_when_the_catalogue_hides_prices(): void
    {
        config(['webx-catalog.price.enabled' => false]);
        $this->product('Camera', ['price' => 10]);

        $this->assertArrayNotHasKey('price', $this->preview(['source' => 'filter'])['products'][0]);
    }

    #[Test]
    public function a_hand_picked_list_keeps_its_order_and_says_why_each_of_the_others_is_out(): void
    {
        $shown = $this->product('Shown');
        $unpublished = $this->product('Draft', ['is_published' => false]);
        $binned = $this->product('Binned');
        $binned->delete();
        $noLanguage = $this->product('Polish only');
        DB::table('catalog_products')->where('id', $noLanguage->id)->update(['slug' => json_encode(['ru' => 'tolko'])]);
        $sold = $this->inStatus('Sold out', 'out-of-stock');

        $hiddenPlace = new Category(['name' => 'Hidden', 'slug' => 'hidden', 'is_published' => false]);
        $hiddenPlace->saveAsRoot();
        $hidden = Product::query()->create(['name' => 'Hidden', 'category_id' => $hiddenPlace->id, 'is_published' => true, 'price' => 10]);

        $answer = $this->preview([
            'source' => 'manual',
            'limit' => 8,
            'products' => [$unpublished->id, $shown->id, $binned->id, $noLanguage->id, $sold->id, $hidden->id, 999999],
        ]);

        $this->assertSame([$shown->id], array_column($answer['products'], 'id'));
        $this->assertSame(1, $answer['count']);
        $this->assertSame([
            $unpublished->id => 'unpublished',
            $binned->id => 'in-bin',
            $noLanguage->id => 'no-language',
            $sold->id => 'status',
            $hidden->id => 'hidden',
            999999 => 'gone',
        ], $this->reasons($answer['dropped']));
        $this->assertSame('Out of stock', array_column($answer['dropped'], 'status', 'id')[$sold->id]);
    }

    #[Test]
    public function what_passes_the_rules_but_not_the_limit_is_out_for_that(): void
    {
        $one = $this->product('One');
        $two = $this->product('Two');

        $answer = $this->preview(['source' => 'manual', 'limit' => 1, 'products' => [$one->id, $two->id]]);

        $this->assertSame([$one->id], array_column($answer['products'], 'id'));
        $this->assertSame(2, $answer['count']);
        $this->assertSame([$two->id => 'over-limit'], $this->reasons($answer['dropped']));
    }

    #[Test]
    public function with_the_setting_off_a_status_keeps_nothing_out(): void
    {
        $sold = $this->inStatus('Sold out', 'out-of-stock');

        $answer = $this->preview(['source' => 'manual', 'only_purchasable' => false, 'products' => [$sold->id]]);

        $this->assertSame([$sold->id], array_column($answer['products'], 'id'));
        $this->assertSame([], $answer['dropped']);
    }

    #[Test]
    public function an_empty_form_is_an_empty_answer(): void
    {
        $answer = $this->preview(['source' => 'manual']);

        $this->assertSame([], $answer['products']);
        $this->assertSame(0, $answer['count']);
    }

    #[Test]
    public function an_unknown_facet_is_a_refusal_rather_than_the_whole_catalogue(): void
    {
        $this->product('Camera');

        $this->actingAs($this->manager(), 'cms')
            ->postJson($this->api('preview'), ['source' => 'filter', 'filters' => ['nothing' => ['values' => [1]]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filters');
    }

    #[Test]
    public function the_form_keeps_the_switch_beside_the_stock_satellite(): void
    {
        $this->assertStringContainsString('only-purchasable', (string) json_encode($this->app->make(ScreenRegistry::class)->tree(ProductCollection::SCREEN)));
    }
}
