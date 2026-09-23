<?php

declare(strict_types=1);

namespace WebxUi\Admin;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use WebxUi\Admin\Backups\Backups;
use WebxUi\Admin\Categories\CategoriesType;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Console\BackupCommand;
use WebxUi\Admin\Console\BootCommand;
use WebxUi\Admin\Console\DemoCommand;
use WebxUi\Admin\Console\DoctorCommand;
use WebxUi\Admin\Console\InstallCommand;
use WebxUi\Admin\Console\MakeModuleCommand;
use WebxUi\Admin\Console\PanelCommand;
use WebxUi\Admin\Console\PruneVersionsCommand;
use WebxUi\Admin\Console\SetupCommand;
use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\Contracts\SiteUrls;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Admin\Links\RoutingSiteUrls;
use WebxUi\Admin\Manifest\ManifestBuilder;
use WebxUi\Admin\Notes\NoteTypes;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Types\BooleanType;
use WebxUi\Admin\Screens\Types\CascaderType;
use WebxUi\Admin\Screens\Types\ColorType;
use WebxUi\Admin\Screens\Types\DateRangeType;
use WebxUi\Admin\Screens\Types\DateType;
use WebxUi\Admin\Screens\Types\InstantType;
use WebxUi\Admin\Screens\Types\LinkType;
use WebxUi\Admin\Screens\Types\NumberType;
use WebxUi\Admin\Screens\Types\OptionListType;
use WebxUi\Admin\Screens\Types\OptionType;
use WebxUi\Admin\Screens\Types\RateType;
use WebxUi\Admin\Screens\Types\RepeaterType;
use WebxUi\Admin\Screens\Types\RichTextType;
use WebxUi\Admin\Screens\Types\SliderType;
use WebxUi\Admin\Screens\Types\SlugType;
use WebxUi\Admin\Screens\Types\StringType;
use WebxUi\Admin\Screens\Types\TagsType;
use WebxUi\Admin\Screens\Types\TimeType;
use WebxUi\Admin\Screens\Types\TreeSelectType;
use WebxUi\Localization\Locales;
use WebxUi\Routing\SiteUrl;

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

        // What this panel can link to (§3 of the menu spec). A singleton for the same reason as
        // the two above: content modules register into it from their own providers.
        $this->app->singleton(LinkSources::class);

        // Which model's categories a `wx-categories` field is about, by the path they answer at.
        $this->app->singleton(CategorySources::class);

        // The language prefix, when there is an address registry to ask. Behind `class_exists`
        // because the frame does not require `webx-ui/routing` — a panel of settings and
        // administrators has no addresses at all — and a path is then handed on as written.
        if (class_exists(SiteUrl::class)) {
            $this->app->singleton(SiteUrls::class, RoutingSiteUrls::class);
        }

        $this->app->bind(LinkUrls::class, static fn ($app): LinkUrls => new LinkUrls(
            $app->make(LinkSources::class),
            $app->make(Locales::class),
            $app->bound(SiteUrls::class) ? $app->make(SiteUrls::class) : null,
        ));

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
            $types->register('wx-checkbox-group', new OptionListType);
            $types->register('wx-segmented', new OptionType);
            $types->register('wx-slider', new SliderType);
            $types->register('wx-rate', new RateType);
            $types->register('wx-time-picker', new TimeType);
            $types->register('wx-date-time-picker', new InstantType);
            $types->register('wx-date-range-picker', new DateRangeType);
            $types->register('wx-tags-input', new TagsType);
            // Free text with suggestions beside it: the suggestions are help, not a list to pick from.
            $types->register('wx-autocomplete', new StringType(2000));
            // A name from the icon set. The set is the page's, and a project can add to it, so the
            // server does not know which names exist and checks only that it is a short string.
            $types->register('wx-icon-picker', new StringType(255));
            $types->register('wx-code-editor', new StringType);
            // The address part of a category (`categoryLinks()` modules): drawn with the module's
            // prefix in front of it by the panel, checked for its shape here.
            $types->register('wx-category-slug', new SlugType);
            // The categories a record is in. Which table is the node's `source`, registered by
            // the module that owns it — the same string the panel asks for the list at.
            $types->register('wx-categories', new CategoriesType($app->make(CategorySources::class)));
            $types->register('wx-cascader', new CascaderType);
            $types->register('wx-tree-select', new TreeSelectType);
            $types->register('wx-transfer', new OptionListType('items'));
            // The library is a module's, not the panel's — a site with no file manager has
            // nothing to ask where a picture lives, and the type then leaves addresses alone.
            $types->register('wx-rich-text', new RichTextType(
                $app->bound(AssetUrls::class) ? $app->make(AssetUrls::class) : null,
            ));

            // A link: the entity or the path, never the address. The picker behind it is the
            // panel's own, and the sections in it are whatever the content modules registered.
            $types->register('wx-link', new LinkType(
                $app->make(LinkSources::class),
                $app->make(LinkUrls::class),
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
        $this->registerCategoryMacros();
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
            BootCommand::class,
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

    /**
     * `$table->category()` and `$table->categoryLinks()` — the columns every module's categories
     * have, and the link table with both orders in it (§3.2 of the services spec). A module adds
     * what only its categories have (a cover, an introduction) beside the macro.
     */
    private function registerCategoryMacros(): void
    {
        if (! Blueprint::hasMacro('category')) {
            Blueprint::macro('category', function (): void {
                /** @var Blueprint $this */
                // Translatable. A category without addresses leaves the slug empty.
                $this->json('title')->nullable();
                $this->json('slug')->nullable();
                $this->integer('position')->default(0);
                $this->boolean('is_visible')->default(true);
                // The fields a project patched onto the category's screen (`HasExtra`).
                $this->json('extra')->nullable();
                $this->softDeletes();
                $this->timestamps();

                $this->index('position');
            });
        }

        if (! Blueprint::hasMacro('categoryLinks')) {
            Blueprint::macro('categoryLinks', function (string $item, string $categories, ?string $items = null): void {
                /** @var Blueprint $this */
                $this->foreignId($item.'_id')->constrained($items ?? Str::plural($item))->cascadeOnDelete();
                $this->foreignId('category_id')->constrained($categories)->cascadeOnDelete();

                // The order of the categories on the item; the first one is the main one.
                $this->integer('position')->default(0);
                // The place of the item inside the category, for a list dragged with a filter on.
                $this->integer('item_position')->default(0);

                $this->unique([$item.'_id', 'category_id']);
                $this->index(['category_id', 'item_position']);
            });
        }
    }
}
