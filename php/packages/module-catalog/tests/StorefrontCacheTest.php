<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;
use WebxUi\Admin\Testing\TemporaryFiles;
use WebxUi\Cache\CacheGroups;
use WebxUi\Cache\Events\GroupRead;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\FacetResult;
use WebxUi\Catalog\Engine\SqlEngine;
use WebxUi\Catalog\Exchange\Importer;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\StorefrontCache;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\CatalogLandings\CatalogLandingsServiceProvider;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\StockServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The generation of the catalogue and the counts of the filter kept on it (WEBX_UI_MODULE_CATALOG.md
 * §8.4): a second show asks nothing, the key is the selection and not its spelling, and every road
 * a write takes moves the generation — a model of the core or of a satellite, a write without an
 * event, a bulk action, an import — once per operation.
 *
 * «A request» here is `forgetMemo()`: what the registry holds for this process goes, and what
 * the store holds stays — the next visitor of a site.
 */
final class StorefrontCacheTest extends TestCase
{
    use TemporaryFiles;

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
            CatalogLandingsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-catalog.root.enabled', true);
        $app['config']->set('webx-catalog.fields.facets', true);

        // The store is the site's before anything is built on it: the registry holds its store.
        match ($this->name()) {
            'it_works_on_the_file_store' => $this->onFile($app),
            'it_works_on_redis' => $this->onRedis($app),
            default => null,
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::seedOnce($this->app, self::class);
        $this->request();
    }

    #[Test]
    public function a_second_show_with_the_same_filter_asks_no_facet_query_and_answers_the_same(): void
    {
        $query = $this->ask(['color' => FacetValue::of(['black'])]);

        [$cold, $coldQueries] = $this->counting(fn (): array => $this->facets($query));
        $this->request();
        [$warm, $warmQueries] = $this->counting(fn (): array => $this->facets($query));

        $this->assertNotSame([], $cold);
        $this->assertSame($cold, $warm);
        $this->assertSame($this->fresh($query), $warm);
        // The page and the total are asked again; the counts — a query a facet or more — are not.
        $this->assertGreaterThan(2, $coldQueries);
        $this->assertSame(2, $warmQueries);
    }

    /**
     * The same on the stores a site has: `file`, which has no tags, and Redis. Redis only when
     * the run names one (`WEBX_TEST_REDIS_HOST`): it is shared, so the test writes under a prefix
     * of its own and deletes its own keys — never `flush()`, which is `FLUSHDB` whatever the
     * prefix.
     */
    #[Test]
    public function it_works_on_the_file_store(): void
    {
        try {
            $this->roundTrip('file');
        } finally {
            $this->app['files']->deleteDirectory((string) config('cache.stores.file.path'));
        }
    }

    #[Test]
    public function it_works_on_redis(): void
    {
        if (config('cache.default') !== 'redis') {
            $this->markTestSkipped('No Redis named for the test (WEBX_TEST_REDIS_HOST).');
        }

        try {
            Redis::connection('webx-test')->ping();
        } catch (Throwable) {
            $this->markTestSkipped('Redis named for the test does not answer.');
        }

        try {
            $this->roundTrip('redis');
        } finally {
            $this->forgetRedisKeys((string) config('cache.stores.redis.prefix'));
        }
    }

    /**
     * @param  Application  $app
     */
    protected function onFile($app): void
    {
        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file.path', sys_get_temp_dir().'/webx-test-'.getmypid().'-'.bin2hex(random_bytes(4)));
    }

    /**
     * @param  Application  $app
     */
    protected function onRedis($app): void
    {
        $host = getenv('WEBX_TEST_REDIS_HOST');

        if (! is_string($host) || $host === '' || ! extension_loaded('redis')) {
            return;
        }

        $app['config']->set('database.redis.client', 'phpredis');
        $app['config']->set('database.redis.webx-test', ['host' => $host, 'port' => (int) (getenv('WEBX_TEST_REDIS_PORT') ?: 6379), 'database' => 0]);
        $app['config']->set('cache.stores.redis', ['driver' => 'redis', 'connection' => 'webx-test', 'prefix' => 'webx-test-'.getmypid().'-'.bin2hex(random_bytes(4)).':']);
        $app['config']->set('cache.default', 'redis');
    }

