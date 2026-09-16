<?php

declare(strict_types=1);

namespace WebxUi\Pages;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\PageForm;
use WebxUi\Pages\Panel\PagesModule;
use WebxUi\Routing\Formatters\TreePath;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;

/**
 * Almost everything a page does belongs to another package, so this provider is mostly
 * registrations: the kind of entity that has addresses, the kind that is made of blocks, and
 * the section of the panel that edits them.
 */
class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-pages.php', 'webx-pages');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-pages');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-pages');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerRouteType();
        $this->registerBlockEntity();
        $this->registerScreens();

        $this->app->make(ModuleRegistry::class)->register($this->app->make(PagesModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-pages.php' => config_path('webx-pages.php'),
        ], 'webx-pages-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-pages'),
        ], 'webx-pages-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-pages'),
        ], 'webx-pages-views');
    }

    /**
     * The address of a page repeats the tree an editor already sees, so moving a page under
     * another one is the same gesture as changing its address.
     *
     * `Fail` rather than `Suffix`: a page address is chosen deliberately, and one that quietly
     * became `about-2` is a mistake found months later in a search result. A site that wants
     * another scheme says so in `webx-routing.types.page.formatter` and runs
     * `webx:routes:rebuild --type=page`.
     */
    private function registerRouteType(): void
    {
        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: 'page',
            model: Page::class,
            formatter: TreePath::class,
            handler: PageHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));
    }

    /**
     * The editor is a described screen, so a project — or the SEO module (§12) — can add a tab
     * to it with a patch instead of a fork.
     */
    private function registerScreens(): void
    {
        $this->app->make(ScreenRegistry::class)->register(
            PageForm::SCREEN,
            __DIR__.'/../resources/screens/form.json',
        );
    }

    /**
     * Pages are an entity made of blocks, which is what `webx:blocks:bundles --warm` needs to
     * be told: the config file of `module-blocks` cannot know them, so the module that has them
     * adds itself.
     */
    private function registerBlockEntity(): void
    {
        /** @var Config $config */
        $config = $this->app->make('config');

        /** @var list<string> $entities */
        $entities = (array) $config->get('webx-blocks.entities', []);

        if (! in_array(Page::class, $entities, true)) {
            $config->set('webx-blocks.entities', [...$entities, Page::class]);
        }
    }
}
