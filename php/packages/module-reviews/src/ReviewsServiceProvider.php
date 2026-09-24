<?php

declare(strict_types=1);

namespace WebxUi\Reviews;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Reviews\Collections\ReviewsSource;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;
use WebxUi\Reviews\Panel\CategoriesModule;
use WebxUi\Reviews\Panel\ReviewsGroup;
use WebxUi\Reviews\Panel\ReviewsModule;

/**
 * Reviews, their categories, and the screens they are edited on — and not one public route
 * (decision 2). A review reaches the site in a block, or through `reviews()` in a template of the
 * site: the module offers the block type and a source for it, and the page it stands on brings
 * the address, the SEO and the menu entry.
 */
class ReviewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-reviews.php', 'webx-reviews');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-reviews');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerScreens();
        $this->registerCollection();
        $this->registerPanel();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-reviews.php' => config_path('webx-reviews.php'),
        ], 'webx-reviews-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-reviews'),
        ], 'webx-reviews-lang');
    }

    /**
     * The editor of a review and the page of a category, both described, so a project adds a
     * field with a patch.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Review::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->register(ReviewCategory::SCREEN, __DIR__.'/../resources/screens/category-form.json');

        // `wx-categories` on the review form, and the choice of a reviews block, name the
        // categories by the path they answer at; this is where that path is told which table the
        // ids live in. From the provider rather than the routes file, which `route:cache` never runs.
        $this->app->make(CategorySources::class)->register(
            'reviews/categories',
            ReviewCategory::class,
            'webx-reviews::errors.unknown-category',
        );
    }

    /**
     * What a block may show, and the block that shows it (§4.4, §4.5). The type is offered, not
     * installed: `webx:blocks:offered --install` puts it on the site once, and a type the site
     * already has by that name is never touched.
     */
    private function registerCollection(): void
    {
        $this->app->singleton(ReviewsSource::class);
        $this->app->make(CollectionSources::class)->register($this->app->make(ReviewsSource::class));

        $this->app->make(BlockOffers::class)->offer(ReviewsModule::ID, __DIR__.'/../resources/blocks');
    }

    /**
     * Two sections in a group of their own (§4.6): the reviews, and their categories.
     *
     * The group is added to the panel's config at boot rather than shipped as a default, because a
     * site that published `webx-admin.php` has its own copy of the list (CLAUDE.md §4).
     */
    private function registerPanel(): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $this->config()->get('webx-admin.groups', []);

        if (! array_key_exists(ReviewsGroup::GROUP, $groups)) {
            $this->config()->set('webx-admin.groups', [
                ...$groups,
                ReviewsGroup::GROUP => [
                    'title' => 'webx-reviews::module.group',
                    'icon' => 'star',
                    'order' => 600,
                ],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach ([ReviewsModule::class, CategoriesModule::class] as $module) {
            $modules->register($this->app->make($module));
        }
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
