<?php

declare(strict_types=1);

namespace WebxUi\Configurator;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Configurator\Panel\ConfiguratorsModule;

/**
 * Configurators: the table, the engine, the section of the panel and its MCP tools.
 *
 * What reaches the site — the block, the view, the script and the request with its configuration
 * — comes with the next stages of the module (§19); this half is everything that does not need a
 * page: storing, checking and pricing a configurator, and building one through MCP.
 */
class ConfiguratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // A configurator is content: it moves between stands with webx:snapshot.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('configurators');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-configurator.php', 'webx-configurator');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-configurator');

        $this->app->make(ModuleRegistry::class)->register($this->app->make(ConfiguratorsModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-configurator.php' => config_path('webx-configurator.php'),
        ], 'webx-configurator-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-configurator'),
        ], 'webx-configurator-lang');
    }
}
