<?php

declare(strict_types=1);

namespace WebxUi\Admin;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Console\InstallCommand;
use WebxUi\Admin\Console\MakeModuleCommand;
use WebxUi\Admin\Console\PanelCommand;
use WebxUi\Admin\Console\PruneVersionsCommand;
use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\Manifest\ManifestBuilder;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Types\BooleanType;
use WebxUi\Admin\Screens\Types\ColorType;
use WebxUi\Admin\Screens\Types\DateType;
use WebxUi\Admin\Screens\Types\NumberType;
use WebxUi\Admin\Screens\Types\OptionType;
use WebxUi\Admin\Screens\Types\RepeaterType;
use WebxUi\Admin\Screens\Types\StringType;
use WebxUi\Localization\Locales;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-admin.php', 'webx-admin');

        // One registry for the request: modules register into it from their own providers,
        // and the manifest reads whatever is there by the time a request arrives.
        $this->app->singleton(ModuleRegistry::class);

        // Same for screens and the field types they are written in: modules and the project
        // add theirs from `boot()`, and the endpoints read the sum.
        $this->app->singleton(ScreenRegistry::class);
        $this->app->singleton(FieldTypes::class, static function ($app): FieldTypes {
            $types = new FieldTypes;

            $types->register('wx-input', new StringType(2000));
            $types->register('wx-textarea', new StringType);
            $types->register('wx-input-number', new NumberType);
            $types->register('wx-switch', new BooleanType);
            $types->register('wx-checkbox', new BooleanType);
            $types->register('wx-select', new OptionType);
            $types->register('wx-radio-group', new OptionType);
            $types->register('wx-date-picker', new DateType);
            $types->register('wx-color-picker', new ColorType);

            // The repeater checks and casts its items with the other types, so it is handed
            // the registry it is being put into.
            $types->register('wx-repeater', new RepeaterType(
                $types,
                $app->make(Locales::class),
                $app->make(ValidationFactory::class),
            ));

            return $types;
        });

        $this->app->bind(
            ManifestBuilder::class,
            static fn ($app): ManifestBuilder => new ManifestBuilder(
                $app->make(ModuleRegistry::class),
                $app->make('config'),
                $app->make(Locales::class),
                $app->make(ScreenRegistry::class),
                // Optional on purpose: a site without `module-settings` has nowhere to put a
                // logo, and the frame must not require the section that holds one.
                $app->bound(BrandingSource::class) ? $app->make(BrandingSource::class) : null,
            ),
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-admin');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-admin');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerDraftMacro();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-admin.php' => config_path('webx-admin.php'),
        ], 'webx-admin-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-admin'),
        ], 'webx-admin-views');

        // Publishing these is how a site adds a language the package never shipped, or
        // disagrees with a word in one it did.
        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-admin'),
        ], 'webx-admin-lang');

        $this->commands([
            InstallCommand::class,
            MakeModuleCommand::class,
            PanelCommand::class,
            PruneVersionsCommand::class,
        ]);
    }

    /**
     * `$table->draft()` — the two columns `HasDraft` reads: the draft itself and when the
     * entity was last published — so a migration says what it adds rather than how.
     */
    private function registerDraftMacro(): void
    {
        if (! Blueprint::hasMacro('draft')) {
            Blueprint::macro('draft', function (string $column = 'draft', string $publishedAt = 'published_at'): void {
                /** @var Blueprint $this */
                $this->json($column)->nullable();
                $this->timestamp($publishedAt)->nullable();
            });
        }

        if (! Blueprint::hasMacro('dropDraft')) {
            Blueprint::macro('dropDraft', function (string $column = 'draft', string $publishedAt = 'published_at'): void {
                /** @var Blueprint $this */
                $this->dropColumn([$column, $publishedAt]);
            });
        }
    }
}
