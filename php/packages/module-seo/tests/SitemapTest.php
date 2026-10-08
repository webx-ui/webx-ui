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
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Rendering\Breadcrumbs;
use WebxUi\Seo\Sitemap\Sitemap;
use WebxUi\Seo\Sitemap\SitemapRoutes;
use WebxUi\Seo\Tests\Fixtures\MappedEntity;
use WebxUi\Seo\Tests\Fixtures\MappedPage;
use WebxUi\Seo\Tests\Fixtures\MappedRedirect;
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
    public function a_type_with_a_dot_in_its_name_is_a_file_the_index_can_reach(): void
    {
        // A module's types are namespaced (`catalog.category`), and the file is named after it.
        app(RouteTypes::class)->forget();
        app(RouteTypes::class)->register(new RouteType(type: 'catalog.mapped', model: MappedEntity::class, formatter: Slug::class));
        $this->entity('shoes');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>http://localhost/sitemap-catalog.mapped.xml</loc>', false);

        $file = (string) $this->get('/sitemap-catalog.mapped.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>http://localhost/shoes</loc>', $file);
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
    public function a_crumb_whose_address_only_redirects_is_left_out_of_the_trail(): void
    {
        app(RouteTypes::class)->register(new RouteType(type: 'mapped', model: MappedEntity::class, formatter: Slug::class, handler: MappedPage::class));
        $category = $this->entity('sauces');

        $item = new class((string) $category->url('ru')) implements HasBreadcrumbs
        {
            public function __construct(private readonly string $category) {}

            public function breadcrumbs(string $locale): array
            {
                return [new Crumb('Sauces', $this->category), new Crumb('Pesto', 'http://localhost/pesto')];
            }
        };

        $names = static fn (): array => array_map(
            static fn (Crumb $crumb): string => $crumb->title,
            app(Breadcrumbs::class)->trail($item, 'ru'),
        );

        $this->assertCount(3, $names());
        $this->assertContains('Sauces', $names());

        // The site binds a redirect over the category's page: the map leaves it out, and so do
        // the crumbs and the BreadcrumbList printed from them — the page itself stays.
        $this->app->bind(MappedPage::class, MappedRedirect::class);

        $this->assertNotContains('Sauces', $names());
        $this->assertSame('Pesto', $names()[1]);
    }

    #[Test]
    public function a_type_whose_handler_redirects_has_no_file_and_the_status_says_why(): void
    {
        app(RouteTypes::class)->register(new RouteType(type: 'mapped', model: MappedEntity::class, formatter: Slug::class, handler: MappedPage::class));
        $this->entity('about');
        $sitemap = app(Sitemap::class);

        // The module's own handler shows a page: nothing changes for a site that did nothing.
        $this->get('/sitemap.xml')->assertSee('sitemap-mapped.xml', false);
        $this->assertSame([], $sitemap->status()['excluded_types']);
        $this->assertSame(['included' => true, 'reason' => null], $sitemap->verdict('/about'));

        // The site binds its own class over the module's. That is a deploy, not a save — no
        // event moves the generation — and the map built a moment ago must not be served.
        $this->app->bind(MappedPage::class, MappedRedirect::class);

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('sitemap-mapped', false);
        $this->get('/sitemap-mapped.xml')->assertNotFound();
        $this->assertSame(['included' => false, 'reason' => 'not-a-page'], $sitemap->verdict('/about'));

        $status = $sitemap->status();
        $this->assertSame([], $status['files']);
        $this->assertSame(
            [['type' => 'mapped', 'reason' => 'not-a-page', 'handler' => MappedRedirect::class, 'addresses' => 1]],
            $status['excluded_types'],
        );

        // Taken back: the type is a page again, with no rebuild asked for.
        $this->app->bind(MappedPage::class, MappedPage::class);

        $this->get('/sitemap.xml')->assertSee('sitemap-mapped.xml', false);
        $this->assertStringContainsString('/about<', $this->file());
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

    #[Test]
    public function a_browser_is_given_a_stylesheet_that_shows_the_maps_own_text(): void
    {
        $this->entity('about');

        $index = (string) $this->get('/sitemap.xml')->getContent();
        $file = (string) $this->get('/sitemap-mapped.xml')->getContent();

        $this->assertStringContainsString('<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>', $index);
        $this->assertStringContainsString('<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>', $file);

        $xsl = (string) $this->get('/sitemap.xsl')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/xsl; charset=UTF-8')
            ->getContent();

        if (! extension_loaded('xsl')) {
            $this->markTestSkipped('ext-xsl is what runs the stylesheet here, the way a browser would.');
        }

        // What a browser would render, by the same libxslt Chrome uses: the text of the file,
        // the address a link, and nothing of the map lost on the way.
        $html = $this->transform($xsl, $file);

        $this->assertStringContainsString('<a href="http://localhost/about">http://localhost/about</a>', $html);
        $this->assertStringContainsString('&lt;urlset', $html);
        $this->assertStringContainsString('&lt;/url&gt;', $html);
        $this->assertStringContainsString('1 address<', $html);
        $this->assertStringContainsString('&lt;sitemap&gt;', $this->transform($xsl, $index));
    }

    #[Test]
    public function a_site_can_turn_the_stylesheet_off_and_the_map_names_none(): void
    {
        config()->set('webx-seo.sitemap.stylesheet', false);
        $this->entity('about');

        $this->assertStringNotContainsString('xml-stylesheet', (string) $this->get('/sitemap.xml')->getContent());
        $this->assertStringNotContainsString('xml-stylesheet', (string) $this->get('/sitemap-mapped.xml')->getContent());
    }

    private function transform(string $xsl, string $xml): string
    {
        $processor = new \XSLTProcessor;
        $stylesheet = new \DOMDocument;
        $stylesheet->loadXML($xsl);
        $processor->importStylesheet($stylesheet);
        $document = new \DOMDocument;
        $document->loadXML($xml);

        return (string) $processor->transformToXml($document);
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
