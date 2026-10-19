<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cache\CacheGroups;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\RouteCache;
use WebxUi\Routing\Tests\Fixtures\Article;
use WebxUi\Routing\Tests\Fixtures\Category;

/**
 * The addresses of a list: the request's memory, then one `many()` of the cache, then one query
 * a type for what the cache did not have — and a changed address read back at once.
 */
final class RouteCacheTest extends TestCase
{
    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['url']->forceRootUrl('https://example.test');
        $this->app['url']->forceScheme('https');
    }

    #[Test]
    public function a_cold_list_is_one_query_a_type_and_an_entity_asked_twice_is_not_asked_again(): void
    {
        [$articles, $categories] = $this->listing();
        $expected = $this->urls($articles, $categories);

        $this->nextRequest(cold: true);
        [$articles, $categories] = $this->reloaded($articles, $categories);

        $queries = $this->routeQueries(function () use ($articles, $categories): void {
            $this->app->make(RouteCache::class)->preload([...$articles, ...$categories, ...$categories]);
            $this->urls($articles, $categories);
            // The same categories on every card: asked again, they are not asked of the registry.
            $this->urls($articles, $categories);
        });

        $this->assertSame(2, $queries, 'one query for the articles, one for the categories');
        $this->assertSame($expected, $this->urls($articles, $categories));
    }

    #[Test]
    public function without_a_preload_the_same_entity_is_still_asked_once_a_request(): void
    {
        $category = Category::create(['name' => 'Belts', 'slug' => 'belts']);

        $this->nextRequest(cold: true);
        $category = Category::query()->findOrFail($category->getKey());

        $queries = $this->routeQueries(static function () use ($category): void {
            for ($card = 0; $card < 5; $card++) {
                $category->url();
            }
        });

        $this->assertSame(1, $queries);
    }

    #[Test]
    public function a_warm_list_asks_the_registry_nothing_and_the_cache_once(): void
    {
        [$articles, $categories] = $this->listing();
        $expected = $this->urls($articles, $categories);

        $this->nextRequest(cold: true);
        [$articles, $categories] = $this->reloaded($articles, $categories);
        $this->app->make(RouteCache::class)->preload([...$articles, ...$categories]);
        $this->urls($articles, $categories);

        $this->nextRequest();
        [$articles, $categories] = $this->reloaded($articles, $categories);

        $reads = 0;
        Event::listen(RetrievingManyKeys::class, static function (RetrievingManyKeys $event) use (&$reads): void {
            if (str_starts_with($event->keys[0] ?? '', 'webx:route:')) {
                $reads++;
            }
        });

        $queries = $this->routeQueries(function () use ($articles, $categories): void {
            $this->app->make(RouteCache::class)->preload([...$articles, ...$categories]);
            $this->urls($articles, $categories);
        });

        $this->assertSame(0, $queries);
        $this->assertSame(1, $reads, 'the addresses of the whole list in one many()');
        $this->assertSame($expected, $this->urls($articles, $categories));
    }

    #[Test]
    public function an_entity_without_an_address_is_not_asked_about_again(): void
    {
        $category = Category::create(['name' => 'Belts', 'slug' => 'belts']);
        Route::query()->forEntity($category)->delete();
        $this->app->make(CacheGroups::class)->bump(CacheGroups::ROUTES);

        $this->nextRequest(cold: true);
        $this->assertSame(1, $this->routeQueries(static fn () => $category->fresh()?->url()));

        $this->nextRequest();
        $this->assertSame(0, $this->routeQueries(static fn () => $category->fresh()?->url()));
    }

    #[Test]
    public function a_new_slug_is_the_next_address_in_this_request_and_the_next(): void
    {
        $category = Category::create(['name' => 'Belts', 'slug' => 'belts']);

        $this->nextRequest(cold: true);
        $this->assertSame('https://example.test/belts', Category::query()->findOrFail($category->getKey())->url());

        $category = Category::query()->findOrFail($category->getKey());
        $category->slug = 'drive-belts';
        $category->save();

        // Another copy of the entity, as another part of the page holds it.
        $this->assertSame('https://example.test/drive-belts', Category::query()->findOrFail($category->getKey())->url());

        $this->nextRequest();
        $this->assertSame('https://example.test/drive-belts', Category::query()->findOrFail($category->getKey())->url());
    }

    #[Test]
    public function a_rebuild_moves_every_cached_address(): void
    {
        $category = Category::create(['name' => 'Parts', 'slug' => 'parts']);
        $this->nextRequest(cold: true);
        $this->assertSame('https://example.test/parts', Category::query()->findOrFail($category->getKey())->url());

        // Written around the model, the way a restored dump or a script does it: nothing told
        // the cache, and the rebuild is the repair.
        DB::table('routes')->where('entity_type', 'category')->update(['path' => 'spare-parts']);
        $this->nextRequest();
        $this->assertSame('https://example.test/parts', Category::query()->findOrFail($category->getKey())->url());

        DB::table('categories')->where('id', $category->getKey())->update(['slug' => 'spare-parts']);
        $this->artisan('webx:routes:rebuild')->assertSuccessful();

        $this->nextRequest();
        $this->assertSame('https://example.test/spare-parts', Category::query()->findOrFail($category->getKey())->url());
    }

    #[Test]
    public function the_cached_addresses_keep_their_language_prefixes(): void
    {
        $this->useLocales(['en', 'ru', 'pl']);
        $category = Category::create(['name' => 'Belts', 'slug' => 'belts']);

        $expected = [];
        foreach (['en', 'ru', 'pl'] as $locale) {
            $expected[$locale] = Category::query()->findOrFail($category->getKey())->load('routes')->url($locale);
        }

        $this->assertSame(['en' => 'https://example.test/belts', 'ru' => 'https://example.test/ru/belts', 'pl' => 'https://example.test/pl/belts'], $expected);

        foreach ([true, false] as $cold) {
            $this->nextRequest(cold: $cold);
            $fresh = Category::query()->findOrFail($category->getKey());

            foreach ($expected as $locale => $url) {
                $this->assertSame($url, $fresh->url($locale));
            }
        }
    }

    /** @return array{list<Article>, list<Category>} three articles and the two categories they share */
    private function listing(): array
    {
        $articles = [];

        foreach (['belts', 'filters', 'brakes'] as $slug) {
            $articles[] = Article::create(['title' => ucfirst($slug), 'slug' => $slug]);
        }

        return [$articles, [Category::create(['name' => 'Repairs', 'slug' => 'repairs']), Category::create(['name' => 'Parts', 'slug' => 'parts'])]];
    }

    /**
     * Fresh copies, as the next request loads them: nothing in memory from this one.
     *
     * @param  list<Article>  $articles
     * @param  list<Category>  $categories
     * @return array{list<Article>, list<Category>}
     */
    private function reloaded(array $articles, array $categories): array
    {
        return [
            array_values(Article::query()->whereKey(array_map(static fn (Article $a): mixed => $a->getKey(), $articles))->orderBy('id')->get()->all()),
            array_values(Category::query()->whereKey(array_map(static fn (Category $c): mixed => $c->getKey(), $categories))->orderBy('id')->get()->all()),
        ];
    }

    /**
     * Every card prints its article and both categories.
     *
     * @param  list<Article>  $articles
     * @param  list<Category>  $categories
     * @return list<string>
     */
    private function urls(array $articles, array $categories): array
    {
        $urls = [];

        foreach ($articles as $article) {
            $urls[] = $article->url();

            foreach ($categories as $category) {
                $urls[] = $category->url();
            }
        }

        return $urls;
    }

    private function routeQueries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return count(array_filter($log, static fn (array $query): bool => str_contains($query['query'], 'from "routes"')));
    }

    /** What a worker does between two requests — and, cold, the store of a fresh deploy too. */
    private function nextRequest(bool $cold = false): void
    {
        if ($cold) {
            Cache::flush();
        }

        // The scoped memory of the addresses, as Octane and the queue worker drop it.
        $this->app->forgetInstance(RouteCache::class);
        $this->app->make(CacheGroups::class)->forgetMemo();
    }
}
