<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Application;
use LogicException;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Panel\ProductColumn;
use WebxUi\Catalog\Panel\ProductColumns;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Purchase\PurchaseRule;
use WebxUi\Catalog\Purchase\Verdict;
use WebxUi\Catalog\Sorts\Sort;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;
use WebxUi\Routing\Models\Route;

/**
 * §7 and §16: the registries as the panel and the satellites meet them — the facets endpoint,
 * the list filtered by the engine, the columns, the chain of refusals — and a site without prices
 * or barcodes, which has no trace of them anywhere.
 */
final class RegistriesTest extends TestCase
{
    #[Test]
    public function the_panel_reads_the_registry_of_facets(): void
    {
        $this->actingAs($this->editor(['catalog.view']), 'cms')
            ->getJson($this->api('facets'))
            ->assertOk()
            ->assertJsonPath('data.0.key', 'category')
            ->assertJsonPath('data.0.kind', 'tree')
            ->assertJsonPath('data.1.key', 'price')
            ->assertJsonPath('data.1.code', 'price')
            ->assertJsonPath('data.1.kind', 'range')
            ->assertJsonPath('data.1.indexable', false)
            ->assertJsonPath('meta.sorts.0.key', 'default');
    }

    #[Test]
    public function the_panels_list_is_filtered_and_counted_by_the_engine(): void
    {
        ColourFacet::migrate();
        $this->app->make(Facets::class)->register(new ColourFacet);

        $laptops = $this->category('laptops');
        $phones = $this->category('phones');
        ColourFacet::paint($black = $this->product('Black laptop', $laptops, ['price' => 100]), 'black');
        ColourFacet::paint($this->product('White laptop', $laptops, ['price' => 200]), 'white');
        ColourFacet::paint($this->product('Black phone', $phones, ['price' => 50]), 'black');
        // The panel sees what the site does not.
        ColourFacet::paint($hidden = $this->product('Black draft', $laptops, ['is_published' => false]), 'black');

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('products').'?'.http_build_query([
                'facets' => ['category' => [(string) $laptops->id], 'colour' => ['black']],
            ]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('facets.colour.values.0.label', 'Black');

        $this->assertEqualsCanonicalizing([$black->id, $hidden->id], array_column($response->json('data'), 'id'));
        // A facet's own choice aside: white is still counted in laptops.
        $colours = array_column($response->json('facets.colour.values'), 'count', 'value');
        $this->assertSame(['black' => 2, 'white' => 1], [...['black' => $colours['black'] ?? null], ...['white' => $colours['white'] ?? null]]);

        $this->actingAs($this->editor(), 'cms')->getJson($this->api('products').'?sort=whatever')->assertStatus(422);
    }

    #[Test]
    public function a_satellites_column_comes_with_every_row_in_one_call(): void
    {
        $column = new class implements ProductColumn
        {
            public int $calls = 0;

            public function key(): string
            {
                return 'stock';
            }

            public function label(): string
            {
                return 'Stock';
            }

            public function values(Collection $products): array
            {
                $this->calls++;

                return array_fill_keys($products->modelKeys(), 'in stock');
            }

            public function sort(): ?string
            {
                return null;
            }
        };

        $this->app->make(ProductColumns::class)->register($column);

        $laptops = $this->category('laptops');
        $this->product('One', $laptops);
        $this->product('Two', $laptops);

        $this->actingAs($this->editor(), 'cms')->getJson($this->api('products'))
            ->assertOk()
            ->assertJsonPath('columns.0.key', 'stock')
            ->assertJsonPath('data.0.columns.stock', 'in stock')
            ->assertJsonPath('data.1.columns.stock', 'in stock');

        $this->assertSame(1, $column->calls);
    }

    #[Test]
    public function the_first_refusal_is_the_answer_and_price_on_request_is_the_last(): void
    {
        $laptops = $this->category('laptops');
        $outOfStock = $this->product('Out of stock', $laptops);
        $unpriced = $this->product('No price', $laptops);
        $hidden = $this->product('Unpublished', $laptops, ['is_published' => false, 'price' => 10]);
        $fine = $this->product('Fine', $laptops, ['price' => 10]);

        // A satellite registered after the core — and "price on request" still comes after it.
        $this->app->make(Purchasability::class)->register(new class($outOfStock->id) implements PurchaseRule
        {
            public function __construct(private readonly int $id) {}

            public function refuse(Collection $products): array
            {
                return $products->contains('id', $this->id) ? [$this->id => Verdict::no('out-of-stock', 'Out of stock')] : [];
            }
        });

        $verdicts = $this->app->make(Purchasability::class)->forMany(new Collection([$outOfStock, $unpriced, $hidden, $fine]));

        $this->assertSame('out-of-stock', $verdicts[$outOfStock->id]->code);
        $this->assertSame('price-on-request', $verdicts[$unpriced->id]->code);
        $this->assertSame('unavailable', $verdicts[$hidden->id]->code);
        $this->assertTrue($verdicts[$fine->id]->purchasable);
    }

    #[Test]
    public function a_facet_code_with_an_underscore_is_refused_at_registration(): void
    {
        $this->expectException(LogicException::class);

        $this->app->make(Facets::class)->register(new class extends ColourFacet
        {
            public function key(): string
            {
                return 'p.material';
            }

            protected function baseCode(): string
            {
                return 'material_type';
            }
        });
    }

    #[Test]
    public function doctor_says_when_the_database_engine_has_been_outgrown(): void
    {
        $this->app['config']->set('webx-catalog.sql_engine_limit', 1);
        $laptops = $this->category('laptops');
        $this->product('One', $laptops);
        $this->product('Two', $laptops);

        $this->artisan('webx:doctor')->expectsOutputToContain('past its limit of 1');
    }

    #[Test]
    public function doctor_names_the_pages_the_root_of_the_catalogue_hides(): void
    {
        // Saved before the root was switched on: `Reserved` had nothing to refuse yet.
        Route::query()->create(['locale' => 'en', 'path' => 'catalog', 'kind' => Route::CANONICAL, 'entity_type' => 'page', 'entity_id' => 1]);

        $this->app['config']->set('webx-catalog.root.enabled', true);

        $this->artisan('webx:doctor')->expectsOutputToContain('these addresses never open: /catalog');
    }

    #[Test]
    #[DefineEnvironment('withoutPriceAndBarcode')]
    public function switched_off_price_and_barcode_leave_no_facet_no_sort_and_no_field(): void
    {
        $facets = array_map(static fn (Facet $facet): string => $facet->key(), $this->app->make(Facets::class)->all());
        $sorts = array_map(static fn (Sort $sort): string => $sort->key(), $this->app->make(Sorts::class)->all());
        $fields = array_map(static fn (IndexField $field): string => $field->name, $this->app->make(Documents::class)->schema(['en']));

        $this->assertSame(['category'], $facets);
        $this->assertNotContains('price_asc', $sorts);
        $this->assertNotContains('price_desc', $sorts);
        $this->assertNotContains('price', $fields);
        $this->assertNotContains('barcode', $fields);

        $product = $this->product('Belt', $this->category('belts'));

        $this->assertTrue($this->app->make(Purchasability::class)->for($product)->purchasable);
        $this->get('/belts')->assertOk()->assertDontSee('Price on request')->assertDontSee('webx-catalog-filter__group--range', false);
        $this->get('/belts/price_1-2')->assertNotFound();
        $this->actingAs($this->editor(), 'cms')->getJson($this->api('facets'))->assertJsonCount(1, 'data');
    }

    /**
     * @param  Application  $app
     */
    protected function withoutPriceAndBarcode($app): void
    {
        $app['config']->set('webx-catalog.price.enabled', false);
        $app['config']->set('webx-catalog.fields.barcode', false);
    }
}
