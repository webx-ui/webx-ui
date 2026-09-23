<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;
use WebxUi\Seo\Rendering\SeoSources;
use WebxUi\Seo\Sitemap\SitemapRoutes;
use WebxUi\Seo\Tests\Fixtures\CrumbedEntity;
use WebxUi\Settings\Settings;

/**
 * What the `<head>` of an entity says on top of its card (§17.4): its other languages, its
 * trail, its schema.org blocks and the handler's, the Twitter card — and the panel's view of the
 * map (§17.6). The site speaks Russian by default and Ukrainian under `/uk`.
 */
final class EntityHeadTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crumbed_entities', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('slug')->nullable();
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        app(RouteTypes::class)->register(new RouteType(type: 'crumbed', model: CrumbedEntity::class, formatter: Slug::class));
    }

    #[Test]
    public function the_trail_starts_at_home_and_the_visible_crumbs_are_the_same_list(): void
    {
        $entity = $this->entity(['ru' => 'О нас', 'uk' => 'Про нас'], ['ru' => 'about', 'uk' => 'pro-nas']);

        $trail = $this->trailOf($this->headOf($entity));

        $this->assertSame(['Главная', 'Section', 'О нас'], array_column($trail, 'name'));
        $this->assertSame([1, 2, 3], array_column($trail, 'position'));
        // A step without an address keeps its place and loses only `item`.
        $this->assertArrayNotHasKey('item', $trail[1]);
        $this->assertStringEndsWith('/about', (string) $trail[2]['item']);

        $crumbs = Blade::render('<x-webx-seo::breadcrumbs :for="$entity" locale="ru" />', ['entity' => $entity]);

        $this->assertStringContainsString('aria-label="Хлебные крошки"', $crumbs);
        $this->assertStringContainsString('>Главная</a>', $crumbs);
        $this->assertStringContainsString('<li>Section</li>', $crumbs);
        $this->assertStringContainsString('<li aria-current="page">О нас</li>', $crumbs);
    }

    #[Test]
    public function the_home_step_is_named_by_the_setting_in_the_language_of_the_page(): void
    {
        $entity = $this->entity(['ru' => 'О нас', 'uk' => 'Про нас'], ['ru' => 'about', 'uk' => 'pro-nas']);

        $this->assertSame('Головна', $this->trailOf($this->headOf($entity, locale: 'uk'))[0]['name']);

        app(Settings::class)->save(['seo.home-crumb' => ['ru' => 'Начало']]);

        $this->assertSame('Начало', $this->trailOf($this->headOf($entity))[0]['name']);
        $this->assertStringContainsString('>Начало</a>', Blade::render('<x-webx-seo::breadcrumbs :for="$entity" locale="ru" />', ['entity' => $entity]));
    }

    #[Test]
    public function quotes_and_angle_brackets_in_a_title_do_not_leave_the_script_block(): void
    {
        $entity = $this->entity(['ru' => 'Say "hi" </script><script>alert(1)</script>'], ['ru' => 'hi']);

        $head = $this->headOf($entity);

        $this->assertStringNotContainsString('<script>alert(1)', $head);
        $this->assertStringContainsString('\u003C/script\u003E', $head);
        $this->assertSame('Say "hi" </script><script>alert(1)</script>', $this->trailOf($head)[2]['name']);

        $crumbs = Blade::render('<x-webx-seo::breadcrumbs :for="$entity" locale="ru" />', ['entity' => $entity]);

        $this->assertStringNotContainsString('<script>', $crumbs);
    }

    #[Test]
    public function the_entity_and_the_handler_add_their_blocks_and_a_push_lives_for_one_request(): void
    {
        $entity = $this->entity(['ru' => 'О нас'], ['ru' => 'about']);

        Route::get('/pushed/{push}', static function (string $push) use ($entity): string {
            if ($push === 'yes') {
                app(Seo::class)->push(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'On this page']);
            }

            return (string) app(Seo::class)->head($entity, '/about', 'ru');
        });

        $with = (string) $this->get('/pushed/yes')->getContent();

        $this->assertContains('Thing', $this->types($with));
        $this->assertContains('ItemList', $this->types($with));
        $this->assertSame(['BreadcrumbList', 'Thing', 'ItemList'], array_values(array_diff($this->types($with), ['Organization', 'WebSite'])));

        $this->assertNotContains('ItemList', $this->types((string) $this->get('/pushed/no')->getContent()));
    }

    #[Test]
    public function every_new_part_of_the_head_can_be_turned_off(): void
    {
        $entity = $this->entity(['ru' => 'О нас', 'uk' => 'Про нас'], ['ru' => 'about', 'uk' => 'pro-nas']);

        config()->set('webx-seo.print', ['hreflang' => false, 'breadcrumbs' => false, 'structured_data' => false, 'twitter' => false]);

        $head = $this->headOf($entity);

        $this->assertStringNotContainsString('hreflang', $head);
        $this->assertStringNotContainsString('twitter:card', $head);
        $this->assertNotContains('BreadcrumbList', $this->types($head));
        $this->assertNotContains('Thing', $this->types($head));
    }

    #[Test]
    public function twitter_takes_the_large_card_only_when_there_is_a_picture(): void
    {
        $entity = $this->entity(['ru' => 'О нас'], ['ru' => 'about']);

        $this->assertStringContainsString('<meta name="twitter:card" content="summary">', $this->headOf($entity));

        app(SeoSources::class)->register(new class implements SeoSource
        {
            public function priority(): int
            {
                return 1;
            }

            public function forUrl(string $url, ?object $subject = null, ?string $locale = null): SeoData
            {
                return SeoData::make(['og' => ['image' => 'https://example.test/cover.jpg']]);
            }
        });

        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $this->headOf($entity));
    }

    #[Test]
    public function an_entity_names_itself_in_every_language_it_has_an_address_in(): void
    {
        $entity = $this->entity(['ru' => 'О нас', 'uk' => 'Про нас'], ['ru' => 'about', 'uk' => 'pro-nas']);

        $links = $this->alternates($this->headOf($entity));

        $this->assertSame(['ru', 'uk', 'x-default'], array_keys($links));
        $this->assertStringEndsWith('/about', $links['ru']);
        $this->assertStringEndsWith('/uk/pro-nas', $links['uk']);
        $this->assertSame($links['ru'], $links['x-default']);

        // The same set from the Ukrainian page.
        $this->assertSame($links, $this->alternates($this->headOf($entity, '/uk/pro-nas', 'uk')));
    }

    #[Test]
    public function a_language_without_a_slug_or_closed_to_the_index_is_no_alternate(): void
    {
        $untranslated = $this->entity(['ru' => 'Только по-русски'], ['ru' => 'only-ru']);

        $this->assertSame([], $this->alternates($this->headOf($untranslated)));

        $closed = $this->entity(['ru' => 'О нас', 'uk' => 'Про нас'], ['ru' => 'about', 'uk' => 'pro-nas']);
        SeoUrl::query()->create(['match_type' => UrlMatcher::EXACT, 'pattern' => '/uk/pro-nas', 'robots' => 'noindex']);

        $this->assertSame([], $this->alternates($this->headOf($closed)));
    }

    #[Test]
    public function with_the_language_in_a_header_there_are_no_alternates_at_all(): void
    {
        config()->set('webx-localization.strategy', 'header');

        $entity = $this->entity(['ru' => 'О нас', 'uk' => 'Про нас'], ['ru' => 'about', 'uk' => 'pro-nas']);

        $this->assertSame([], $this->alternates($this->headOf($entity)));
        $this->assertStringNotContainsString('xhtml:link', (string) $this->get('/sitemap-crumbed.xml')->getContent());
    }

    #[Test]
    public function an_address_without_an_entity_is_the_same_path_under_every_prefix(): void
    {
        $links = $this->alternates((string) app(Seo::class)->head(null, '/uk/catalog?page=2&utm_source=mail', 'uk'));

        $this->assertStringEndsWith('/catalog?page=2', $links['ru']);
        $this->assertStringEndsWith('/uk/catalog?page=2', $links['uk']);
        $this->assertSame($links['ru'], $links['x-default']);
    }

    #[Test]
    public function the_map_lists_the_same_alternates_as_the_head(): void
    {
        $this->entity(['ru' => 'О нас', 'uk' => 'Про нас'], ['ru' => 'about', 'uk' => 'pro-nas']);
        $this->entity(['ru' => 'Только по-русски'], ['ru' => 'only-ru']);

        $file = (string) $this->get('/sitemap-crumbed.xml')->getContent();

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $file);
        $this->assertSame(2, substr_count($file, 'hreflang="uk" href="http://localhost/uk/pro-nas"'));
        $this->assertSame(2, substr_count($file, 'hreflang="x-default" href="http://localhost/about"'));
        $this->assertStringNotContainsString('hreflang="uk" href="http://localhost/uk/only-ru"', $file);

        preg_match('#<url><loc>http://localhost/only-ru</loc>.*?</url>#', $file, $only);
        $this->assertStringNotContainsString('xhtml:link', $only[0] ?? 'missing');
    }

    #[Test]
    public function test_url_says_whether_an_address_is_in_the_map_and_why_not(): void
    {
        $this->entity(['ru' => 'О нас'], ['ru' => 'about']);
        $this->entity(['ru' => 'Черновик'], ['ru' => 'draft'], published: false);
        $this->entity(['ru' => 'Закрыто'], ['ru' => 'closed'])->saveSeo(['robots' => 'noindex']);

        $this->actingAs($this->editor(['seo.view']), 'cms');

        foreach ([
            '/about' => [true, null],
            '/draft' => [false, 'hidden'],
            '/closed' => [false, 'noindex'],
            '/nowhere' => [false, 'unknown'],
        ] as $url => [$included, $reason]) {
            $this->postJson('/api/cms/seo/test-url', ['url' => $url])
                ->assertOk()
                ->assertJsonPath('data.sitemap.included', $included)
                ->assertJsonPath('data.sitemap.reason', $reason);
        }
    }

    #[Test]
    public function a_named_route_in_the_map_is_one_test_url_knows(): void
    {
        Route::get('catalog', static fn (): string => 'catalog')->name('catalog');
        app(SitemapRoutes::class)->register('catalog');

        $this->actingAs($this->editor(['seo.view']), 'cms');

        $this->postJson('/api/cms/seo/test-url', ['url' => '/uk/catalog'])
            ->assertJsonPath('data.sitemap.included', true);
    }

    #[Test]
    public function the_card_counts_what_is_in_the_map_and_what_was_kept_out(): void
    {
        $this->entity(['ru' => 'О нас', 'uk' => 'Про нас'], ['ru' => 'about', 'uk' => 'pro-nas']);
        $this->entity(['ru' => 'Закрыто'], ['ru' => 'closed'])->saveSeo(['robots' => 'noindex']);
        $this->entity(['ru' => 'Чужое'], ['ru' => 'elsewhere'])->saveSeo(['canonical' => 'https://example.test/about']);

        $this->actingAs($this->editor(['seo.view']), 'cms');

        $this->getJson('/api/cms/seo/sitemap')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.files.crumbed', 2)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.excluded.noindex', 1)
            ->assertJsonPath('data.excluded.canonical', 1)
            ->assertJson(static fn (AssertableJson $json) => $json->whereType('data.built_at', 'string')->etc());

        // Rebuilding is a change, and reading is not permission to make one.
        $this->postJson('/api/cms/seo/sitemap')->assertForbidden();

        $this->actingAs($this->editor(), 'cms');
        $this->entity(['ru' => 'Новое'], ['ru' => 'new']);

        $this->postJson('/api/cms/seo/sitemap')->assertOk()->assertJsonPath('data.files.crumbed', 3);
    }

    #[Test]
    public function an_agent_reads_the_same_numbers(): void
    {
        $this->entity(['ru' => 'О нас'], ['ru' => 'about']);

        $registry = $this->app->make(ToolRegistry::class);
        $response = WebxServer::actingAs($this->editor(['seo.view']), 'cms')
            ->tool(new RegistryTool($registry->tool('seo_sitemap_status')), []);

        $this->assertInstanceOf(TestResponse::class, $response);
        $response->assertStructuredContent(static function (AssertableJson $json): void {
            $json->where('enabled', true)->where('files.crumbed', 1)->where('total', 1)->etc();
        });
    }

    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $slug
     */
    private function entity(array $title, array $slug, bool $published = true): CrumbedEntity
    {
        return CrumbedEntity::query()->create(['title' => $title, 'slug' => $slug, 'published' => $published]);
    }

    private function headOf(CrumbedEntity $entity, ?string $url = null, string $locale = 'ru'): string
    {
        return (string) app(Seo::class)->head($entity, $url ?? '/'.$entity->routePath('ru'), $locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(string $head): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $head, $matches);

        return array_map(static fn (string $json): array => (array) json_decode($json, true), $matches[1]);
    }

    /**
     * @return list<string>
     */
    private function types(string $head): array
    {
        return array_map(static fn (array $block): string => (string) ($block['@type'] ?? ''), $this->blocks($head));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function trailOf(string $head): array
    {
        foreach ($this->blocks($head) as $block) {
            if (($block['@type'] ?? null) === 'BreadcrumbList') {
                /** @var list<array<string, mixed>> $items */
                $items = $block['itemListElement'];

                return $items;
            }
        }

        $this->fail('No BreadcrumbList in the head.');
    }

    /**
     * @return array<string, string>
     */
    private function alternates(string $head): array
    {
        preg_match_all('#<link rel="alternate" hreflang="([^"]+)" href="([^"]+)">#', $head, $matches);

        return array_combine($matches[1], array_map(static fn (string $href): string => html_entity_decode($href), $matches[2]));
    }
}
