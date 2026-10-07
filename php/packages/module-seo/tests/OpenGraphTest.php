<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Panel\AddressReport;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;
use WebxUi\Seo\Rendering\SeoSources;
use WebxUi\Seo\Rendering\SocialTags;
use WebxUi\Seo\Tests\Fixtures\ArticleEntity;
use WebxUi\Seo\Tests\Fixtures\FallbackEntity;
use WebxUi\Settings\Settings;

/**
 * Open Graph, `article:*` and `twitter:*`, every line from what the page already says: the title
 * after the template, the description, the canonical, the picture the sources chose, the entity's
 * dates, the language. Nothing here is typed for the purpose, and nothing is printed empty.
 */
final class OpenGraphTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('seo_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->text('lead')->nullable();
            $table->string('picture')->nullable();
        });

        app(Settings::class)->save([
            'general.project-name' => ['ru' => 'Акме'],
            'seo.title-template' => '{title} — {site}',
        ]);
    }

    #[Test]
    public function a_page_gets_the_whole_set_from_what_it_already_says(): void
    {
        $head = $this->headOf(FallbackEntity::query()->create([
            'name' => 'Печенье',
            'lead' => '<p>Миндальное печенье без муки.</p>',
            'picture' => '/storage/cookies.webp',
        ]));

        $this->assertSame([
            ['property', 'og:title', 'Печенье — Акме'],
            ['property', 'og:description', 'Миндальное печенье без муки.'],
            ['property', 'og:url', 'http://localhost/recipes/cookies'],
            ['property', 'og:type', 'website'],
            ['property', 'og:site_name', 'Акме'],
            ['property', 'og:locale', 'ru_RU'],
            // The test site has Ukrainian too, under its prefix: the same path is the page there.
            ['property', 'og:locale:alternate', 'uk_UA'],
            ['property', 'og:image', 'http://localhost/storage/cookies.webp'],
            ['property', 'og:image:type', 'image/webp'],
            // No picture alt was given, so the page's title says what the picture is of.
            ['property', 'og:image:alt', 'Печенье — Акме'],
            ['name', 'twitter:card', 'summary_large_image'],
            ['name', 'twitter:title', 'Печенье — Акме'],
            ['name', 'twitter:description', 'Миндальное печенье без муки.'],
            ['name', 'twitter:image', 'http://localhost/storage/cookies.webp'],
            ['name', 'twitter:image:alt', 'Печенье — Акме'],
        ], $this->tags($head));
    }

    #[Test]
    public function an_article_says_so_with_its_dates_section_and_tags(): void
    {
        $head = $this->headOf(ArticleEntity::query()->create(['name' => 'Печенье', 'picture' => '/storage/cookies.jpg']));
        $tags = $this->tags($head);

        $this->assertContains(['property', 'og:type', 'article'], $tags);
        $this->assertContains(['property', 'og:image:alt', 'A plate of cookies'], $tags);
        $this->assertContains(['name', 'twitter:image:alt', 'A plate of cookies'], $tags);

        $article = array_values(array_filter($tags, static fn (array $tag): bool => str_starts_with($tag[1], 'article:')));

        $this->assertSame([
            ['property', 'article:published_time', '2026-10-01T09:00:00+00:00'],
            ['property', 'article:modified_time', '2026-10-05T12:30:00+00:00'],
            ['property', 'article:section', 'Sweet things'],
            ['property', 'article:tag', 'Baking'],
            ['property', 'article:tag', 'Gluten-free'],
        ], $article);
    }

    #[Test]
    public function the_type_is_on_the_resolved_data_too(): void
    {
        $entity = ArticleEntity::query()->create(['name' => 'Печенье']);

        $this->assertSame('article', app(Seo::class)->for('/recipes/cookies', $entity, 'ru')->og['type']);
        $this->assertSame('website', app(Seo::class)->for('/recipes', null, 'ru')->og['type']);
    }

    #[Test]
    public function an_svg_falls_through_to_the_next_picture(): void
    {
        $this->defaultImage('http://localhost/storage/default.png');

        $head = $this->headOf(FallbackEntity::query()->create(['name' => 'Tatler', 'picture' => '/storage/logo.svg']));

        $this->assertStringNotContainsString('logo.svg', $head);
        $this->assertContains(['property', 'og:image', 'http://localhost/storage/default.png'], $this->tags($head));
        $this->assertContains(['property', 'og:image:type', 'image/png'], $this->tags($head));
    }

    #[Test]
    public function an_svg_with_nothing_below_it_is_no_picture_at_all(): void
    {
        $head = $this->headOf(FallbackEntity::query()->create(['name' => 'Tatler', 'picture' => '/storage/logo.svg']));

        $this->assertStringNotContainsString('og:image', $head);
        $this->assertStringNotContainsString('twitter:image', $head);
        $this->assertContains(['name', 'twitter:card', 'summary'], $this->tags($head));
    }

    #[Test]
    public function a_picture_keeps_the_alt_of_its_own_source(): void
    {
        // The card's picture wins; the article's alt described the cover it replaced.
        $entity = ArticleEntity::query()->create(['name' => 'Печенье', 'picture' => '/storage/cookies.jpg']);
        $this->registerSource(40, ['og' => ['image' => 'http://localhost/storage/other.jpg']]);

        $tags = $this->tags($this->headOf($entity));

        $this->assertContains(['property', 'og:image', 'http://localhost/storage/other.jpg'], $tags);
        $this->assertContains(['property', 'og:image:alt', 'Печенье — Акме'], $tags);
    }

    #[Test]
    public function the_x_account_comes_from_the_organisations_profiles(): void
    {
        app(Settings::class)->save(['seo.org-socials' => [
            ['url' => 'https://www.instagram.com/acme'],
            ['url' => 'https://x.com/acme_studio'],
        ]]);

        $tags = $this->tags($this->headOf(FallbackEntity::query()->create(['name' => 'Печенье'])));

        $this->assertContains(['name', 'twitter:site', '@acme_studio'], $tags);
    }

    #[Test]
    public function every_group_follows_its_switch(): void
    {
        $entity = ArticleEntity::query()->create(['name' => 'Печенье', 'picture' => '/storage/cookies.jpg']);

        config()->set('webx-seo.print.og', false);
        $head = $this->headOf($entity);
        $this->assertStringNotContainsString('og:', $head);
        $this->assertStringContainsString('article:section', $head);
        $this->assertStringContainsString('twitter:title', $head);

        config()->set('webx-seo.print.article', false);
        config()->set('webx-seo.print.twitter', false);
        $head = $this->headOf($entity);
        $this->assertStringNotContainsString('article:', $head);
        $this->assertStringNotContainsString('twitter:', $head);
    }

    #[Test]
    public function a_language_code_becomes_the_locale_open_graph_wants(): void
    {
        $this->assertSame('en_US', SocialTags::ogLocale('en'));
        $this->assertSame('de_DE', SocialTags::ogLocale('de'));
        $this->assertSame('uk_UA', SocialTags::ogLocale('uk'));
        $this->assertSame('pt_BR', SocialTags::ogLocale('pt-BR'));
        $this->assertSame('pt_BR', SocialTags::ogLocale('pt_br'));
        $this->assertSame('en_GB', SocialTags::ogLocale('en', ['en' => 'en_GB']));
        $this->assertNull(SocialTags::ogLocale(''));
    }

    #[Test]
    public function the_other_languages_of_the_page_are_its_alternate_locales(): void
    {
        $tags = app(SocialTags::class)->for(SeoData::make(['title' => 'About']), null, 'en', [
            'en' => 'http://localhost/about',
            'pt-BR' => 'http://localhost/pt-br/sobre',
            'uk' => 'http://localhost/uk/pro-nas',
            'x-default' => 'http://localhost/about',
        ]);

        $locales = array_values(array_map(
            static fn (array $tag): string => $tag['key'].'='.$tag['content'],
            array_filter($tags, static fn (array $tag): bool => str_starts_with($tag['key'], 'og:locale')),
        ));

        $this->assertSame(['og:locale=en_US', 'og:locale:alternate=pt_BR', 'og:locale:alternate=uk_UA'], $locales);
    }

    #[Test]
    public function test_url_reports_the_lines_the_head_prints(): void
    {
        $this->registerSource(1, ['og' => ['image' => 'http://localhost/storage/default.jpg']]);

        $report = app(AddressReport::class)->for('/about', 'ru');

        $this->assertContains(['key' => 'og:image', 'content' => 'http://localhost/storage/default.jpg'], $report['social']);
        $this->assertContains(['key' => 'twitter:card', 'content' => 'summary_large_image'], $report['social']);
    }

    private function headOf(object $entity): string
    {
        return (string) app(Seo::class)->head($entity, '/recipes/cookies', 'ru');
    }

    /**
     * Every meta line of the social groups, in order, as [attribute, key, content].
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function tags(string $head): array
    {
        preg_match_all('#<meta (property|name)="((?:og|article|twitter):[^"]+)" content="([^"]*)">#u', $head, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [$match[1], $match[2], html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5)], $matches);
    }

    private function defaultImage(string $url): void
    {
        $this->registerSource(10, ['og' => ['image' => $url]]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function registerSource(int $priority, array $fields): void
    {
        app(SeoSources::class)->register(new class($priority, $fields) implements SeoSource
        {
            /**
             * @param  array<string, mixed>  $fields
             */
            public function __construct(private readonly int $priority, private readonly array $fields) {}

            public function priority(): int
            {
                return $this->priority;
            }

            public function forUrl(string $url, ?object $subject = null, ?string $locale = null): SeoData
            {
                return SeoData::make($this->fields);
            }
        });
    }
}
