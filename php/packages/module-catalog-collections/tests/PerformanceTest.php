<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Storefront\LiveCollections;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * The query budget of a collection on the site (§5.6, WEBX_UI_PERFORMANCE.md), on the shop of the
 * core's fixture with the satellites that add to a card beside it.
 *
 * Each measure is a request of its own: a second render inside one request finds the card's parts
 * and the verdicts already worked out, and would call a page warm that no visitor ever gets.
 *
 * Every budget is a ceiling the page fits under today, not a target. A change that needs more says
 * why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), StockServiceProvider::class, LabelsServiceProvider::class, BrandsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-cache.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Shop::seed($this->app);

        Route::get('/page/{keys}', static function (string $keys): string {
            $tags = array_map(static fn (string $key): string => '<x-webx-catalog-collections::collection key="'.$key.'" />', explode(',', $keys));

            return Blade::render(implode('', $tags));
        });
    }

    /** With a link to a category, as a collection on a real page has: the link has a price too. */
    private function collection(string $key, int $limit = 8, string $sort = 'new'): void
    {
        ProductCollection::query()->create([
            'title' => 'Picks',
            'key' => $key,
            'source' => ProductCollection::FILTER,
            'sort' => $sort,
            'limit' => $limit,
            'link' => ['target' => 'entity', 'entity_type' => Category::TYPE, 'entity_id' => Category::query()->value('id')],
        ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function page(string $keys): TestResponse
    {
        return $this->get('/page/'.$keys)->assertOk();
    }

    #[Test]
    public function a_collection_fits_its_budget_cold_and_warm(): void
    {
        $this->collection('picks');

        // Cold: the settings and the redirects of the site, the collections, the two questions to
        // the statuses, the selection with what `models()` loads with it (§5.6), the warm path below.
        $html = $this->assertQueriesWithin(25, fn () => $this->page('picks'), 'cold')->getContent();
        $this->assertSame(8, substr_count((string) $html, 'class="webx-catalog-card"'));

        // Warm: the redirects, the products with their categories, pictures and barcodes, the main
        // categories, the stock with its statuses, the brands, the check of what can be bought, and
        // four for the entity behind the link, which no cache of ours can know the state of.
        $this->assertQueriesWithin(15, fn () => $this->page('picks'), 'warm');
        $this->assertNoRepeatedQueries(fn () => $this->page('picks'), 'warm');
    }

    #[Test]
    public function a_warm_collection_asks_nothing_of_the_collections_tables(): void
    {
        $this->collection('picks');
        $this->page('picks');

        $tally = $this->recordQueries(fn () => $this->page('picks'));

        $this->assertStringNotContainsString('catalog_collections', $tally->describe());
    }

    #[Test]
    public function the_number_of_products_changes_nothing(): void
    {
        $this->collection('few', 2);
        $this->collection('many', 24);

        $count = function (string $key): int {
            $this->page($key);

            return $this->recordQueries(fn () => $this->page($key))->count();
        };

        $this->assertSame($count('few'), $count('many'));
    }

    #[Test]
    public function two_collections_on_a_page_fit_their_budget(): void
    {
        // Other products in the second, or it would find its cards' parts already prepared.
        $this->collection('first');
        $this->collection('second', sort: 'price_asc');
        $this->page('first,second');

        // The redirects once, and the fourteen of a warm collection for each.
        $this->assertQueriesWithin(29, fn () => $this->page('first,second'), 'two');
    }

    #[Test]
    public function a_saved_collection_is_read_again_and_the_budget_holds(): void
    {
        $this->collection('picks');
        $this->page('picks');
        $version = $this->app->make(CacheGroups::class)->version(LiveCollections::GROUP);

        ProductCollection::query()->where('key', 'picks')->firstOrFail()->update(['limit' => 2]);

        $this->assertNotSame($version, $this->app->make(CacheGroups::class)->version(LiveCollections::GROUP));
        $this->assertSame(2, substr_count((string) $this->page('picks')->getContent(), 'class="webx-catalog-card"'));
    }
}
