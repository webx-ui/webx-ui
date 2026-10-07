<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;
use WebxUi\Seo\Rendering\SeoSources;
use WebxUi\Seo\Tests\Fixtures\FallbackEntity;
use WebxUi\Settings\Settings;

/**
 * What a page is called when nobody wrote it a card: the entity's own name, lead and picture,
 * below the card and above the site's defaults — and the title template, which adds the site's
 * name once and only where the title does not already say it.
 */
final class FallbackTest extends TestCase
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
    public function an_entity_without_a_card_names_its_page_through_the_template(): void
    {
        $entity = $this->entity();

        $head = (string) app(Seo::class)->head($entity, '/recipes/cookies', 'ru');

        $this->assertStringContainsString('<title>Печенье — Акме</title>', $head);
        $this->assertStringContainsString('<meta property="og:title" content="Печенье — Акме">', $head);
        $this->assertStringContainsString('<meta name="description" content="Миндальное печенье без муки.">', $head);
        $this->assertStringContainsString('<meta property="og:description" content="Миндальное печенье без муки.">', $head);
        $this->assertStringContainsString('<meta property="og:image" content="http://localhost/storage/cookies.webp">', $head);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $head);
    }

    #[Test]
    public function the_card_wins_every_field_it_fills_in_and_only_those(): void
    {
        $entity = $this->entity();
        $entity->saveSeo(['title' => ['ru' => 'Лучшее печенье']]);

        $data = app(Seo::class)->for('/recipes/cookies', $entity, 'ru');

        $this->assertSame('Лучшее печенье — Акме', $data->title);
        $this->assertSame('Миндальное печенье без муки.', $data->description);
    }

    #[Test]
    public function the_entitys_own_picture_beats_the_sites_default_one(): void
    {
        // The default social image is "shown when a page has no picture of its own".
        app(SeoSources::class)->register(new class implements SeoSource
        {
            public function priority(): int
            {
                return 10;
            }

            public function forUrl(string $url, ?object $subject = null, ?string $locale = null): SeoData
            {
                return SeoData::make(['og' => ['image' => 'https://example.test/default.png']]);
            }
        });

        $data = app(Seo::class)->for('/recipes/cookies', $this->entity(), 'ru');
        $this->assertSame('http://localhost/storage/cookies.webp', $data->og['image']);

        $bare = $this->entity(['picture' => null]);
        $this->assertSame('https://example.test/default.png', app(Seo::class)->for('/recipes/cookies', $bare, 'ru')->og['image']);
    }

    #[Test]
    public function a_view_names_a_page_that_has_no_entity(): void
    {
        $head = (string) app(Seo::class)->head(null, '/recipes', 'ru', ['title' => 'Рецепты']);

        $this->assertStringContainsString('<title>Рецепты — Акме</title>', $head);

        // A rule for the address still wins.
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/recipes', 'title' => ['ru' => 'Все рецепты']]);
        $this->assertSame('Все рецепты — Акме', app(Seo::class)->for('/recipes', null, 'ru', ['title' => 'Рецепты'])->title);
    }

    #[Test]
    public function a_title_that_already_names_the_site_is_printed_as_written(): void
    {
        $entity = $this->entity();
        // Spelled with a space and in another case than the project name: still the site's name.
        $entity->saveSeo(['title' => ['ru' => 'О нас | АКМЕ']]);
        app(Settings::class)->save(['general.project-name' => ['ru' => 'Ак ме']]);

        $this->assertSame('О нас | АКМЕ', app(Seo::class)->for('/about', $entity, 'ru')->title);
        // Without the site in it, the template still applies.
        $this->assertSame('Печенье — Ак ме', app(Seo::class)->for('/recipes/cookies', $this->entity(), 'ru')->title);
    }

    #[Test]
    public function the_home_page_called_by_its_own_name_is_called_by_the_sites(): void
    {
        $home = $this->entity(['name' => 'Главная']);

        $this->assertSame('Акме', app(Seo::class)->for('/', $home, 'ru')->title);

        // A title somebody wrote for it goes through the template like any other.
        $home->saveSeo(['title' => ['ru' => 'Свежая выпечка']]);
        $this->assertSame('Свежая выпечка — Акме', app(Seo::class)->for('/', $home, 'ru')->title);
    }

    #[Test]
    public function the_fallback_lead_loses_its_markup_and_is_cut_at_a_word(): void
    {
        $data = SeoData::fallback('A', '<p>One&nbsp;<b>two</b></p><p>three</p>', 'ftp://example.test/x.png');

        $this->assertSame('One two three', $data->description);
        $this->assertSame('Hands on.', SeoData::fallback('A', '<p>Hands <em>on</em>.</p>')->description);
        $this->assertSame([], $data->og);

        $long = SeoData::fallback('A', str_repeat('word ', 100));
        $this->assertLessThanOrEqual(301, mb_strlen((string) $long->description));
        $this->assertStringEndsWith('word…', (string) $long->description);
    }

    #[Test]
    public function the_chain_shows_the_fallback_as_a_step_of_its_own(): void
    {
        $steps = array_column(app(Seo::class)->chain('/recipes/cookies', $this->entity(), 'ru'), 'source');

        $this->assertContains('FallbackSource', $steps);
    }

    #[Test]
    public function the_front_controller_never_reaches_the_canonical(): void
    {
        $request = Request::create('http://localhost/index.php/about', server: ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => base_path('index.php')]);
        $this->app->instance('request', $request);

        $seo = app(Seo::class);

        $this->assertSame('/about', $seo->currentUrl());
        $this->assertSame('http://localhost/about', $seo->for($seo->currentUrl(), null, 'ru')->canonical);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function entity(array $overrides = []): FallbackEntity
    {
        return FallbackEntity::query()->create($overrides + [
            'name' => 'Печенье',
            'lead' => '<p>Миндальное печенье без муки.</p>',
            'picture' => '/storage/cookies.webp',
        ]);
    }
}
