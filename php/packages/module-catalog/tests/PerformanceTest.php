<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Exchange\Importer;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogStock\StockServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The query budget of every public page of the catalogue, with the satellites a shop has — brands,
 * labels, stock, properties — and the freshness of what they cache (WEBX_UI_PERFORMANCE_AUDIT.md
 * §2.2, §7).
 *
 * Every budget is a ceiling the page fits under today with a hundred and forty products in
 * fifteen categories, not a target, and the point of each is that it does not grow with the rows:
 * a page that asks the registry once per card is caught by `assertNoRepeatedQueries()` on the warm
 * pass whatever the count. A change that needs more says why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    /**
     * The panel's list, its form and the agent's list (WEBX_UI_PERFORMANCE_AUDIT.md §9.5): each
     * row hashes its revision out of what the page loaded at once, so a list costs the same for
     * five rows and for fifty. Warm — the request before it filled the caches every panel request
     * reads — because what is measured is the endpoint, not the first visitor after a deploy.
     */
    #[Test]
    public function the_panel_fits_its_budgets_and_does_not_grow_with_the_rows(): void
    {
        $admin = CmsUser::query()->create(['name' => 'Admin', 'email' => 'budget@example.test', 'password' => 'correct-horse-battery', 'is_super' => true, 'is_active' => true]);
        $id = (string) Product::query()->orderBy('id')->value('id');

        // [address, budget, whether nothing may repeat]
        foreach ([
            ['/api/cms/catalog/products', 40, false],
            ['/api/cms/catalog/products?per_page=50', 40, false],
            ['/api/cms/catalog/products/{id}', 16, false],
            ['/api/cms/catalog/categories', 5, true],
        ] as [$url, $budget, $flat]) {
            $url = str_replace('{id}', $id, $url);
            $this->actingAs($admin, 'cms')->getJson($url)->assertOk();

            $this->assertQueriesWithin($budget, fn () => $this->actingAs($admin, 'cms')->getJson($url)->assertOk(), $url);

            if ($flat) {
                $this->assertNoRepeatedQueries(fn () => $this->actingAs($admin, 'cms')->getJson($url)->assertOk(), $url);
            }
        }

        foreach ([
            ['catalog_products_list', 44],
        ] as [$tool, $budget]) {
            $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
            $this->assertQueriesWithin($budget, fn () => WebxServer::actingAs($admin, 'cms')->tool($bound, [])->assertOk(), $tool);
            $this->assertNoRepeatedQueries(fn () => WebxServer::actingAs($admin, 'cms')->tool($bound, [])->assertOk(), $tool);
        }

        // A page of twenty and a page of fifty cost the same: nothing in the rows asks on its own.
        $twenty = $this->recordQueries(fn () => $this->actingAs($admin, 'cms')->getJson('/api/cms/catalog/products')->assertOk())->count();
        $fifty = $this->recordQueries(fn () => $this->actingAs($admin, 'cms')->getJson('/api/cms/catalog/products?per_page=50')->assertOk())->count();
        $this->assertSame($twenty, $fifty);
    }

    private Shop $shop;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            BrandsServiceProvider::class,
            LabelsServiceProvider::class,
            StockServiceProvider::class,
            CatalogPropertiesServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The caches on, the way a site has them: the budget of a warm page is the point.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.cache.enabled', true);
        $app['config']->set('webx-seo.cache.enabled', true);
        $app['config']->set('webx-catalog.root.enabled', true);
        $app['config']->set('webx-catalog.fields.facets', true);
        $app['config']->set('webx-catalog.price.currency', 'EUR');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::seed($this->app);
    }

    #[Test]
    public function the_root_fits_its_budget_cold_and_warm(): void
    {
        $this->cold();
        $this->budget(48, fn () => $this->page('/catalog'), 'root cold');

        // Warm, the counts of the filter come from the storefront's cache (§8.4): no `group by` at all.
        $this->budget(32, fn () => $this->page('/catalog'), 'root warm');
        $this->assertNoRepeatedQueries(fn () => $this->page('/catalog'), 'root warm');
    }

    #[Test]
    public function a_category_fits_its_budget_cold_and_warm(): void
    {
        // A top one with a subtree and the children in its filter, a leaf, and a leaf's parent
        // narrowed by a property's value.
        foreach (['/clothing', '/shirts', '/clothing/color_black'] as $path) {
            $this->cold();
            $this->budget(48, fn () => $this->page($path), "{$path} cold");

            $this->budget(32, fn () => $this->page($path), "{$path} warm");
            $this->assertNoRepeatedQueries(fn () => $this->page($path), "{$path} warm");
        }
    }

    #[Test]
    public function a_product_fits_its_budget_cold_and_warm(): void
    {
        // The third shirt: the first two have the id of their brand or their category, and two
        // rows of the registry would look like one statement asked twice.
        $path = $this->pathOf($this->shop->productOf('Shirts', 2)->url());

        $this->cold();
        $this->budget(36, fn () => $this->page($path), 'product cold');

        $this->budget(28, fn () => $this->page($path), 'product warm');
        $this->assertNoRepeatedQueries(fn () => $this->page($path), 'product warm');
    }

    #[Test]
    public function the_search_and_the_suggestions_fit_their_budgets(): void
    {
        $this->cold();
        $this->budget(42, fn () => $this->page('/catalog/search?q=shirt'), 'search cold');

        $this->budget(30, fn () => $this->page('/catalog/search?q=shirt'), 'search warm');
        $this->assertNoRepeatedQueries(fn () => $this->page('/catalog/search?q=shirt'), 'search warm');

        $this->budget(10, fn () => $this->page('/catalog/suggest?q=shirt'), 'suggest');
        $this->assertNoRepeatedQueries(fn () => $this->page('/catalog/suggest?q=shirt'), 'suggest');
    }

    #[Test]
    public function the_sitemap_fits_its_budget(): void
    {
        // The build: the categories, the products — visible by the chunk, with their pictures
        // and their rows of the registry — and the first levels of the filter, counted without
        // a page of ids per category.
        $this->cold();
        $this->budget(100, fn () => $this->page('/sitemap.xml'), 'sitemap build');

        // Served from the cache: nothing but the request's own infrastructure.
        foreach (['/sitemap-catalog.product.xml', '/sitemap-catalog.category.xml', '/sitemap-catalog-filters.xml'] as $file) {
            $this->budget(4, fn () => $this->page($file), $file);
        }
    }

    /**
     * The listing and the card read what the panel wrote, in the same process — the way a worker
     * serves the next request — and the old address of a renamed product leads to the new one.
     */
    #[Test]
    public function a_page_shows_a_product_edited_in_the_panel_at_once(): void
    {
        $product = $this->shop->productOf('Shirts');
        $name = $product->displayName();
        $old = $this->pathOf($product->url());

        $this->page('/shirts')->assertSee($name);
        $this->page($old)->assertSee($name);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api("products/{$product->getKey()}"), ['values' => [
                'name' => ['en' => 'The shirt, renamed'],
                'slug' => ['en' => 'the-shirt-renamed'],
                'is_published' => true,
            ]])
            ->assertOk();

        // The address is the slug and the id (§4): read back rather than spelled here.
        $new = $this->pathOf($product->refresh()->url());

        $this->page('/shirts')->assertSee('The shirt, renamed')->assertDontSee('>'.$name.'<', false);
        $this->page($new)->assertSee('The shirt, renamed');
        $this->get($old)->assertRedirect($new);
    }

    /** The trail of a product and the listing of a branch follow a category renamed and moved. */
    #[Test]
    public function the_trail_and_the_listing_follow_a_category_renamed_and_moved(): void
    {
        // The second shirt: the first is filed on a second shelf under «Women» already.
        $product = $this->shop->productOf('Shirts', 1);
        $path = $this->pathOf($product->url());
        $shirts = $this->shop->category('Shirts');
        $editor = $this->editor();

        $this->page($path)->assertSee('>Shirts<', false)->assertDontSee('>Tops<', false);
        $this->page('/men')->assertSee('>Shirts<', false);
        $this->page('/women')->assertDontSee('>Shirts<', false);

        $this->actingAs($editor, 'cms')
            ->putJson($this->api("categories/{$shirts->getKey()}"), ['values' => ['name' => ['en' => 'Tops']]])
            ->assertOk();

        $this->page($path)->assertSee('>Tops<', false)->assertDontSee('>Shirts<', false);

        $this->actingAs($editor, 'cms')
            ->postJson($this->api("categories/{$shirts->getKey()}/move"), ['parent_id' => $this->shop->category('Women')->getKey()])
            ->assertOk();

        // The branch's filter offers its children, and its list is the subtree: both read the tree anew.
        $this->page($path)->assertSee('>Women<', false)->assertDontSee('>Men<', false);
        $this->page('/women')->assertSee('>Tops<', false);
        $this->page('/men')->assertDontSee('>Tops<', false)->assertDontSee('>Shirts<', false);
    }

    /**
     * The filter of a listing follows the reference books and the settings behind it: a value
     * renamed, a property taken out of the category's set, a facet hidden in the category's
     * settings — each written through the panel's API, each cached in the `catalog` group.
     */
    #[Test]
    public function the_filter_follows_the_values_the_sets_and_the_settings(): void
    {
        $editor = $this->editor();
        $colour = $this->shop->properties['color'];
        $black = $this->shop->values['color']['Black'];
        $clothing = $this->shop->category('Clothing');

        $this->page('/clothing')->assertSee('Black')->assertSee('Colour')->assertSee('Brand');

        $this->actingAs($editor, 'cms')
            ->putJson($this->api("properties/{$colour->getKey()}/values/{$black->getKey()}"), ['title' => ['en' => 'Noir']])
            ->assertOk();

        $this->page('/clothing')->assertSee('Noir')->assertDontSee('>Black<', false);

        $this->actingAs($editor, 'cms')
            ->putJson($this->api("categories/{$clothing->getKey()}/properties"), ['ids' => []])
            ->assertOk();

        $this->page('/clothing')->assertDontSee('Colour');

        $this->actingAs($editor, 'cms')
            ->putJson($this->api("categories/{$clothing->getKey()}"), ['values' => ['facets' => [
                ['key' => 'category', 'visible' => true],
                ['key' => 'price', 'visible' => true],
                ['key' => 'brand', 'visible' => false],
            ]]])
            ->assertOk();

        $this->page('/clothing')->assertDontSee('Brand');
    }

    /** The listing follows a bulk action of the panel and an import of the exchange. */
    #[Test]
    public function the_listing_follows_a_bulk_action_and_an_import(): void
    {
        $first = $this->shop->productOf('Shirts');
        $second = $this->shop->productOf('Shirts', 1);

        $this->page('/shirts')->assertSee($first->displayName())->assertSee($second->displayName());

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('bulk'), ['action' => 'unpublish', 'selection' => ['ids' => [$first->getKey()]]])
            ->assertOk()
            ->assertJsonPath('data.done', 1);

        $this->page('/shirts')->assertDontSee($first->displayName())->assertSee($second->displayName());

        $path = tempnam(sys_get_temp_dir(), 'webx-test-').'.csv';
        file_put_contents($path, "sku,name\n{$second->sku},Imported shirt\n");
        $importer = $this->app->make(Importer::class);
        $importer->start(['path' => $path, 'name' => 'file.csv'], $importer->inspect($path, 'file.csv')['mapping'], ['key' => 'sku']);

        $this->page('/shirts')->assertSee('Imported shirt')->assertDontSee('>'.$second->displayName().'<', false);
    }

    /**
     * The budget, and the tally on the way: `WEBX_PERF_PRINT=1` prints every page's count and
     * what repeated, `2` every statement — which is how the numbers above were read.
     */
    private function budget(int $budget, callable $work, string $label): void
    {
        $tally = $this->recordQueries($work);

        if (getenv('WEBX_PERF_PRINT')) {
            $repeated = '';

            foreach ($tally->repeated() as $shape => $times) {
                $repeated .= sprintf("    %dx %s\n", $times, substr($shape, 0, 150));
            }

            fwrite(STDERR, sprintf("\n[%s] %d queries\n%s", $label, $tally->count(), getenv('WEBX_PERF_PRINT') === '2' ? $tally->describe()."\n" : $repeated));
        }

        self::assertLessThanOrEqual($budget, $tally->count(), "[{$label}] over the budget of {$budget} queries.".PHP_EOL.$tally->describe());
    }

    /**
     * @return TestResponse<Response>
     */
    private function page(string $path): TestResponse
    {
        return $this->get($path)->assertOk();
    }

    private function pathOf(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH);
    }

    /** The first visitor after a deploy: an empty store, and nothing read in this process either. */
    private function cold(): void
    {
        Cache::flush();
        $this->app->make(CacheGroups::class)->forgetMemo();
    }
}
