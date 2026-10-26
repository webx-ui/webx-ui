<?php

declare(strict_types=1);

namespace WebxUi\Configurator;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Cache\CacheGroups;
use WebxUi\Configurator\Inbox\ConfiguratorEnclosure;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorsModule;
use WebxUi\Configurator\Panel\Revision;
use WebxUi\Configurator\Relations\ConfiguratorTarget;
use WebxUi\Configurator\Rendering\Assets;
use WebxUi\Configurator\Screens\ConfiguratorFieldType;
use WebxUi\Configurator\Screens\PresetFieldType;
use WebxUi\Configurator\Site\LiveConfigurators;
use WebxUi\Configurator\View\Components\Configurator as ConfiguratorComponent;
use WebxUi\Inbox\Submissions\Enclosures;

/**
 * Configurators: the table, the engine, the section of the panel and its MCP tools — and the site:
 * `<x-webx-configurator>`, its view and base look, the widget's script, the offered block
 * `configurator` and the configuration enclosed in a request of the inbox (§11, §12).
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

        // The flag «the script is on this page already» belongs to the response being built.
        $this->app->scoped(Assets::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-configurator');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-configurator');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/public.php');

        // `<x-webx-configurator key="…" />` — in the offered block and in any template of the site.
        Blade::component('webx-configurator', ConfiguratorComponent::class);

        $this->registerCacheGroup();

        // A configurator is what a block chooses (§12.1): `wx-configurator` keeps its key and
        // refuses one nobody has, and the picker lists them through the relation target.
        $this->app->make(RelationTargets::class)->register(new ConfiguratorTarget);
        $this->app->make(FieldTypes::class)->register('wx-configurator', new ConfiguratorFieldType);
        $this->app->make(FieldTypes::class)->register('wx-configurator-preset', new PresetFieldType);

        // The block a site installs with `webx:blocks:offered --install` (§12.1).
        if (class_exists(BlockOffers::class) && $this->app->bound(BlockOffers::class)) {
            $this->app->make(BlockOffers::class)->offer(ConfiguratorsModule::ID, __DIR__.'/../resources/blocks');
        }

        // The configuration in a request of the inbox (§11.1), when the inbox is installed.
        if (class_exists(Enclosures::class) && $this->app->bound(Enclosures::class)) {
            $this->app->make(Enclosures::class)->register($this->app->make(ConfiguratorEnclosure::class));
        }

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

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-configurator'),
        ], 'webx-configurator-views');
    }

    /**
     * `configurators` (WEBX_UI_CACHE.md §2): the published configurators the site prints, moved by
     * any save of one — a publication, a draft, the bin.
     */
    private function registerCacheGroup(): void
    {
        $groups = $this->app->make(CacheGroups::class);

        $groups->declare(LiveConfigurators::GROUP, 'webx-configurator::module.title');
        $groups->bumpOn(LiveConfigurators::GROUP, Configurator::class);
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
