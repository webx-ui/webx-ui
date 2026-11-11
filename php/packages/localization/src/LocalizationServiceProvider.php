<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Cache\CacheGroups;
use WebxUi\Cache\CacheServiceProvider;
use WebxUi\Localization\Console\ClearLocalesCommand;
use WebxUi\Localization\Console\SeedLocalesCommand;
use WebxUi\Localization\Contracts\PageInLanguage;
use WebxUi\Localization\Doctor\FirstVisitCheck;
use WebxUi\Localization\Http\Middleware\SetLocale;
use WebxUi\Localization\Http\Middleware\SetPanelLocale;
use WebxUi\Localization\Models\Locale;
use WebxUi\Localization\Translations\DictionaryBuilder;
use WebxUi\Localization\Translations\ValidationLines;

class LocalizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-localization.php', 'webx-localization');

        // The registry of cache groups has to be one singleton before `boot()` declares into it,
        // whether the site found `webx-ui/cache` by discovery or a test listed the providers.
        $this->app->register(CacheServiceProvider::class);

        // One list per request: it is read by routing, by models, by the manifest, and asking
        // the database each time would be a query on every translated attribute.
        $this->app->singleton(Locales::class, static fn ($app): Locales => new Locales(
            $app,
            $app->make('config'),
        ));

        // Nothing here knows the site's addresses: `webx-ui/widgets` binds the switcher's own
        // answer, whichever of the two providers runs first (ROUTING §18.1, 10).
        $this->app->bindIf(PageInLanguage::class, NoPageInLanguage::class);

        // Extended rather than replaced, as `webx-ui/themes` does: whichever wraps the other, both
        // answer, and the loader the site set up stays the one underneath.
        $this->app->extend('translation.loader', static fn (Loader $loader): ValidationLines => new ValidationLines(
            $loader,
            __DIR__.'/../lang',
        ));

        $this->app->singleton(DictionaryBuilder::class, static fn ($app): DictionaryBuilder => new DictionaryBuilder(
            $app,
            // Laravel binds the loader under a string, not the contract it implements.
            $app->make('translation.loader'),
            $app->make('config'),
            $app->make('cache')->store(),
            $app->make(Locales::class),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerBlueprintMacro();
        $this->registerCacheGroup();

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('webx.locale', SetLocale::class);
        $router->aliasMiddleware('webx.panel-locale', SetPanelLocale::class);

        // The language of the first visit is read by a proxy in front of the site as well, and its
        // value is no secret (ROUTING §18.1, 7); the declined offer is written by a script (§18.6).
        if (class_exists(EncryptCookies::class)) {
            $config = $this->app->make('config');
            EncryptCookies::except([
                (string) $config->get('webx-localization.first_visit.cookie', 'webx_lang'),
                (string) $config->get('webx-localization.first_visit.offer.cookie', 'webx_lang_offer'),
            ]);
        }

        // `webx:doctor` is the frame's, which this package does not require.
        if (class_exists(DoctorChecks::class)) {
            $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
                $checks->register(FirstVisitCheck::class);
            });
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-localization.php' => config_path('webx-localization.php'),
        ], 'webx-localization-config');

        $this->commands([
            ClearLocalesCommand::class,
            SeedLocalesCommand::class,
        ]);
    }

    /**
     * The `locales` cache group (WEBX_UI_CACHE.md §2.1): a language saved through its model moves
     * it, and the list itself lives on the group's versioned key ({@see Locales::rows()}). The
     * words the panel shows for the group are the frame's, where there is one.
     */
    private function registerCacheGroup(): void
    {
        $groups = $this->app->make(CacheGroups::class);

        $groups->declare(CacheGroups::LOCALES);
        $groups->bumpOn(CacheGroups::LOCALES, Locale::class);
        // Every page reads the languages: with the versions, in the same call.
        $groups->prefetch('locales', [CacheGroups::LOCALES], localized: false);
    }

    /**
     * `$table->translatable('title', 'slug')` — so a migration says which columns hold a
     * language map instead of leaving the reader to infer it from a `json` column.
     */
    private function registerBlueprintMacro(): void
    {
        if (! Blueprint::hasMacro('translatable')) {
            Blueprint::macro('translatable', function (string ...$columns): void {
                /** @var Blueprint $this */
                Translatable::columns($this, ...$columns);
            });
        }

        if (! Blueprint::hasMacro('dropTranslatable')) {
            Blueprint::macro('dropTranslatable', function (string ...$columns): void {
                /** @var Blueprint $this */
                Translatable::dropColumns($this, ...$columns);
            });
        }
    }
}
