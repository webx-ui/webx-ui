<?php

declare(strict_types=1);

namespace WebxUi\Vacancies;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Routing\Formatters\Prefixed;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Rendering\SeoSources;
use WebxUi\Seo\Sitemap\SitemapRoutes;
use WebxUi\Vacancies\Handlers\VacancyHandler;
use WebxUi\Vacancies\Http\Controllers\IndexController;
use WebxUi\Vacancies\Links\VacancyLinkSource;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;
use WebxUi\Vacancies\Panel\CategoriesModule;
use WebxUi\Vacancies\Panel\Revision;
use WebxUi\Vacancies\Panel\VacanciesGroup;
use WebxUi\Vacancies\Panel\VacanciesModule;
use WebxUi\Vacancies\Seo\ClosedSource;
use WebxUi\Vacancies\Support\Salary;

/**
 * One entity with an address, one route that is not an entity — the index — and the screens they
 * are edited on. What is shared is somebody else's: the categories and the relations are
 * `module-admin`'s, the addresses `routing`'s, the sitemap and the `<head>` `module-seo`'s, and
 * the application form `module-inbox`'s, when it is installed.
 */
class VacanciesServiceProvider extends ServiceProvider
{
    /** The name of the index route — what the sitemap and a site's templates know it by. */
    public const INDEX_ROUTE = 'webx.vacancies.index';

    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('vacancies', 'vacancy_categories', 'vacancy_category_vacancy');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-vacancies.php', 'webx-vacancies');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-vacancies');

        // Before anything is registered under it: a prefix nobody gave is a site whose vacancies
        // would stand among its pages, and that is refused rather than half built.
        $prefix = self::prefix($this->config());

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-vacancies');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerRouteType($prefix);
        $this->registerIndex($prefix);
        $this->registerScreens();
        $this->app->make(LinkSources::class)->register($this->app->make(VacancyLinkSource::class));
        $this->app->make(SeoSources::class)->register($this->app->make(ClosedSource::class));
        $this->registerPanel();
        $this->registerEditedRecord();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-vacancies.php' => config_path('webx-vacancies.php'),
        ], 'webx-vacancies-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-vacancies'),
        ], 'webx-vacancies-lang');

        // The page and its parts are the least markup that works, and meant to be rewritten one
        // part at a time: a site publishes them all and deletes what it keeps as it is.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-vacancies'),
        ], 'webx-vacancies-views');
    }

    /**
     * The prefix, normalised — and never empty (decision 1): flat addresses of vacancies at the
     * root of the site would argue with the tree of pages over every address.
     *
     * @throws InvalidArgumentException
     */
    public static function prefix(Config $config): string
    {
        $prefix = UrlNormaliser::key((string) $config->get('webx-vacancies.prefix', 'careers'));

        if ($prefix === '') {
            throw new InvalidArgumentException((string) __('webx-vacancies::errors.prefix'));
        }

        return $prefix;
    }

    /**
     * A vacancy under the prefix. `Fail`: a clash is an error under the field, not a quiet `-2`.
     * No type for the categories — they have no addresses (decision 2, §7).
     */
    private function registerRouteType(string $prefix): void
    {
        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: Vacancy::TYPE,
            model: Vacancy::class,
            formatter: new Prefixed($prefix, Slug::class),
            handler: VacancyHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));
    }

    /**
     * The index: an ordinary route, so it wins before the registry's fallback, and `Reserved`
     * closes its address to pages of its own accord. Switched off, it is not registered, and the
     * address is free for a page (decision 1).
     *
     * Twice where the language is in the path; the prefixed copy would match `anything/careers`
     * without {@see OneSpellingPerAddress} in front of it.
     */
    private function registerIndex(string $prefix): void
    {
        if (! (bool) $this->config()->get('webx-vacancies.index', true)) {
            return;
        }

        /** @var list<string> $middleware */
        $middleware = array_values((array) $this->config()->get('webx-vacancies.middleware', ['web', 'webx.locale']));

        Route::get($prefix, IndexController::class)
            ->middleware($middleware)
            ->name(self::INDEX_ROUTE);

        if ((string) $this->config()->get('webx-localization.strategy', 'prefix') === 'prefix') {
            Route::get('{'.OneSpellingPerAddress::PARAMETER.'}/'.$prefix, IndexController::class)
                ->middleware([...$middleware, OneSpellingPerAddress::class])
                ->name(self::INDEX_ROUTE.'.localised');
        }

        $this->app->make(SitemapRoutes::class)->register(self::INDEX_ROUTE);
    }

    /**
     * The editor of a vacancy and the form of its category, both described, so a project adds a
     * field with a patch and `module-seo` its card the same way. The currencies are laid over the
     * select from the config (§4.9): the JSON has no options, so a site adds a currency with one
     * line of config.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Vacancy::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->register(VacancyCategory::SCREEN, __DIR__.'/../resources/screens/category-form.json');

        $screens->extend(Vacancy::SCREEN, [[
            'op' => 'set',
            'target' => 'salary-currency',
            'props' => ['options' => array_map(
                static fn (string $code, string $symbol): array => ['value' => $code, 'label' => $code.' · '.$symbol],
                array_keys($currencies = $this->app->make(Salary::class)->currencies()),
                array_values($currencies),
            )],
        ]]);

        // `wx-categories` names its categories by the path they answer at; this is where that
        // path is told which table the ids live in. From the provider, which `route:cache` runs.
        $this->app->make(CategorySources::class)
            ->register('vacancies/categories', VacancyCategory::class, 'webx-vacancies::errors.unknown-category');
    }

    /**
     * Two sections in a group of their own (§4.10): Vacancies · Categories. The group is added to
     * the panel's config at boot, because a site that published `webx-admin.php` has its own copy
     * of the list (CLAUDE.md §4).
     */
    private function registerPanel(): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $this->config()->get('webx-admin.groups', []);

        if (! array_key_exists(VacanciesGroup::GROUP, $groups)) {
            $this->config()->set('webx-admin.groups', [
                ...$groups,
                VacanciesGroup::GROUP => [
                    'title' => 'webx-vacancies::module.group',
                    'icon' => 'briefcase',
                    'order' => 650,
                ],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach ([VacanciesModule::class, CategoriesModule::class] as $module) {
            $modules->register($this->app->make($module));
        }
    }

    /**
     * The editor's heartbeat asks after a vacancy by this name: whether it moved under the
     * editor, who moved it, who else has it open.
     */
    private function registerEditedRecord(): void
    {
        $this->app->make(EditedRecords::class)->register(
            'vacancies',
            ['vacancies.view', 'vacancies.manage'],
            static function (string $id): ?array {
                // A vacancy in the bin too: the editor open on it hears that it went there.
                $vacancy = ctype_digit($id) ? Vacancy::withTrashed()->find((int) $id) : null;

                return $vacancy instanceof Vacancy ? ['revision' => Revision::of($vacancy), 'model' => $vacancy] : null;
            },
            'vacancies.manage',
            model: Vacancy::class,
        );
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