    private function roundTrip(string $store): void
    {
        $this->assertSame($store, Cache::getDefaultDriver());

        $query = $this->ask(['color' => FacetValue::of(['black'])]);
        $cold = $this->facets($query);
        $this->request();
        [$warm, $queries] = $this->counting(fn (): array => $this->facets($query));

        $this->assertSame($cold, $warm);
        $this->assertSame(2, $queries, "Read from {$store}.");

        $this->shop->productOf('Shirts')->forceFill(['is_published' => false])->save();
        $this->request();

        $this->assertSame($this->fresh($query), $this->facets($query));
        $this->assertNotSame($cold, $this->facets($query));
    }

    #[Test]
    public function a_page_of_the_storefront_keeps_its_counts(): void
    {
        $this->get('/shirts')->assertOk();
        $this->request();

        $reads = [];
        Event::listen(GroupRead::class, static function (GroupRead $read) use (&$reads): void {
            if (str_starts_with($read->name, 'catalog.facets.')) {
                $reads[] = $read->source;
            }
        });

        [, $facetQueries] = $this->counting(fn () => $this->get('/shirts?sort=price_asc')->assertOk(), facetsOnly: true);

        $this->assertSame([GroupRead::STORE], $reads, 'Another sort of the same filter reads the same counts.');
        $this->assertSame(0, $facetQueries);
    }

    #[Test]
    public function the_key_is_the_selection_and_not_its_spelling(): void
    {
        $name = fn (CatalogQuery $query): string => StorefrontCache::name('facets', SqlEngine::facetsKey($query));

        $one = $this->ask(['color' => FacetValue::of(['black', 'white']), 'price' => FacetValue::range(10, 50)]);
        $same = $this->ask(['price' => FacetValue::range(50, 10), 'brand' => FacetValue::of([]), 'color' => FacetValue::of(['white', 'black'])], page: 3, sort: 'price_desc');

        $this->assertSame($name($one), $name($same), 'The order of keys and values, an empty facet, the page and the sort make no other entry.');

        foreach ([
            'another value' => $this->ask(['color' => FacetValue::of(['black'])]),
            'another range' => $this->ask(['color' => FacetValue::of(['black', 'white']), 'price' => FacetValue::range(10, 60)]),
            'another language' => $this->ask(['color' => FacetValue::of(['black', 'white']), 'price' => FacetValue::range(10, 50)], locale: 'de'),
            'a search' => $this->ask(['color' => FacetValue::of(['black', 'white']), 'price' => FacetValue::range(10, 50)], search: 'shirt'),
            'another place' => $this->ask(['color' => FacetValue::of(['black', 'white']), 'price' => FacetValue::range(10, 50)], scope: ['category' => FacetValue::of([(string) $this->shop->category('Shirts')->getKey()])]),
        ] as $label => $other) {
            $this->assertNotSame($name($one), $name($other), $label);
        }

        // A search is the words, not the blanks around them.
        $this->assertSame($name($this->ask(search: 'shirt')), $name($this->ask(search: '  shirt ')));
    }

    #[Test]
    public function languages_and_selections_do_not_mix(): void
    {
        $this->useLocales('en', 'de');
        $shirts = ['category' => FacetValue::of([(string) $this->shop->category('Shirts')->getKey()])];

        $everything = $this->facets($this->ask());
        $branch = $this->facets($this->ask(scope: $shirts));
        $german = $this->facets($this->ask(locale: 'de'));
        $this->request();

        $this->assertNotSame($everything, $branch);
        $this->assertSame($this->fresh($this->ask(scope: $shirts)), $this->facets($this->ask(scope: $shirts)));
        $this->assertSame($this->fresh($this->ask()), $this->facets($this->ask()));
        $this->assertSame($this->fresh($this->ask(locale: 'de')), $german);
    }

