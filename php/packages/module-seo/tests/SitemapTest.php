<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Sitemap\SitemapRoutes;
use WebxUi\Seo\Tests\Fixtures\MappedEntity;
use WebxUi\Settings\Settings;

/**
 * The sitemap takes its addresses from the registry and its verdicts from the SEO resolver
 * (§17.1, decisions 2 and 4). Every test here is one of the ways a page can be closed, checked
 * against the map rather than against the `<head>` — the point being that nobody told the map.
 */
final class SitemapTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // In memory, so the generation and the built files are what this test wrote and not
        // what a `.env` in the testbench skeleton says the store is (CLAUDE.md §4).
        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('mapped_entities', function (Blueprint $table): void {
            $table->id();
            $table->json('slug')->nullable();
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        app(RouteTypes::class)->register(new RouteType(type: 'mapped', model: MappedEntity::class, formatter: Slug::class));
    }

    #[Test]
    public function what_is_published_is_in_the_map_and_a_draft_is_not(): void
    {
        $this->entity('about');
        $this->entity('draft', published: false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>http://localhost/sitemap-mapped.xml</loc>', false);

        $file = $this->get('/sitemap-mapped.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>http://localhost/about</loc>', (string) $file);
        $this->assertStringContainsString('<lastmod>', (string) $file);
        $this->assertStringNotContainsString('draft', (string) $file);
    }

    #[Test]
    public function a_card_that_says_noindex_takes_the_page_out(): void
    {
        $this->entity('open');
        $this->entity('closed')->saveSeo(['robots' => 'noindex, follow']);

        $file = $this->file();

        $this->assertStringContainsString('/open<', $file);
        $this->assertStringNotContainsString('/closed<', $file);
    }

    #[Test]
    public function a_mask_rule_with_noindex_takes_out_every_address_it_covers(): void
    {
        $this->entity('open');
        $this->entity('filter-red');
        $this->entity('filter-blue');

        SeoUrl::query()->create(['match_type' => 'mask', 'pattern' => '/filter-*', 'robots' => 'noindex']);

        $file = $this->file();

        $this->assertStringContainsString('/open<', $file);
        $this->assertStringNotContainsString('filter-', $file);
    }

    #[Test]
    public function a_page_whose_canonical_names_another_address_is_left_to_that_one(): void
    {
        $this->entity('copy')->saveSeo(['canonical' => 'http://localhost/original']);
        $this->entity('itself')->saveSeo(['canonical' => 'http://localhost/itself']);
        $this->entity('elsewhere')->saveSeo(['canonical' => 'https://other.test/elsewhere']);

        $file = $this->file();

        $this->assertStringNotContainsString('/copy<', $file);
        $this->assertStringNotContainsString('/elsewhere<', $file);
        // A canonical written out in full that names the page itself is the page agreeing.
        $this->assertStringContainsString('/itself<', $file);
    }

    #[Test]
    public function every_language_is_an_address_and_a_missing_slug_is_none(): void
    {
        $this->entity(['ru' => 'o-nas', 'uk' => 'pro-nas']);
        $this->entity(['ru' => 'tolko-ru']);

        $file = $this->file();

        $this->assertStringContainsString('<loc>http://localhost/o-nas</loc>', $file);
        $this->assertStringContainsString('<loc>http://localhost/uk/pro-nas</loc>', $file);
        $this->assertStringContainsString('<loc>http://localhost/tolko-ru</loc>', $file);
        $this->assertSame(3, substr_count($file, '<url>'));
    }

    #[Test]
    public function a_type_past_the_limit_is_cut_into_numbered_files(): void
    {
        config()->set('webx-seo.sitemap.per_file', 2);

        foreach (['a', 'b', 'c'] as $slug) {
            $this->entity($slug);
        }

        $this->get('/sitemap.xml')
            ->assertSee('sitemap-mapped-1.xml', false)
            ->assertSee('sitemap-mapped-2.xml', false)
            ->assertDontSee('sitemap-mapped.xml', false);

        $this->assertSame(2, substr_count((string) $this->get('/sitemap-mapped-1.xml')->assertOk()->getContent(), '<url>'));
        $this->assertSame(1, substr_count((string) $this->get('/sitemap-mapped-2.xml')->assertOk()->getContent(), '<url>'));
        $this->get('/sitemap-mapped.xml')->assertNotFound();
    }

    #[Test]
    public function saving_what_the_map_depends_on_throws_the_built_one_away(): void
    {
        $this->entity('first');
        $this->assertStringNotContainsString('/second<', $this->file());

        // An entity saved: a new generation.
        $this->entity('second');
        $this->assertStringContainsString('/second<', $this->file());

        // A rule saved: the same.
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/second', 'robots' => 'noindex']);
        $this->assertStringNotContainsString('/second<', $this->file());

        // An `seo.*` setting saved: the same — the defaults are a source too. The entity is
        // opened behind the map's back first, so that only the setting can be what rebuilt it.
        $this->entity('third', published: false);
        $this->assertStringNotContainsString('/third<', $this->file());
        MappedEntity::query()->update(['published' => true]);
        $this->assertStringNotContainsString('/third<', $this->file());

        app(Settings::class)->save(['seo.robots-txt' => 'User-agent: *']);
        $this->assertStringContainsString('/third<', $this->file());
    }

    #[Test]
    public function what_changes_without_a_save_arrives_with_the_ttl(): void
    {
        $this->entity('waiting', published: false);
        $this->assertStringNotContainsString('/waiting<', $this->file());

        // Nothing announces this, the way nothing announces an article's date arriving.
        MappedEntity::query()->update(['published' => true]);
        $this->assertStringNotContainsString('/waiting<', $this->file());

        $this->travel(86401)->seconds();

        $this->assertStringContainsString('/waiting<', $this->file());
    }

    #[Test]
    public function the_command_builds_the_whole_map_ahead_of_the_first_request(): void
    {
        $this->entity('about');

        $this->artisan('webx:seo:sitemap')
            ->expectsOutputToContain('sitemap-mapped.xml')
            ->assertSuccessful();

        // Built and kept: a change nothing announced is not in it, which is how it shows that
        // the request was answered from the cache and not by building.
        MappedEntity::query()->update(['published' => false]);

        $this->assertStringContainsString('/about<', $this->file());
    }

    #[Test]
    public function a_named_route_a_module_asked_for_is_in_the_map_in_every_language(): void
    {
        Route::get('news', static fn (): string => 'news')->name('test.news');
        Route::get('closed-news', static fn (): string => 'closed')->name('test.closed');
        Route::get('news/{slug}', static fn (): string => 'one')->name('test.one');

        $routes = app(SitemapRoutes::class);
        $routes->register('test.news');
        $routes->register('test.closed');
        $routes->register('test.one');
        $routes->register('test.nothing');

        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/closed-news', 'robots' => 'noindex']);

        $file = (string) $this->get('/sitemap-routes.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>http://localhost/news</loc>', $file);
        $this->assertStringContainsString('<loc>http://localhost/uk/news</loc>', $file);
        $this->assertStringNotContainsString('<loc>http://localhost/closed-news</loc>', $file);
        // A route with parameters is a family of addresses, not one.
        $this->assertStringNotContainsString('{slug}', $file);
    }

    #[Test]
    public function robots_txt_names_the_map_once(): void
    {
        app(Settings::class)->save(['seo.robots-txt' => "User-agent: *\nDisallow: /cms"]);

        $body = (string) $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString("Disallow: /cms\n\nSitemap: http://localhost/sitemap.xml", $body);

        // Somebody wrote the line themselves, to a map of their own: theirs stands, alone.
        app(Settings::class)->save(['seo.robots-txt' => "User-agent: *\nsitemap: https://cdn.example.test/map.xml"]);

        $body = (string) $this->get('/robots.txt')->getContent();

        $this->assertSame(1, substr_count(mb_strtolower($body), 'sitemap:'));

        config()->set('webx-seo.sitemap.enabled', false);
        app(Settings::class)->save(['seo.robots-txt' => 'User-agent: *']);

        $this->assertStringNotContainsString('Sitemap:', (string) $this->get('/robots.txt')->getContent());
    }

    /**
     * @param  string|array<string, string>  $slug
     */
    private function entity(string|array $slug, bool $published = true): MappedEntity
    {
        return MappedEntity::query()->create([
            'slug' => is_string($slug) ? ['ru' => $slug] : $slug,
            'published' => $published,
        ]);
    }

    private function file(): string
    {
        $response = $this->get('/sitemap-mapped.xml');

        return $response->getStatusCode() === 404 ? '' : (string) $response->getContent();
    }
}
