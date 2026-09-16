<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\Formatters\TreePath;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\RouteSync;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\Tests\Fixtures\Article;
use WebxUi\Routing\Tests\Fixtures\Category;
use WebxUi\Routing\Tests\Fixtures\Page;
use WebxUi\Routing\Tests\Fixtures\PageHandler;
use WebxUi\Routing\Tests\Fixtures\Product;
use WebxUi\Routing\Tests\Fixtures\TranslatedPage;

class WritesTest extends TestCase
{
    #[Test]
    public function saving_gives_the_entity_an_address(): void
    {
        $page = $this->page('about');

        $route = $page->routeCanonical();

        $this->assertNotNull($route);
        $this->assertSame('about', $route->path);
        $this->assertSame('page', $route->entity_type);
        $this->assertSame(Route::CANONICAL, $route->kind);
    }

    #[Test]
    public function renaming_leaves_the_old_address_behind_as_an_alias(): void
    {
        $page = $this->page('about');

        $page->slug = 'about-us';
        $page->save();

        $this->assertSame('about-us', $page->routeCanonical()?->path);

        $alias = Route::query()->where('path', 'about')->firstOrFail();

        $this->assertSame(Route::ALIAS, $alias->kind);
        $this->assertSame($page->routeCanonical()->getKey(), $alias->target_id);
    }

    #[Test]
    public function a_second_rename_does_not_build_a_chain(): void
    {
        $page = $this->page('about');

        $page->slug = 'about-us';
        $page->save();

        $page->slug = 'who-we-are';
        $page->save();

        $canonical = $page->routeCanonical();

        $this->assertSame('who-we-are', $canonical->path);

        // Both aliases point at the row that is canonical now, not at each other.
        foreach (Route::query()->where('kind', Route::ALIAS)->get() as $alias) {
            $this->assertSame($canonical->getKey(), $alias->target_id);
        }
    }

    #[Test]
    public function moving_a_branch_moves_every_descendant_and_leaves_each_an_alias(): void
    {
        $about = $this->page('about');
        $mission = $this->page('mission', $about);
        $team = $this->page('team', $mission);

        $company = $this->page('company');

        $about->refresh()->appendTo($company->refresh());

        $this->assertSame('company/about', $about->refresh()->routeCanonical()?->path);
        $this->assertSame('company/about/mission', $mission->refresh()->routeCanonical()?->path);
        $this->assertSame('company/about/mission/team', $team->refresh()->routeCanonical()?->path);

        foreach (['about', 'about/mission', 'about/mission/team'] as $old) {
            $this->assertSame(
                Route::ALIAS,
                Route::query()->where('path', $old)->firstOrFail()->kind,
                "{$old} should have been left behind as an alias",
            );
        }
    }

    #[Test]
    public function deleting_takes_the_addresses_with_it(): void
    {
        $page = $this->page('about');

        $page->slug = 'about-us';
        $page->save();

        $this->assertSame(2, Route::query()->count());

        $page->delete();

        $this->assertSame(0, Route::query()->count());
    }

    #[Test]
    public function a_live_address_pushes_an_alias_out_of_the_way(): void
    {
        $page = $this->page('about');

        $page->slug = 'about-us';
        $page->save();

        // Somebody writes a new page on the address the old one left behind.
        $fresh = $this->page('about');

        $route = Route::query()->where('path', 'about')->firstOrFail();

        $this->assertSame(Route::CANONICAL, $route->kind);
        $this->assertSame($fresh->getKey(), $route->entity_id);
        $this->assertNull($route->target_id);
    }

    #[Test]
    public function restoring_onto_a_taken_address_is_refused_for_a_fail_type(): void
    {
        $article = Article::query()->create(['title' => 'Belts', 'slug' => 'remni']);
        $article->delete();

        // The address is free again, and somebody takes it.
        Article::query()->create(['title' => 'Belts', 'slug' => 'remni']);

        try {
            $article->restore();
            $this->fail('Restoring onto a taken address should have been refused.');
        } catch (PathRejected $rejected) {
            $this->assertArrayHasKey('slug', $rejected->errors());
        }

        $this->assertTrue(Article::withTrashed()->findOrFail($article->getKey())->trashed());
    }