    /**
     * Every kind of write, each the way the site makes it, moves the generation; where the counts
     * change, the next request reads the new ones.
     *
     * @param  Closure(self): mixed  $write
     */
    #[Test]
    #[DataProvider('writes')]
    public function every_write_moves_the_generation(Closure $write, bool $counts): void
    {
        $before = $this->facets($this->ask());
        $generation = $this->generation();

        $write($this);
        $this->request();

        $this->assertNotSame($generation, $this->generation());

        if ($counts) {
            $after = $this->facets($this->ask());
            $this->assertNotSame($before, $after, 'The counts changed with the write.');
            $this->assertSame($this->fresh($this->ask()), $after);
        }
    }

    /**
     * @return array<string, array{Closure(self): mixed, bool}>
     */
    public static function writes(): array
    {
        return [
            'a product created' => [static fn (self $test) => $test->product('Another shirt', $test->shop->category('Shirts'), ['price' => 9999]), true],
            'a product edited' => [static fn (self $test) => $test->shop->productOf('Shirts')->forceFill(['price' => 9999])->save(), true],
            'a product unpublished' => [static fn (self $test) => $test->shop->productOf('Shirts')->forceFill(['is_published' => false])->save(), true],
            'a product trashed' => [static fn (self $test) => $test->shop->productOf('Shirts')->delete(), true],
            'a product restored' => [static function (self $test): void {
                $product = $test->shop->productOf('Shirts');
                Product::withoutEvents(static fn () => $product->delete());
                $test->actingAs($test->editor(), 'cms')->postJson($test->api("products/{$product->getKey()}/restore"))->assertOk();
            }, false],
            'a category hidden' => [static fn (self $test) => $test->shop->category('Shirts')->forceFill(['is_published' => false])->save(), true],
            'a category moved' => [static fn (self $test) => $test->actingAs($test->editor(), 'cms')
                ->postJson($test->api("categories/{$test->shop->category('Shirts')->getKey()}/move"), ['parent_id' => $test->shop->category('Women')->getKey()])
                ->assertOk(), false],
            'a category trashed' => [static fn (self $test) => $test->category('empty', parent: $test->shop->category('Clothing'))->delete(), false],
            'the filter settings of a category' => [static fn (self $test) => $test->actingAs($test->editor(), 'cms')
                ->putJson($test->api("categories/{$test->shop->category('Clothing')->getKey()}"), ['values' => ['facets' => [['key' => 'price', 'visible' => true]]]])
                ->assertOk(), false],
            'a property set of a category' => [static fn (self $test) => $test->actingAs($test->editor(), 'cms')
                ->putJson($test->api("categories/{$test->shop->category('Clothing')->getKey()}/properties"), ['ids' => []])
                ->assertOk(), false],
            'a property renamed' => [static fn (self $test) => $test->shop->properties['color']->forceFill(['title' => ['en' => 'Shade']])->save(), false],
            'a value renamed' => [static fn (self $test) => $test->shop->values['color']['Black']->forceFill(['title' => ['en' => 'Noir']])->save(), false],
            'a label hidden from the filter' => [static fn (self $test) => Label::query()->where('code', 'top')->firstOrFail()->forceFill(['is_visible' => false])->save(), true],
            'a brand hidden' => [static fn (self $test) => Brand::query()->where('slug', '!=', 'proseware')->firstOrFail()->forceFill(['is_visible' => false])->save(), true],
            'a stock status renamed' => [static fn (self $test) => StockStatus::query()->firstOrFail()->forceFill(['title' => ['en' => 'Renamed']])->save(), false],
            'a landing edited' => [static fn (self $test) => Landing::query()->firstOrFail()->forceFill(['h1' => ['en' => 'Renamed']])->save(), false],
            'a bulk action of a satellite' => [static fn (self $test) => $test->actingAs($test->editor(), 'cms')
                ->postJson($test->api('bulk'), ['action' => 'add-label', 'params' => ['label_id' => Label::query()->where('code', 'top')->value('id')], 'selection' => ['ids' => $test->shirts(3)]])
                ->assertOk(), true],
            'an import' => [static fn (self $test) => $test->import("sku,price\n{$test->shop->productOf('Shirts')->sku},9999\n"), true],
            'a write without an event' => [static function (self $test): void {
                $ids = $test->shirts(2);
                Product::query()->whereKey($ids)->update(['price' => 9999]);
                $test->app->make(Catalog::class)->touch($ids);
            }, true],
        ];
    }

