<?php

declare(strict_types=1);

namespace WebxUi\Admin;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Backups\Backups;
use WebxUi\Admin\Console\BackupCommand;
use WebxUi\Admin\Console\DemoCommand;
use WebxUi\Admin\Console\DoctorCommand;
use WebxUi\Admin\Console\InstallCommand;
use WebxUi\Admin\Console\MakeModuleCommand;
use WebxUi\Admin\Console\PanelCommand;
use WebxUi\Admin\Console\PruneVersionsCommand;
use WebxUi\Admin\Console\SetupCommand;
use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Manifest\ManifestBuilder;
use WebxUi\Admin\Notes\NoteTypes;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Types\BooleanType;
use WebxUi\Admin\Screens\Types\ColorType;
use WebxUi\Admin\Screens\Types\DateType;
use WebxUi\Admin\Screens\Types\NumberType;
use WebxUi\Admin\Screens\Types\OptionType;
use WebxUi\Admin\Screens\Types\RepeaterType;
use WebxUi\Admin\Screens\Types\RichTextType;
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

        // Which records have notes. A register rather than the morph map alone, because the
        // type comes out of an address and must not be able to name anything else.
        $this->app->singleton(NoteTypes::class);
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
            // The library is a module's, not the panel's — a site with no file manager has
            // nothing to ask where a picture lives, and the type then leaves addresses alone.
            $types->register('wx-rich-text', new RichTextType(
                $app->bound(AssetUrls::class) ? $app->make(AssetUrls::class) : null,
            ));

            // The repeater checks and casts its items with the other types, so it is handed
            // the registry it is being put into.
            $types->register('wx-repeater', new RepeaterType(
                $types,
                $app->make(Locales::class),
                $app->make(ValidationFactory::class),
            ));

            return $types;
        });

        // The backup directory has no state anywhere else, so this holds no state either: it
        // is a singleton to be injectable by name, not because it remembers anything.
        $this->app->singleton(Backups::class);

        // One journal for the run, shared by every module that seeds into it. The path is
        // fixed rather than configurable: it is a file two commands pass between them, and a
        // site that moved it would gain nothing and lose the answer to "where is it".
        $this->app->singleton(DemoLedger::class, static fn ($app): DemoLedger => new DemoLedger(
            $app->make(Filesystem::class),
            $app->make(FilesystemFactory::class),
            $app->storagePath('app/webx-demo.json'),
        ));

        $this->app->bind(
            ManifestBuilder::class,
            static fn ($app): ManifestBuilder => new ManifestBuilder(
                $app->make(ModuleRegistry::class),
                $app->make('config'),
                $app->make(Locales::class),
                $app->make(ScreenRegistry::class),
                $app->make(Backups::class),
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
        $this->registerBackupSchedule();

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
            BackupCommand::class,
            DemoCommand::class,
            DoctorCommand::class,
            InstallCommand::class,
            MakeModuleCommand::class,
            PanelCommand::class,
            PruneVersionsCommand::class,
            SetupCommand::class,
        ]);
    }

    /**
     * The nightly dump, put on the schedule by the package rather than by the site.
     *
     * A backup nobody remembered to schedule is the ordinary way to have no backup, so the
     * default is on and the site turns it off rather than on. What the site does have to
     * supply is the system cron behind `schedule:run` — there is no way to do that from here,
     * and the panel's own line is what notices when it is missing.
     *
     * `callAfterResolving` because the scheduler is built on the first console command that
     * needs one: asking for it here would build it during boot, before the application has
     * finished deciding what it is.
     */
    private function registerBackupSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $backups = $this->app->make(Backups::class);

            if (! $backups->enabled()) {
                return;
            }

            $schedule->command(BackupCommand::class)
                ->dailyAt($backups->at())
                // Two web servers behind one database would otherwise dump it twice a night.
                ->onOneServer()
                ->withoutOverlapping();
        });
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
