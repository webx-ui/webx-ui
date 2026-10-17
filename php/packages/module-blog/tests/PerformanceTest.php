<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Author;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Cache\CacheGroups;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * The query budget of every public page of the blog, and the freshness of what they cache
 * (WEBX_UI_PERFORMANCE_AUDIT.md §7).
 *
 * Every budget is a ceiling the page fits under today with thirty articles, not a target, and
 * the point of each is that it does not grow with the rows: a page that asks the registry once
 * per card is caught by `assertNoRepeatedQueries()` on the warm pass whatever the count. A
 * change that needs more says why here before raising the number.
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
        $this->seedBlog();
        $admin = CmsUser::query()->create(['name' => 'Admin', 'email' => 'budget@example.test', 'password' => 'correct-horse-battery', 'is_super' => true, 'is_active' => true]);
        $id = (string) Article::query()->orderBy('id')->value('id');

        // [address, budget, whether nothing may repeat]
        foreach ([
            ['/api/cms/blog/articles', 14, true],
            ['/api/cms/blog/articles?page=2', 14, true],
            ['/api/cms/blog/articles/{id}', 20, false],
        ] as [$url, $budget, $flat]) {
            $url = str_replace('{id}', $id, $url);
            $this->actingAs($admin, 'cms')->getJson($url)->assertOk();

            $this->assertQueriesWithin($budget, fn () => $this->actingAs($admin, 'cms')->getJson($url)->assertOk(), $url);

            if ($flat) {
                $this->assertNoRepeatedQueries(fn () => $this->actingAs($admin, 'cms')->getJson($url)->assertOk(), $url);
            }
        }

        foreach ([
            ['articles_list', 12],
        ] as [$tool, $budget]) {
            $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
            $this->assertQueriesWithin($budget, fn () => WebxServer::actingAs($admin, 'cms')->tool($bound, [])->assertOk(), $tool);
            $this->assertNoRepeatedQueries(fn () => WebxServer::actingAs($admin, 'cms')->tool($bound, [])->assertOk(), $tool);
        }
    }

    private const ARTICLES = 30;

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
        $app['config']->set('webx-blog.authors', true);
    }

    #[Test]
    public function the_feed_fits_its_budget_cold_and_warm(): void
    {
        $this->seedBlog();

        $this->cold();
        $this->assertQueriesWithin(30, fn () => $this->get('/blog')->assertOk());

        $this->assertQueriesWithin(12, fn () => $this->get('/blog')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/blog')->assertOk());
    }

    #[Test]
    public function an_article_fits_its_budget_cold_and_warm(): void
    {
        $this->seedBlog();

        $this->cold();
        $this->assertQueriesWithin(36, fn () => $this->get('/blog/article-1')->assertOk());

        $this->assertQueriesWithin(24, fn () => $this->get('/blog/article-1')->assertOk());
    }

    #[Test]
    public function a_rubric_a_tag_and_an_author_fit_their_budgets(): void
    {
        $this->seedBlog();

        foreach (['/blog/news', '/blog/tag/tips', '/blog/author/anna'] as $path) {
            $this->cold();
            $this->assertQueriesWithin(30, fn () => $this->get($path)->assertOk(), "{$path} cold");

            $this->assertQueriesWithin(14, fn () => $this->get($path)->assertOk(), "{$path} warm");
            $this->assertNoRepeatedQueries(fn () => $this->get($path)->assertOk(), "{$path} warm");
        }
    }

    #[Test]
    public function the_rss_feed_and_the_sitemap_fit_their_budgets(): void
    {
        $this->seedBlog();

        $this->cold();
        $this->assertQueriesWithin(14, fn () => $this->get('/blog/rss')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/blog/rss')->assertOk());

        // The build: every type of the blog, chunked, with its covers and its history in bulk.
        $this->cold();
        $this->assertQueriesWithin(40, fn () => $this->get('/sitemap.xml')->assertOk());
        // Served from the cache: nothing but the request's own infrastructure.
        $this->assertQueriesWithin(4, fn () => $this->get('/sitemap-article.xml')->assertOk());
    }

    /**
     * The feed, the article and the registry are cached on versioned keys; a save through the
     * panel moves their groups, and the next request — in the same process, the way a worker
     * serves it — reads the new rows. The newest article is the one on the first page.
     */
    #[Test]
    public function a_page_shows_a_change_made_through_the_panel_at_once(): void
    {
        $this->seedBlog();
        $editor = $this->editor();
        $newest = 'article-'.self::ARTICLES;
        $title = 'Article '.self::ARTICLES;

        $this->get('/blog')->assertOk()->assertSee($title);
        $this->get('/blog/'.$newest)->assertOk()->assertSee($title);

        $article = Article::query()->where('slug->en', $newest)->firstOrFail();
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($article->getKey()), ['values' => ['title' => ['en' => 'The newest, renamed'], 'slug' => ['en' => 'the-newest']]])
            ->assertOk();
        $this->actingAs($editor, 'cms')->postJson($this->api($article->getKey().'/publish'))->assertOk();

        $this->get('/blog')->assertOk()->assertSee('The newest, renamed')->assertDontSee('>'.$title.'<', false);
        $this->get('/blog/the-newest')->assertOk()->assertSee('The newest, renamed');
        $this->get('/blog/'.$newest)->assertRedirect('/blog/the-newest');
    }

    /**
     * Two rubrics, five tags, an author with a page and thirty published articles with a cover,
     * a rubric, two tags and a couple of related ones each.
     */
    private function seedBlog(): void
    {
        $news = $this->rubric('news');
        $guides = $this->rubric('guides');
        $tags = array_map(fn (string $slug): Tag => $this->tag($slug, noindex: false), ['tips', 'tools', 'travel', 'food', 'home']);
        $anna = $this->author('anna');
        $cover = $this->picture();

        $articles = [];

        for ($i = 1; $i <= self::ARTICLES; $i++) {
            $article = new Article([
                'title' => "Article {$i}",
                'slug' => "article-{$i}",
                'lead' => "The lead of article {$i}.",
                'cover_id' => $cover->getKey(),
                'author_id' => $anna->user_id,
            ]);
            $article->save();
            $article->syncCategories([($i % 2 === 0 ? $news : $guides)->getKey()]);
            $article->tags()->attach([$tags[$i % 5]->getKey(), $tags[($i + 1) % 5]->getKey()]);
            $article->publish();
            $articles[] = $article;
        }

        foreach ($articles as $i => $article) {
            $article->related()->attach([
                $articles[($i + 1) % self::ARTICLES]->getKey() => ['position' => 1],
                $articles[($i + 2) % self::ARTICLES]->getKey() => ['position' => 2],
            ]);
        }
    }

    private function author(string $slug): Author
    {
        $user = CmsUser::query()->create([
            'name' => ucfirst($slug),
            'email' => "{$slug}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        return Author::query()->create(['user_id' => $user->getKey(), 'title' => ucfirst($slug), 'slug' => $slug, 'is_public' => true]);
    }

    private function picture(): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/cover.jpg',
            'hash' => str_repeat('a', 32),
            'name' => 'cover',
            'file_name' => 'cover.jpg',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
        ]);
    }

    /** The first visitor after a deploy: an empty store, and nothing read in this process either. */
    private function cold(): void
    {
        Cache::flush();
        $this->app->make(CacheGroups::class)->forgetMemo();
    }
}
