<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use WebxUi\Cache\CacheGroups;
use WebxUi\Cache\CacheServiceProvider;
use WebxUi\Localization\Console\ClearLocalesCommand;
use WebxUi\Localization\Console\SeedLocalesCommand;
use WebxUi\Localization\Http\Middleware\SetLocale;
use WebxUi\Localization\Http\Middleware\SetPanelLocale;
use WebxUi\Localization\Models\Locale;
use WebxUi\Localization\Translations\DictionaryBuilder;

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

        $this->addValidationLines();
        $this->registerBlueprintMacro();
        $this->registerCacheGroup();

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('webx.locale', SetLocale::class);
        $router->aliasMiddleware('webx.panel-locale', SetPanelLocale::class);

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
     * Put this package's validation lines behind the application's own.
     *
     * Laravel ships them in English and nowhere else, so a panel translated into ten languages
     * answers a bad form in English. `addPath` appends rather than replaces — the application's
     * `lang/` is still read first, so publishing your own line still wins.
     *
     * The loader is bound under a string rather than under its interface, which is why this
     * asks for it by name.
     */
    private function addValidationLines(): void
    {
        if (! $this->app->bound('translation.loader')) {
            return;
        }

        $loader = $this->app->make('translation.loader');

        if ($loader instanceof FileLoader) {
            $loader->addPath(__DIR__.'/../lang');
        }
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
