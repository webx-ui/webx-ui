<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use WebxUi\Localization\Console\ClearLocalesCommand;
use WebxUi\Localization\Console\SeedLocalesCommand;
use WebxUi\Localization\Http\Middleware\SetLocale;
use WebxUi\Localization\Http\Middleware\SetPanelLocale;
use WebxUi\Localization\Translations\DictionaryBuilder;

class LocalizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-localization.php', 'webx-localization');

        // One list per request: it is read by routing, by models, by the manifest, and asking
        // the database each time would be a query on every translated attribute.
        $this->app->singleton(Locales::class, static fn ($app): Locales => new Locales(
            $app,
            $app->make('config'),
            $app->make('cache')->store(),
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
