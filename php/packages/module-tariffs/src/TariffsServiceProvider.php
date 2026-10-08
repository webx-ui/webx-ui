<?php

declare(strict_types=1);

namespace WebxUi\Tariffs;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Tariffs\Collections\TariffsSource;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;
use WebxUi\Tariffs\Panel\CategoriesModule;
use WebxUi\Tariffs\Panel\TariffsGroup;
use WebxUi\Tariffs\Panel\TariffsModule;

/**
 * Tariffs, their groups, and the screens they are edited on — and not one public route
 * (decision 1). A tariff reaches the site in a block, or through `tariffs()` in a template of the
 * site: the module offers the block type and a source for it, and the page it stands on brings
 * the address, the SEO and the menu entry.
 */
class TariffsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('tariffs', 'tariff_categories', 'tariff_category_tariff');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-tariffs.php', 'webx-tariffs');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-tariffs');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerScreens();
        $this->registerCollection();
        $this->registerPanel();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-tariffs.php' => config_path('webx-tariffs.php'),
        ], 'webx-tariffs-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-tariffs'),
        ], 'webx-tariffs-lang');
    }

    /**
     * The editor of a tariff and the page of a group, both described, so a project adds a field
     * with a patch — and the two selects of the tariff laid over from the config (§4.3). They
     * have no options in the JSON: this patch is where they come from, so a site adds a currency
     * or a button look with one line of config, and the check of the value is the one every
     * `wx-select` already has on the server.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Tariff::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->register(TariffCategory::SCREEN, __DIR__.'/../resources/screens/category-form.json');

        $screens->extend(Tariff::SCREEN, [
            [
                'op' => 'set',
                'target' => 'currency',
                'props' => ['options' => $this->app->make(Currencies::class)->options()],
            ],
            [
                'op' => 'set',
                'target' => 'button-variant',
                'props' => ['options' => $this->app->make(Variants::class)->options()],
            ],
        ]);

        // `wx-categories` on the tariff form, and the choice of a tariffs block, name the groups
        // by the path they answer at; this is where that path is told which table the ids live
        // in. From the provider rather than the routes file, which `route:cache` never runs.
        $this->app->make(CategorySources::class)->register(
            'tariffs/categories',
            TariffCategory::class,
            'webx-tariffs::errors.unknown-category',
        );
    }

    /**
     * What a block may show, and the block that shows it (§4.2, §4.4). The type is offered, not
     * installed: `webx:blocks:offered --install` puts it on the site once, and a type the site
     * already has by that name is never touched.
     */
    private function registerCollection(): void
    {
        $this->app->singleton(TariffsSource::class);
        $this->app->make(CollectionSources::class)->register($this->app->make(TariffsSource::class));

        $this->app->make(BlockOffers::class)->offer(TariffsModule::ID, __DIR__.'/../resources/blocks');
    }

    /**
     * Two sections in a group of their own (§5.2): the tariffs, and their groups.
     *
     * The menu group is added to the panel's config at boot rather than shipped as a default,
     * because a site that published `webx-admin.php` has its own copy of the list (CLAUDE.md §4).
     */
    private function registerPanel(): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $this->config()->get('webx-admin.groups', []);

        if (! array_key_exists(TariffsGroup::GROUP, $groups)) {
            $this->config()->set('webx-admin.groups', [
                ...$groups,
                TariffsGroup::GROUP => [
                    'title' => 'webx-tariffs::module.group',
                    'icon' => 'tag',
                    'order' => 680,
                ],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach ([TariffsModule::class, CategoriesModule::class] as $module) {
            $modules->register($this->app->make($module));
        }
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
