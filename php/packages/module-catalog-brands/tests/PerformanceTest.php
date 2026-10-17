<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\Models\Brand;

/**
 * The query budget of a brand's page — the catalogue's list narrowed to the brand — of the list
 * of brands, and of the brands' file of the sitemap (WEBX_UI_PERFORMANCE_AUDIT.md §2.2).
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
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The caches on, the way a site has them, and the database as the engine.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.cache.enabled', true);
        $app['config']->set('webx-seo.cache.enabled', true);
        $app['config']->set('webx-catalog.engine', 'sql');
        $app['config']->set('webx-catalog.price.currency', 'EUR');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::seed($this->app);
    }

    #[Test]
    public function a_brand_fits_its_budget_cold_and_warm(): void
    {
        $path = $this->pathOf($this->shop->brands['northwind']->url());

        $this->cold();
        $this->budget(28, fn () => $this->page($path), 'brand cold');

        $this->budget(22, fn () => $this->page($path), 'brand warm');
        $this->assertNoRepeatedQueries(fn () => $this->page($path), 'brand warm');
    }

    #[Test]
    public function the_list_of_brands_fits_its_budget_cold_and_warm(): void
    {
        $path = $this->pathOf(Brand::listUrl());

        $this->cold();
        $this->budget(10, fn () => $this->page($path), 'brands cold');

        $this->budget(6, fn () => $this->page($path), 'brands warm');
        $this->assertNoRepeatedQueries(fn () => $this->page($path), 'brands warm');
    }

    #[Test]
    public function the_brands_file_of_the_sitemap_fits_its_budget(): void
    {
        $this->cold();
        $this->budget(75, fn () => $this->page('/sitemap.xml'), 'sitemap build');
        $this->budget(4, fn () => $this->page('/sitemap-catalog.brand.xml'), 'sitemap brands');
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
