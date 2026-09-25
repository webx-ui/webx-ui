<?php

declare(strict_types=1);

namespace WebxUi\Recipes;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use WebxUi\Admin\Categories\CategoryLinkSource;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Recipes\Collections\RecipesSource;
use WebxUi\Recipes\Handlers\CategoryHandler;
use WebxUi\Recipes\Handlers\RecipeHandler;
use WebxUi\Recipes\Http\Controllers\IndexController;
use WebxUi\Recipes\Links\RecipeLinkSource;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;
use WebxUi\Recipes\Panel\CategoriesModule;
use WebxUi\Recipes\Panel\NutrientsModule;
use WebxUi\Recipes\Panel\RecipesGroup;
use WebxUi\Recipes\Panel\RecipesModule;
use WebxUi\Recipes\Relations\RecipeTarget;
use WebxUi\Recipes\Rendering\RecipeCard;
use WebxUi\Recipes\Seo\FilteredCatalogSource;
use WebxUi\Routing\Formatters\Prefixed;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Rendering\SeoSources;
use WebxUi\Seo\Sitemap\SitemapRoutes;

/**
 * Two entities with addresses, one kind of category without, one route that is not an entity,
 * and the screens they are edited on. What is shared is somebody else's: the categories and the
 * relations are `module-admin`'s, the addresses `routing`'s, the sitemap and the `<head>`
 * `module-seo`'s, the block `module-blocks'` — when it is here.
 */
class RecipesServiceProvider extends ServiceProvider
{
    /** The name of the index route — what the sitemap and a site's templates know it by. */
    public const INDEX_ROUTE = 'webx.recipes.index';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-recipes.php', 'webx-recipes');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-recipes');

        // Before anything is registered under it: a prefix nobody gave is a site whose recipes
        // would stand among its pages, and that is refused rather than half built.
        $prefix = self::prefix($this->config());

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-recipes');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerRouteTypes($prefix);
        $this->registerIndexRoute($prefix);
        $this->registerScreens();
        $this->registerLinkSources();
        $this->registerCollection();
        $this->app->make(RelationTargets::class)->register(new RecipeTarget);
        $this->app->make(SeoSources::class)->register(new FilteredCatalogSource);
        $this->registerPanel();

        $this->app->make(SitemapRoutes::class)->register(self::INDEX_ROUTE);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-recipes.php' => config_path('webx-recipes.php'),
        ], 'webx-recipes-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-recipes'),
        ], 'webx-recipes-lang');

        // The page and its parts are the least markup that works, and meant to be rewritten one
        // part at a time: a site publishes them all and deletes what it keeps as it is.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-recipes'),
        ], 'webx-recipes-views');
    }

    /**
     * The prefix, normalised — and never empty (§5.3): flat addresses of recipes at the root of
     * the site would argue with the tree of pages over every address.
     *
     * @throws InvalidArgumentException
     */
    public static function prefix(Config $config): string
    {
        $prefix = UrlNormaliser::key((string) $config->get('webx-recipes.prefix', 'recipes'));

        if ($prefix === '') {
            throw new InvalidArgumentException((string) __('webx-recipes::errors.prefix'));
        }

        return $prefix;
    }

    /**
     * A recipe and a category on one level under one prefix — so a recipe in three categories
     * has one address. `Fail` on both: a clash is an error under the field, not a quiet `-2`.
     */
    private function registerRouteTypes(string $prefix): void
    {
        $types = $this->app->make(RouteTypes::class);
        $flat = new Prefixed($prefix, Slug::class);

        $types->register(new RouteType(
            type: Recipe::TYPE,
            model: Recipe::class,
            formatter: $flat,
            handler: RecipeHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));

        $types->register(new RouteType(
            type: RecipeCategory::TYPE,
            model: RecipeCategory::class,
            formatter: $flat,
            handler: CategoryHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));
    }

    /**
     * The index: an ordinary route, so it wins before the registry's fallback and `Reserved`
     * closes the address to pages of its own accord. Switched off, it is not registered, and the
     * address is free for a page with the catalogue on it as a block (decision 16).
     *
     * Twice where the language is in the path; the prefixed copy would match `anything/recipes`
     * without {@see OneSpellingPerAddress} in front of it.
     */
    private function registerIndexRoute(string $prefix): void
    {
        if (! (bool) $this->config()->get('webx-recipes.index', true)) {
            return;
        }

        /** @var list<string> $middleware */
        $middleware = array_values((array) $this->config()->get('webx-recipes.middleware', ['web', 'webx.locale']));

        Route::get($prefix, IndexController::class)
            ->middleware($middleware)
            ->name(self::INDEX_ROUTE);

        if ((string) $this->config()->get('webx-localization.strategy', 'prefix') !== 'prefix') {
            return;
        }

        Route::get('{'.OneSpellingPerAddress::PARAMETER.'}/'.$prefix, IndexController::class)
            ->middleware([...$middleware, OneSpellingPerAddress::class])
            ->name(self::INDEX_ROUTE.'.localised');
    }

    /**
     * The editor of a recipe and the forms of its two kinds of category, all described, so a
     * project adds a field with a patch and `module-seo` its card the same way.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Recipe::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->register(RecipeCategory::SCREEN, __DIR__.'/../resources/screens/category-form.json');
        $screens->register(RecipeNutrient::SCREEN, __DIR__.'/../resources/screens/nutrient-form.json');

        // `wx-categories` names its categories by the path they answer at; this is where that
        // path is told which table the ids live in. From the provider, which `route:cache` runs.
        $sources = $this->app->make(CategorySources::class);

        $sources->register('recipes/categories', RecipeCategory::class, 'webx-recipes::errors.unknown-category');
        $sources->register('recipes/nutrients', RecipeNutrient::class, 'webx-recipes::errors.unknown-nutrient');
    }

    /** A menu names a section of the recipes as often as a single recipe. */
    private function registerLinkSources(): void
    {
        $links = $this->app->make(LinkSources::class);

        $links->register($this->app->make(RecipeLinkSource::class));
        $links->register(new CategoryLinkSource(
            RecipeCategory::class,
            RecipeCategory::TYPE,
            static fn (): string => (string) __('webx-recipes::module.categories'),
            'folder',
            231,
        ));
    }

    /**
     * What a block may show, and — when `module-blocks` is here — the block that shows it. The
     * type is offered, not installed: `webx:blocks:offered --install` puts it on the site once. The
     * card is declared as a component (§5 of the components spec).
     */
    private function registerCollection(): void
    {
        $this->app->make(CollectionSources::class)->register($this->app->make(RecipesSource::class));

        if (class_exists(BlockOffers::class)) {
            $this->app->make(BlockOffers::class)->offer(RecipesModule::ID, __DIR__.'/../resources/blocks');
        }

        // The card is a place a site can redraw in the panel; the partial prints until it does.
        RecipeCard::declare(RecipesModule::ID);
    }

    /**
     * Three sections in a group of their own (§5.9): Recipes · Categories · Rich in. The group is
     * added to the panel's config at boot, because a site that published `webx-admin.php` has its
     * own copy of the list (CLAUDE.md §4).
     */
    private function registerPanel(): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $this->config()->get('webx-admin.groups', []);

        if (! array_key_exists(RecipesGroup::GROUP, $groups)) {
            $this->config()->set('webx-admin.groups', [
                ...$groups,
                RecipesGroup::GROUP => [
                    'title' => 'webx-recipes::module.group',
                    'icon' => 'heart',
                    'order' => 500,
                ],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach ([RecipesModule::class, CategoriesModule::class, NutrientsModule::class] as $module) {
            $modules->register($this->app->make($module));
        }
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