    #[Test]
    public function a_refused_rename_leaves_the_entity_as_it_was(): void
    {
        $about = $this->page('about');
        $contacts = $this->page('contacts');

        $contacts->slug = 'about';

        try {
            $contacts->save();
            $this->fail('Renaming onto a taken address should have been refused.');
        } catch (PathRejected $rejected) {
            $this->assertSame('about', $rejected->path);
        }

        // Otherwise the slug in the table and the address in the registry would disagree, and
        // the next save would make that permanent.
        $this->assertSame('contacts', Page::query()->findOrFail($contacts->getKey())->slug);
        $this->assertSame('contacts', $contacts->refresh()->routeCanonical()?->path);
        $this->assertSame('about', $about->routeCanonical()?->path);
    }

    #[Test]
    public function every_language_gets_its_own_address(): void
    {
        $this->useLocales(['en', 'uk']);

        $page = new Page(['title' => ['en' => 'About', 'uk' => 'Про нас'], 'slug' => ['en' => 'about', 'uk' => 'pro-nas']]);
        $page->saveAsRoot();

        $paths = Route::query()->canonical()->pluck('path', 'locale')->all();

        $this->assertSame(['en' => 'about', 'uk' => 'pro-nas'], $paths);
    }

    #[Test]
    public function an_untranslated_slug_answers_in_every_language(): void
    {
        $this->useLocales(['en', 'uk']);

        $category = Category::query()->create(['name' => 'Parts', 'slug' => 'parts']);

        $paths = $category->routes()->pluck('path', 'locale')->all();

        $this->assertSame(['en' => 'parts', 'uk' => 'parts'], $paths);
    }

    #[Test]
    public function bulk_writes_a_batch_without_the_observer(): void
    {
        $products = [];

        for ($index = 0; $index < 5; $index++) {
            $product = new Product(['name' => 'Belt', 'slug' => 'remen', 'sku' => (string) (100 + $index)]);
            $product->saveQuietly();
            $products[] = $product;
        }

        $this->assertSame(0, Route::query()->count());

        $this->app->make(RouteSync::class)->bulk($products);

        $this->assertSame(5, Route::query()->canonical()->count());
        $this->assertSame('remen-104', $products[4]->routeCanonical()?->path);
    }

    #[Test]
    public function url_carries_the_language_prefix(): void
    {
        $this->useLocales(['en', 'uk']);
        $this->app['url']->forceRootUrl('https://example.test');
        $this->app['url']->forceScheme('https');

        $page = new Page(['title' => 'About', 'slug' => ['en' => 'about', 'uk' => 'pro-nas']]);
        $page->saveAsRoot();

        $this->assertSame('https://example.test/about', $page->url('en'));
        $this->assertSame('https://example.test/uk/pro-nas', $page->url('uk'));
    }

    #[Test]
    public function a_language_the_entity_has_no_address_in_gets_no_row(): void
    {
        $this->useLocales(['en', 'uk']);
        $this->registerTranslatedPages();

        $page = new TranslatedPage(['title' => 'About', 'slug' => ['en' => 'about']]);
        $page->saveAsRoot();

        // The Ukrainian slug was never written, so there is no Ukrainian address — rather than
        // one made of the English slug standing in front of untranslated content.
        $this->assertSame(['en'], Route::query()->forEntity($page)->pluck('locale')->all());

        // And nothing was filled in behind the editor: reading a slug falls back to another
        // language, and syncing used to write what it read straight back.
        $this->assertArrayNotHasKey('uk', $page->refresh()->getTranslations('slug'));
    }

    #[Test]
    public function clearing_a_slug_takes_that_language_off_the_site(): void
    {
        $this->useLocales(['en', 'uk']);
        $this->registerTranslatedPages();

        $page = new TranslatedPage(['title' => 'About', 'slug' => ['en' => 'about', 'uk' => 'pro-nas']]);
        $page->saveAsRoot();

        $this->assertSame(2, Route::query()->forEntity($page)->count());

        $page->setTranslation('slug', 'uk', null);
        $page->save();

        $this->assertSame(['en'], Route::query()->forEntity($page)->pluck('locale')->all());
    }

    /**
     * Registered here rather than with the other types, because the fixture shares the `pages`
     * table with {@see Page} — two registered types over one table would have every row of it
     * expected under both.
     */
    private function registerTranslatedPages(): void
    {
        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: 'translated-page',
            model: TranslatedPage::class,
            formatter: TreePath::class,
            handler: PageHandler::class,
        ));
    }
}
