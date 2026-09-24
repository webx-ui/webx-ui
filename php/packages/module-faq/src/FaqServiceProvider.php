<?php

declare(strict_types=1);

namespace WebxUi\Faq;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Faq\Collections\FaqSource;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;
use WebxUi\Faq\Panel\CategoriesModule;
use WebxUi\Faq\Panel\FaqGroup;
use WebxUi\Faq\Panel\QuestionsModule;

/**
 * Questions, their categories, and the screens they are edited on — and not one public route
 * (decision 6). A question reaches the site in a block: the module offers the block type and a
 * source for it, and the page it stands on brings the address, the SEO and the menu entry.
 */
class FaqServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-faq.php', 'webx-faq');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-faq');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerScreens();
        $this->registerCollection();
        $this->registerPanel();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-faq.php' => config_path('webx-faq.php'),
        ], 'webx-faq-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-faq'),
        ], 'webx-faq-lang');
    }

    /**
     * The editor of a question and the page of a category, both described, so a project adds a
     * field with a patch.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Question::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->register(FaqCategory::SCREEN, __DIR__.'/../resources/screens/category-form.json');

        // `wx-categories` on the question form, and the choice of a FAQ block, name the
        // categories by the path they answer at; this is where that path is told which table the
        // ids live in. From the provider rather than the routes file, which `route:cache` never runs.
        $this->app->make(CategorySources::class)->register(
            'faq/categories',
            FaqCategory::class,
            'webx-faq::errors.unknown-category',
        );
    }

    /**
     * What a block may show, and the block that shows it (§4.3, §4.4). The type is offered, not
     * installed: `webx:blocks:offered --install` puts it on the site once, and a type the site
     * already has by that name is never touched.
     */
    private function registerCollection(): void
    {
        $this->app->singleton(FaqSource::class);
        $this->app->make(CollectionSources::class)->register($this->app->make(FaqSource::class));

        $this->app->make(BlockOffers::class)->offer(QuestionsModule::ID, __DIR__.'/../resources/blocks');
    }

    /**
     * Two sections in a group of their own (§4.5): the questions, and their categories.
     *
     * The group is added to the panel's config at boot rather than shipped as a default, because a
     * site that published `webx-admin.php` has its own copy of the list (CLAUDE.md §4).
     */
    private function registerPanel(): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $this->config()->get('webx-admin.groups', []);

        if (! array_key_exists(FaqGroup::GROUP, $groups)) {
            $this->config()->set('webx-admin.groups', [
                ...$groups,
                FaqGroup::GROUP => [
                    'title' => 'webx-faq::module.group',
                    'icon' => 'question',
                    'order' => 500,
                ],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach ([QuestionsModule::class, CategoriesModule::class] as $module) {
            $modules->register($this->app->make($module));
        }
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