    #[Test]
    public function a_bulk_action_and_an_import_move_it_once(): void
    {
        $generation = $this->generation();

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('bulk'), ['action' => 'unpublish', 'selection' => ['ids' => $this->shirts(5)]])
            ->assertOk()
            ->assertJsonPath('data.done', 5);

        $this->assertSame($generation + 1, $this->generation(), 'Five products, one move.');

        $rows = "sku,price\n";

        foreach ($this->shirts(4) as $n => $id) {
            $rows .= Product::query()->whereKey($id)->value('sku').','.(100 + $n)."\n";
        }

        $this->import($rows);

        $this->assertSame($generation + 2, $this->generation(), 'Four rows of one chunk, one move.');

        $this->app->make(Catalog::class)->deferTouch(function (): void {
            $this->app->make(Catalog::class)->touch([1]);
            $this->app->make(Catalog::class)->touch([2]);
            $this->shop->productOf('Shirts')->forceFill(['price' => 1])->save();
        });

        $this->assertSame($generation + 3, $this->generation(), 'The touches and the saves of a deferred batch are one move.');
    }

    #[Test]
    public function a_rolled_back_write_moves_nothing(): void
    {
        $generation = $this->generation();

        try {
            DB::transaction(function (): void {
                $this->shop->productOf('Shirts')->forceFill(['price' => 9999])->save();
                $this->app->make(Catalog::class)->touch([1]);

                throw new RuntimeException('rehearsal');
            });
        } catch (RuntimeException) {
        }

        $this->request();
        $this->assertSame($generation, $this->generation());
    }

    #[Test]
    public function switched_off_it_counts_every_time(): void
    {
        foreach (['webx-catalog.cache.enabled', 'webx-cache.enabled'] as $switch) {
            config([$switch => false]);
            $query = $this->ask(['color' => FacetValue::of(['black'])]);

            $this->facets($query);
            $this->request();
            [, $queries] = $this->counting(fn (): array => $this->facets($query));

            $this->assertGreaterThan(2, $queries, $switch);
            config([$switch => true]);
        }
    }

    #[Test]
    public function the_panel_counts_every_time(): void
    {
        $query = new CatalogQuery(locale: 'en', context: 'root', count: $this->keys(), withUnpublished: true);

        $this->engine()->search($query);
        $this->request();
        [, $queries] = $this->counting(fn () => $this->engine()->search($query));

        $this->assertGreaterThan(2, $queries);
    }

    #[Test]
    public function the_agent_resets_it_and_the_debug_bar_sees_where_it_came_from(): void
    {
        $query = $this->ask();
        $reads = [];
        Event::listen(GroupRead::class, static function (GroupRead $read) use (&$reads): void {
            if (str_starts_with($read->name, 'catalog.facets.')) {
                $reads[] = $read->source;
            }
        });

        $this->facets($query);
        $this->facets($query);
        $this->request();
        $this->facets($query);

        $generation = $this->generation();
        $admin = $this->editor();
        $admin->forceFill(['is_super' => true])->save();
        $tool = new RegistryTool($this->app->make(ToolRegistry::class)->tool('cache_reset'));
        WebxServer::actingAs($admin, 'cms')->tool($tool, ['group' => StorefrontCache::GROUP])->assertOk();
        $this->request();
        $this->facets($query);

        $this->assertNotSame($generation, $this->generation());
        $this->assertSame([GroupRead::MISS, GroupRead::MEMO, GroupRead::STORE, GroupRead::MISS], $reads);
    }

    #[Test]
    public function a_satellite_watches_its_models_through_the_core(): void
    {
        $models = $this->app->make(CacheGroups::class)->all()[StorefrontCache::GROUP]->models();

        foreach ([Category::class, Product::class, Brand::class, Label::class, StockStatus::class, Landing::class, Property::class, PropertyValue::class] as $model) {
            $this->assertContains($model, $models);
        }
    }

    /** Only the keys under the test's own prefix, found by `SCAN` and deleted by name. */
    private function forgetRedisKeys(string $prefix): void
    {
        $redis = Redis::connection('webx-test')->client();
        /** @var int|null $cursor phpredis moves it by reference; 0 is the end of the scan */
        $cursor = null;

        do {
            $keys = $redis->scan($cursor, $prefix.'*', 100);

            if (is_array($keys) && $keys !== []) {
                $redis->del($keys);
            }
        } while ($cursor !== 0 && $cursor !== null);
    }

    /** @return list<int> */
    public function shirts(int $count): array
    {
        return array_map(fn (int $n): int => (int) $this->shop->productOf('Shirts', $n)->getKey(), range(0, $count - 1));
    }

    public function import(string $csv): void
    {
        $path = $this->temporaryFile('webx-test-', '.csv');
        file_put_contents($path, $csv);
        $importer = $this->app->make(Importer::class);
        $importer->start(['path' => $path, 'name' => 'file.csv'], $importer->inspect($path, 'file.csv')['mapping'], ['key' => 'sku']);
    }

    /** The next visitor: the registry's memo goes, the store stays. */
    private function request(): void
    {
        $this->app->make(CacheGroups::class)->forgetMemo();
    }

    private function generation(): int
    {
        $this->request();

        return $this->app->make(StorefrontCache::class)->generation();
    }

    private function engine(): SqlEngine
    {
        return $this->app->make(SqlEngine::class);
    }

    /** @return list<string> */
    private function keys(): array
    {
        return array_map(static fn ($facet): string => $facet->key(), array_values($this->app->make(Facets::class)->all()));
    }

    /**
     * @param  array<string, FacetValue>  $facets
     * @param  array<string, FacetValue>  $scope
     */
    private function ask(array $facets = [], string $locale = 'en', ?string $search = null, array $scope = [], int $page = 1, string $sort = 'default'): CatalogQuery
    {
        return new CatalogQuery(locale: $locale, context: 'root', scope: $scope, facets: $facets, count: $this->keys(), search: $search, sort: $sort, page: $page);
    }

    /** @return array<string, array<string, mixed>> */
    private function facets(CatalogQuery $query): array
    {
        return array_map(static fn (FacetResult $result): array => $result->toArray(), $this->engine()->search($query)->facets);
    }

    /**
     * The same question with the cache off: what a cached answer must equal.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fresh(CatalogQuery $query): array
    {
        config(['webx-catalog.cache.enabled' => false]);

        try {
            return $this->facets($query);
        } finally {
            config(['webx-catalog.cache.enabled' => true]);
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return array{T, int}
     */
    private function counting(Closure $work, bool $facetsOnly = false): array
    {
        $count = 0;
        DB::listen(static function (QueryExecuted $query) use (&$count, $facetsOnly): void {
            if (! $facetsOnly || str_contains($query->sql, 'pairs') || str_contains($query->sql, 'facet_key')) {
                $count++;
            }
        });

        try {
            return [$work(), $count];
        } finally {
            DB::getEventDispatcher()->forget(QueryExecuted::class);
        }
    }
}
