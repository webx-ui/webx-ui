<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Author;
use WebxUi\Blog\Support\PublishedAuthors;
use WebxUi\Cache\CacheGroups;
use WebxUi\Routing\RouteCache;

/**
 * What the page of an author asks the database: «has something published» once, however many
 * parts of the page ask it, and the `hreflang` lines out of `RouteCache` like `url()`.
 *
 * Its own class for the languages: the localised routes are registered while the providers boot.
 */
final class AuthorQueriesTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-blog.authors', true);
        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'ru'],
            ['code' => 'pl'],
        ]);
        $app['config']->set('webx-localization.strategy', 'prefix');
    }

    #[Test]
    public function the_page_asks_whether_the_author_has_articles_once_and_a_warm_one_reads_no_addresses(): void
    {
        $anna = $this->profile('anna');
        $this->articleBy($anna, 'changing-a-belt');

        $this->nextRequest();
        [$page, $queries] = $this->logged(fn (): string => (string) $this->get('/ru/blog/author/anna-ru')->assertOk()->getContent());

        $this->assertCount(1, $this->hasArticles($queries), self::listed($queries));
        $this->assertStringNotContainsString('noindex', $page);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="http://localhost/blog/author/anna">', $page);
        $this->assertStringContainsString('<link rel="alternate" hreflang="ru" href="http://localhost/ru/blog/author/anna-ru">', $page);
        $this->assertStringContainsString('<link rel="alternate" hreflang="pl" href="http://localhost/pl/blog/author/anna-pl">', $page);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="http://localhost/blog/author/anna">', $page);

        // The next request: the addresses of the author come out of the site's cache.
        $this->nextRequest();
        [$warm, $queries] = $this->logged(fn (): string => (string) $this->get('/ru/blog/author/anna-ru')->assertOk()->getContent());

        $this->assertSame([], $this->authorRoutes($queries), self::listed($queries));
        $this->assertCount(1, $this->hasArticles($queries));
        $this->assertSame($page, $warm);
    }

    #[Test]
    public function an_author_with_nothing_published_is_still_out_of_the_index(): void
    {
        $this->profile('anna');

        $this->nextRequest();
        [$page, $queries] = $this->logged(fn (): string => (string) $this->get('/blog/author/anna')->assertOk()->getContent());

        $this->assertCount(1, $this->hasArticles($queries), self::listed($queries));
        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $page);
        // Not visible, so no `hreflang` either: the map and the `<head>` name the same set.
        $this->assertStringNotContainsString('hreflang', $page);
    }

    #[Test]
    public function a_map_of_three_authors_asks_it_with_the_list(): void
    {
        foreach (['anna', 'boris', 'clara'] as $slug) {
            $this->articleBy($this->profile($slug), "by-{$slug}");
        }

        $this->nextRequest();
        [$map, $queries] = $this->logged(fn (): string => (string) $this->get('/sitemap-author.xml')->assertOk()->getContent());

        $this->assertSame([], $this->hasArticles($queries), self::listed($queries));
        $this->assertStringContainsString('/pl/blog/author/clara-pl<', $map);
        $this->assertSame(9, substr_count($map, '<url>'));
    }

    private function profile(string $slug): Author
    {
        $user = CmsUser::query()->create([
            'name' => ucfirst($slug),
            'email' => "{$slug}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        return Author::query()->create([
            'user_id' => $user->getKey(),
            'title' => ucfirst($slug),
            'slug' => ['en' => $slug, 'ru' => "{$slug}-ru", 'pl' => "{$slug}-pl"],
            'is_public' => true,
        ]);
    }

    private function articleBy(Author $author, string $slug): void
    {
        $article = new Article(['title' => ucfirst($slug), 'slug' => $slug, 'author_id' => $author->user_id]);
        $article->save();
        $article->publish();
    }

    /** What a worker does between two requests: the scoped memory goes, the store stays. */
    private function nextRequest(): void
    {
        $this->app->forgetInstance(RouteCache::class);
        $this->app->forgetInstance(PublishedAuthors::class);
        $this->app->make(CacheGroups::class)->forgetMemo();
    }

    /**
     * @param  callable(): string  $work
     * @return array{string, list<array{query: string, bindings: array<mixed>}>}
     */
    private function logged(callable $work): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = $work();
        /** @var list<array{query: string, bindings: array<mixed>}> $queries */
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return [$result, $queries];
    }

    /**
     * «Has something published», asked by itself — not the `withExists` of a list.
     *
     * @param  list<array{query: string, bindings: array<mixed>}>  $queries
     * @return list<string>
     */
    private function hasArticles(array $queries): array
    {
        return array_values(array_filter(array_column($queries, 'query'), static fn (string $sql): bool => str_starts_with($sql, 'select exists(select * from "blog_articles"')
            || str_starts_with($sql, 'select distinct "author_id" from "blog_articles"')));
    }

    /**
     * The registry rows of an author — what `url()`, `hreflang` and the switcher read; not the
     * resolver's lookup of the path, nor the addresses of the article cards.
     *
     * @param  list<array{query: string, bindings: array<mixed>}>  $queries
     * @return list<string>
     */
    private function authorRoutes(array $queries): array
    {
        $type = (new Author)->getMorphClass();

        return array_values(array_column(array_filter($queries, static fn (array $query): bool => str_contains($query['query'], 'from "routes"')
            && in_array($type, $query['bindings'], true)), 'query'));
    }

    /**
     * @param  list<array{query: string, bindings: array<mixed>}>  $queries
     */
    private static function listed(array $queries): string
    {
        return implode("\n", array_column($queries, 'query'));
    }
}
