<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The query budget of a landing's page — a category's page over a set, with the strip of
 * recommended products and the links to its neighbours — of the category's page with its
 * collections, of a product with «In collections» beside it, and of the landings' file of the
 * sitemap (WEBX_UI_PERFORMANCE_AUDIT.md §2.2).
 *
 * Every budget is a ceiling the page fits under today with the shop of the core's fixture, not a
 * target; the warm pass asks nothing twice. A change that needs more says why here before raising
 * the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    private Shop $shop;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The product page plays its videos through the widgets, which the themes serve.
        return [...parent::getPackageProviders($app), ThemeServiceProvider::class, WidgetsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The caches on, the way a site has them, and the database as the engine: the counts
        // are the budget's, not the queue's.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.cache.enabled', true);
        $app['config']->set('webx-seo.cache.enabled', true);
        $app['config']->set('webx-catalog.engine', 'sql');
        $app['config']->set('webx-catalog.fields.facets', true);
        $app['config']->set('webx-catalog.price.currency', 'EUR');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::seed($this->app);
    }

    #[Test]
    public function a_landing_fits_its_budget_cold_and_warm(): void
    {
        $path = $this->pathOf($this->shop->landings['black-clothing']->url());

        $this->cold();
        $this->budget(56, fn () => $this->page($path), 'landing cold');
        $this->budget(48, fn () => $this->page($path), 'landing warm');

        // Nothing asked twice — on a landing without a strip of recommended products: the strip
        // is a second, smaller round of the cards' parts for the products it adds, and on a base
        // whose id is not the landing's, or two rows of the registry would look like one statement.
        $plain = $this->pathOf($this->shop->landings['black-shoes']->url());
        $this->page($plain);
        $this->assertNoRepeatedQueries(fn () => $this->page($plain), 'plain landing warm');
    }

    #[Test]
    public function a_category_with_collections_fits_its_budget_cold_and_warm(): void
    {
        $this->cold();
        $this->budget(44, fn () => $this->page('/clothing'), 'category cold');

        $this->budget(36, fn () => $this->page('/clothing'), 'category warm');
        $this->assertNoRepeatedQueries(fn () => $this->page('/clothing'), 'category warm');
    }

    #[Test]
    public function a_product_in_collections_fits_its_budget_cold_and_warm(): void
    {
        // The third shirt: the first two have the id of their brand or their category, and two
        // rows of the registry would look like one statement asked twice.
        $path = $this->pathOf($this->shop->productOf('Shirts', 2)->url());

        $this->cold();
        $this->budget(42, fn () => $this->page($path), 'product cold');

        $this->budget(34, fn () => $this->page($path), 'product warm');
        $this->assertNoRepeatedQueries(fn () => $this->page($path), 'product warm');
    }

    #[Test]
    public function the_landings_file_of_the_sitemap_fits_its_budget(): void
    {
        $this->cold();
        $this->budget(120, fn () => $this->page('/sitemap.xml'), 'sitemap build');
        $this->budget(4, fn () => $this->page('/sitemap-catalog.landing.xml'), 'sitemap landings');
    }

    /**
     * The budget, and the tally on the way: `WEBX_PERF_PRINT=1` prints every page's count and
     * what repeated, `2` every statement.
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
