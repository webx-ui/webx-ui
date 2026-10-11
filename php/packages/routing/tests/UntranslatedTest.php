<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Formatters\TreePath;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\Tests\Fixtures\PageHandler;
use WebxUi\Routing\Tests\Fixtures\TranslatedPage;
use WebxUi\Routing\Untranslated;

/**
 * A page with no translation in the language that was asked for (§8.7): shown in the default
 * language's words under that language's prefix, where its type asked for it — and nowhere else.
 */
class UntranslatedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useLocales(['en', 'uk']);
    }

    #[Test]
    public function an_untranslated_page_answers_under_the_prefix_of_the_language_asked_for(): void
    {
        $this->register(shows: true);
        $page = $this->english('about');

        // No Ukrainian row was written, and none is written by answering.
        $this->assertSame(['en'], Route::query()->forEntity($page)->pluck('locale')->all());

        $this->get('/uk/about')->assertOk()->assertJsonPath('id', $page->getKey());

        $resolution = Resolution::of($this->app->make('request'));
        $this->assertNotNull($resolution);
        $this->assertTrue($resolution->isUntranslated());
        $this->assertSame('uk', $resolution->locale);
        $this->assertSame('en', $resolution->route->locale);

        $this->assertSame(['en'], Route::query()->forEntity($page)->pluck('locale')->all());
    }

    #[Test]
    public function a_page_found_in_its_own_language_is_not_untranslated(): void
    {
        $this->register(shows: true);
        $this->english('about');

        $this->get('/about')->assertOk();

        $this->assertFalse(Resolution::of($this->app->make('request'))?->isUntranslated());
    }

    #[Test]
    public function a_type_that_did_not_ask_for_it_answers_404_as_before(): void
    {
        $this->register(shows: false);
        $this->english('about');

        $this->get('/uk/about')->assertNotFound();
    }

    #[Test]
    public function a_translation_under_another_path_is_a_301_to_it(): void
    {
        $this->register(shows: true);
        $page = new TranslatedPage(['title' => 'About', 'slug' => ['en' => 'about', 'uk' => 'pro-nas']]);
        $page->saveAsRoot();

        $response = $this->get('/uk/about?ref=menu');

        $response->assertStatus(301);
        $location = (string) $response->headers->get('Location');
        $this->assertSame('/uk/pro-nas?ref=menu', parse_url($location, PHP_URL_PATH).'?'.parse_url($location, PHP_URL_QUERY));
    }

    #[Test]
    public function a_draft_stays_a_404_in_every_language(): void
    {
        $this->register(shows: true);
        $page = $this->english('about');
        $page->update(['published' => false]);

        $this->get('/uk/about')->assertNotFound();
        $this->get('/uk/about?token='.PageHandler::PREVIEW)->assertOk();
    }

    #[Test]
    public function an_unknown_path_is_still_a_404(): void
    {
        $this->register(shows: true);
        $this->english('about');

        $this->get('/uk/nowhere')->assertNotFound();
        $this->get('/nowhere')->assertNotFound();
    }

    #[Test]
    public function the_address_of_an_untranslated_page_is_the_default_path_under_the_prefix(): void
    {
        $this->register(shows: true);
        $page = $this->english('about');
        $untranslated = $this->app->make(Untranslated::class);

        $this->assertSame('uk/about', $untranslated->path($page, 'uk'));
        $this->assertSame('/about', $untranslated->original($page, 'uk'));
        $this->assertTrue($untranslated->is($page, 'uk'));

        // The default language is the original, not a copy of it.
        $this->assertNull($untranslated->path($page, 'en'));
        $this->assertFalse($untranslated->is($page, 'en'));
        // A language the site does not have.
        $this->assertNull($untranslated->path($page, 'de'));
    }

    #[Test]
    public function a_translated_page_or_a_type_that_did_not_ask_has_no_such_address(): void
    {
        $this->register(shows: true);
        $translated = new TranslatedPage(['title' => 'About', 'slug' => ['en' => 'about', 'uk' => 'pro-nas']]);
        $translated->saveAsRoot();

        $this->assertNull($this->app->make(Untranslated::class)->path($translated, 'uk'));

        $this->register(shows: false);
        $page = $this->english('contacts');

        $this->assertNull($this->app->make(Untranslated::class)->path($page, 'uk'));
    }

    #[Test]
    public function a_path_another_entity_holds_in_that_language_is_not_offered(): void
    {
        $this->register(shows: true);
        $page = $this->english('news');
        // A Ukrainian page whose own address happens to be the English page's.
        $other = new TranslatedPage(['title' => 'Novyny', 'slug' => ['en' => 'novyny', 'uk' => 'news']]);
        $other->saveAsRoot();

        $this->assertNull($this->app->make(Untranslated::class)->path($page, 'uk'));
        $this->get('/uk/news')->assertOk()->assertJsonPath('id', $other->getKey());
    }

    #[Test]
    public function nothing_is_shown_untranslated_where_the_language_is_not_in_the_path(): void
    {
        $this->register(shows: true);
        $page = $this->english('about');
        $this->app['config']->set('webx-localization.strategy', 'header');

        $this->assertNull($this->app->make(Untranslated::class)->path($page, 'uk'));
    }

    private function register(bool $shows): void
    {
        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: 'translated-page',
            model: TranslatedPage::class,
            formatter: TreePath::class,
            handler: PageHandler::class,
            showsUntranslated: $shows,
        ));
    }

    private function english(string $slug): TranslatedPage
    {
        $page = new TranslatedPage(['title' => ucfirst($slug), 'slug' => ['en' => $slug]]);
        $page->saveAsRoot();

        return $page->refresh();
    }
}
