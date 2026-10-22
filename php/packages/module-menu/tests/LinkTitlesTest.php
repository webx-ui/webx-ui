<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blog\BlogServiceProvider;
use WebxUi\Catalog\CatalogServiceProvider;
use WebxUi\Events\EventsServiceProvider;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Pages\PagesServiceProvider;
use WebxUi\Press\PressServiceProvider;
use WebxUi\Recipes\RecipesServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Services\ServicesServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Vacancies\VacanciesServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * What a menu item can point at, as `menu://menus` names it to an agent, in every language of the
 * panel. A site whose fallback is not English read `webx-catalog::module.categories` there: the
 * catalogue had no Ukrainian file, and Laravel falls back once, to the site's own language.
 *
 * Every module that offers something to link to is booted, so it runs only in the monorepo: this
 * package on its own has none of them to ask.
 */
final class LinkTitlesTest extends TestCase
{
    /** Each module that registers a kind of link, with what it needs booted before it. */
    private const array MODULES = [
        MediaServiceProvider::class,
        SettingsServiceProvider::class,
        SeoServiceProvider::class,
        ThemeServiceProvider::class,
        WidgetsServiceProvider::class,
        BlocksServiceProvider::class,
        InboxServiceProvider::class,
        PagesServiceProvider::class,
        BlogServiceProvider::class,
        ServicesServiceProvider::class,
        EventsServiceProvider::class,
        RecipesServiceProvider::class,
        VacanciesServiceProvider::class,
        PressServiceProvider::class,
        CatalogServiceProvider::class,
    ];

    /** The languages of the panel. */
    private const array LOCALES = ['en', 'ru', 'uk', 'de', 'fr', 'es', 'it', 'pt', 'pl', 'tr'];

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        $modules = array_values(array_filter(self::MODULES, class_exists(...)));

        return [...parent::getPackageProviders($app), ...$modules];
    }

    #[Test]
    public function every_kind_of_link_is_named_in_every_language_of_the_panel(): void
    {
        if (! class_exists(CatalogServiceProvider::class) || ! class_exists(BlogServiceProvider::class)) {
            $this->markTestSkipped('Not in the monorepo: there are no modules to link to.');
        }

        $resource = null;

        foreach ($this->app->make(ToolRegistry::class)->resources() as $one) {
            if ($one->uri === 'menu://menus') {
                $resource = $one;
            }
        }

        $this->assertNotNull($resource);

        foreach (self::LOCALES as $locale) {
            // A site in one language falls back to it, and a missing file shows its key.
            $this->app->setLocale($locale);
            $this->app->setFallbackLocale($locale);

            /** @var array{link_to: list<array{entity_type: string, title: string}>} $menus */
            $menus = json_decode((string) json_encode(($resource->handler)()), true);
            $types = array_column($menus['link_to'], 'entity_type');

            foreach (['page', 'article', 'service', 'event', 'recipe', 'vacancy', 'catalog.category', 'catalog.product'] as $type) {
                $this->assertContains($type, $types, "{$locale}: nothing links to [{$type}]");
            }

            foreach ($menus['link_to'] as $source) {
                $this->assertStringNotContainsString('::', $source['title'], "{$locale}: [{$source['entity_type']}] is named by its key");
                $this->assertNotSame('', trim($source['title']), "{$locale}: [{$source['entity_type']}] has no name");
            }
        }
    }
}
