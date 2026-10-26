<?php

declare(strict_types=1);

namespace WebxUi\Configurator;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorsModule;
use WebxUi\Configurator\Panel\Revision;
use WebxUi\Configurator\Relations\ConfiguratorTarget;
use WebxUi\Configurator\Screens\ConfiguratorFieldType;

/**
 * Configurators: the table, the engine, the section of the panel and its MCP tools.
 *
 * What reaches the site — the block, the view, the script and the request with its configuration
 * — comes with the next stage of the module (§19); this half is everything that does not need a
 * page: storing, checking and pricing a configurator, building one in the panel and through MCP,
 * and choosing one in a field of another screen.
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
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // A configurator is what a block chooses (§12.1): `wx-configurator` keeps its key and
        // refuses one nobody has, and the picker lists them through the relation target.
        $this->app->make(RelationTargets::class)->register(new ConfiguratorTarget);
        $this->app->make(FieldTypes::class)->register('wx-configurator', new ConfiguratorFieldType);

        $this->registerEditedRecord();

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

    /**
     * The editor's heartbeat asks after a configurator by this name: whether somebody saved it
     * meanwhile, who else has it open, whether it went to the bin.
     */
    private function registerEditedRecord(): void
    {
        $this->app->make(EditedRecords::class)->register(
            'configurators',
            ['configurators.view', 'configurators.manage'],
            static function (string $id): ?array {
                $configurator = ctype_digit($id) ? Configurator::withTrashed()->find((int) $id) : null;

                return $configurator instanceof Configurator ? ['revision' => Revision::of($configurator), 'model' => $configurator] : null;
            },
            'configurators.manage',
            model: Configurator::class,
        );
    }
}
